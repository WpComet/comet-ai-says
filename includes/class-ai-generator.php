<?php

namespace WpComet\AISays;

class AIGenerator {
    private $provider;
    private $api_key;
    private static $instance;

    public function __construct() {
        $this->provider = get_option('wpcmt_aisays_provider', 'gemini');

        // Get the appropriate API key based on provider
        if ('gemini' === $this->provider) {
            $this->api_key = get_option('wpcmt_aisays_gemini_api_key');
        } else {
            $this->api_key = get_option('wpcmt_aisays_openai_api_key');
        }

        add_action('wp_ajax_generate_ai_description', [$this, 'generate_description_ajax']);
        add_action('wp_ajax_save_ai_description', [$this, 'save_description_ajax']);
        add_action('wp_ajax_get_ai_description', [$this, 'get_description_ajax']);
    }

    private function generate_description($product) {
        if (empty($this->api_key)) {
            return false;
        }

        $language = get_option('wpcmt_aisays_language', 'english');
        $product_name = $product->get_name();
        $short_description = $product->get_short_description();
        $categories = wp_get_post_terms($product->get_id(), 'product_cat', ['fields' => 'names']);
        $attributes = $product->get_attributes();

        // Get the custom prompt template or use default
        $prompt_template = get_option('wpcmt_aisays_prompt_template', AdminInterface::get_default_prompt_template_public());

        // Prepare attributes string
        $attributes_string = '';
        if (!empty($attributes)) {
            foreach ($attributes as $attribute) {
                if ($attribute['is_taxonomy']) {
                    $terms = wp_get_post_terms($product->get_id(), $attribute['name'], ['fields' => 'names']);
                    if (!empty($terms)) {
                        $attributes_string .= '- '.wc_attribute_label($attribute['name']).': '.implode(', ', $terms)."\n";
                    }
                } else {
                    $attributes_string .= '- '.wc_attribute_label($attribute['name']).': '.$attribute['value']."\n";
                }
            }
        }

        // Get featured image analysis
        $image_analysis = $this->get_featured_image_analysis($product->get_id());

        // Get introduction and instructions
        $introduction = AdminInterface::get_lang_static($language, 'intro');
        $instructions = AdminInterface::get_lang_static($language, 'instructions');

        // Replace template variables
        $prompt = str_replace(
            [
                '{introduction}',
                '{product_name}',
                '{short_description}',
                '{categories}',
                '{attributes}',
                '{image_analysis}',
                '{instructions}',
            ],
            [
                $introduction,
                $product_name,
                $short_description ?: __('No short description provided', 'comet-ai-says'),
                !empty($categories) ? implode(', ', $categories) : __('No categories', 'comet-ai-says'),
                $attributes_string ?: __('No specifications provided', 'comet-ai-says'),
                $image_analysis ?: __('No image analysis available', 'comet-ai-says'),
                $instructions,
            ],
            $prompt_template
        );

        // Call the appropriate API based on provider
        switch ($this->provider) {
            case 'openai':
                AdminInterface::track_usage('generation');

                return $this->call_openai_api($prompt, $product->get_id());
            case 'gemini':
                AdminInterface::track_usage('generation');

                return $this->call_gemini_api($prompt, $product->get_id());
            default:
                return false;
        }
    }

    private function get_featured_image_analysis($product_id) {
        $featured_image_id = get_post_thumbnail_id($product_id);

        if (!$featured_image_id) {
            return __('No featured image available for analysis.', 'comet-ai-says');
        }

        $image_url = wp_get_attachment_image_url($featured_image_id, 'full');

        if (!$image_url) {
            return __('Featured image could not be processed.', 'comet-ai-says');
        }

        // Get image alt text and caption for additional context
        $image_alt = get_post_meta($featured_image_id, '_wp_attachment_image_alt', true);
        $image_caption = wp_get_attachment_caption($featured_image_id);

        $analysis_context = '';
        if ($image_alt) {
            // translators: %s is the image alt text
            $analysis_context .= sprintf(__('Image alt text: "%s". ', 'comet-ai-says'), $image_alt);
        }
        if ($image_caption) {
            // translators: %s is the image caption
            $analysis_context .= sprintf(__('Image caption: "%s". ', 'comet-ai-says'), $image_caption);
        }

        return $analysis_context.__('The product has a featured image that shows the actual product. Use visual cues from the image to enhance the description.', 'comet-ai-says');
    }

    /**
     * Unified Gemini API call that handles both text and image prompts.
     *
     * @example $description = $this->call_gemini_api($prompt, $product_id); // With image if available
     * @example $description = $this->call_gemini_api($prompt); // Text-only
     *
     * @param mixed      $prompt
     * @param null|mixed $product_id
     */
    private function call_gemini_api($prompt, $product_id = null) {
        $gemini_model = get_option('wpcmt_aisays_gemini_model', 'gemini-2.0-flash');
        $max_tokens = get_option('wpcmt_aisays_max_tokens', 1024);

        // Check if we should use image analysis
        $use_image = $product_id && get_post_thumbnail_id($product_id);
        $image_data = null;

        if ($use_image) {
            $image_data = $this->prepare_gemini_image_data($product_id);
            if (!$image_data) {
                $use_image = false; // Fallback to text-only if image prep fails

                if (Plugin::$debug && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                    error_log('Comet AI Says - Gemini image preparation failed, falling back to text-only');
                }
            }
        }

        $api_url = 'https://generativelanguage.googleapis.com/v1/models/'.$gemini_model.':generateContent?key='.$this->api_key;

        // Build request parts
        $parts = [['text' => $prompt]];

        if ($use_image && $image_data) {
            $parts[] = [
                'inline_data' => [
                    'mime_type' => $image_data['mime_type'],
                    'data' => $image_data['base64'],
                ],
            ];

            if (Plugin::$debug && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                error_log('Comet AI Says - Using image analysis for product ID: '.$product_id);
            }
        }

        $request_body = [
            'contents' => [
                [
                    'parts' => $parts,
                ],
            ],
            'generationConfig' => [
                'temperature' => 0.7,
                'maxOutputTokens' => $max_tokens,
                'topP' => 0.8,
                'topK' => 40,
            ],
        ];

        $args = [
            'headers' => ['Content-Type' => 'application/json'],
            'body' => json_encode($request_body),
            'timeout' => 30,
        ];

        $response = wp_remote_post($api_url, $args);

        if (is_wp_error($response)) {
            if (Plugin::$debug && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                error_log('Comet AI Says - Gemini API Connection Error: '.$response->get_error_message());
            }

            return false;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (200 === $response_code && isset($body['candidates'][0]['content']['parts'][0]['text'])) {
            return trim($body['candidates'][0]['content']['parts'][0]['text']);
        }

        // If model not found or image failed, fallback appropriately
        if (404 === $response_code || $use_image) {
            if (Plugin::$debug && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                error_log('Comet AI Says - Gemini API fallback. Response code: '.$response_code.', Used image: '.($use_image ? 'yes' : 'no'));
            }

            if (404 === $response_code) {
                return $this->fallback_gemini_api($prompt);
            }

            // If image failed, retry without image
            return $this->call_gemini_api($prompt); // Recursive call without product_id
        }

        if (Plugin::$debug && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log,WordPress.PHP.DevelopmentFunctions.error_log_print_r
            error_log('Comet AI Says - Gemini API Error Response: '.print_r($body, true));
        }

        return false;
    }

    /**
     * Prepare image data for Gemini API.
     *
     * @param mixed $product_id
     */
    private function prepare_gemini_image_data($product_id) {
        $featured_image_id = get_post_thumbnail_id($product_id);
        if (!$featured_image_id) {
            return false;
        }

        $image_url = wp_get_attachment_image_url($featured_image_id, 'thumbnail');
        if (!$image_url) {
            return false;
        }

        // Download image and convert to base64
        $image_response = wp_remote_get($image_url);
        if (is_wp_error($image_response)) {
            return false;
        }

        $image_data = wp_remote_retrieve_body($image_response);
        $image_base64 = base64_encode($image_data);

        // Get image MIME type
        $image_info = getimagesize($image_url);
        $mime_type = $image_info['mime'] ?? 'image/jpeg';

        return [
            'base64' => $image_base64,
            'mime_type' => $mime_type,
        ];
    }

    /**
     * Unified OpenAI API call that handles both text and image prompts.
     *
     * @param mixed      $prompt
     * @param null|mixed $product_id
     */
    private function call_openai_api($prompt, $product_id = null) {
        $api_url = 'https://api.openai.com/v1/chat/completions';
        $model = 'gpt-4o'; // Use gpt-4o for both text and vision

        // Check if we should use image analysis
        $use_image = $product_id && get_post_thumbnail_id($product_id);
        $image_url = null;

        if ($use_image) {
            $image_url = wp_get_attachment_image_url(get_post_thumbnail_id($product_id), 'full');
            if (!$image_url) {
                $use_image = false; // Fallback to text-only if image URL fails

                if (Plugin::$debug && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                    error_log('Comet AI Says - OpenAI image URL preparation failed, falling back to text-only');
                }
            }
        }

        // Build messages array
        if ($use_image && $image_url) {
            $messages = [
                [
                    'role' => 'user',
                    'content' => [
                        ['type' => 'text', 'text' => $prompt],
                        ['type' => 'image_url', 'image_url' => ['url' => $image_url]],
                    ],
                ],
            ];

            if (Plugin::$debug && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                error_log('Comet AI Says - Using vision analysis for product ID: '.$product_id);
            }
        } else {
            $messages = [
                [
                    'role' => 'user',
                    'content' => $prompt,
                ],
            ];
        }

        $args = [
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer '.$this->api_key,
            ],
            'body' => json_encode([
                'model' => $model,
                'messages' => $messages,
                'max_tokens' => 500,
                'temperature' => 0.7,
            ]),
            'timeout' => 30,
        ];

        $response = wp_remote_post($api_url, $args);

        if (is_wp_error($response)) {
            if (Plugin::$debug && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                error_log('Comet AI Says - OpenAI API Error: '.$response->get_error_message());
            }

            return false;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($body['choices'][0]['message']['content'])) {
            return trim($body['choices'][0]['message']['content']);
        }

        // If vision failed, fallback to text-only
        if ($use_image) {
            if (Plugin::$debug && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log,WordPress.PHP.DevelopmentFunctions.error_log_print_r
                error_log('Comet AI Says - OpenAI Vision API failed, falling back to text-only. Response: '.print_r($body, true));
            }

            return $this->call_openai_api($prompt); // Recursive call without product_id
        }

        if (Plugin::$debug && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log,WordPress.PHP.DevelopmentFunctions.error_log_print_r
            error_log('Comet AI Says - OpenAI API Error Response: '.print_r($body, true));
        }

        return false;
    }

    private function fallback_gemini_api($prompt) {
        // Fallback to the current recommended free model
        $api_url = 'https://generativelanguage.googleapis.com/v1/models/gemini-2.0-flash:generateContent?key='.$this->api_key;

        $args = [
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode([
                'contents' => [
                    [
                        'parts' => [
                            [
                                'text' => $prompt,
                            ],
                        ],
                    ],
                ],
                'generationConfig' => [
                    'temperature' => 0.7,
                    'maxOutputTokens' => 500,
                ],
            ]),
            'timeout' => 30,
        ];

        $response = wp_remote_post($api_url, $args);

        if (is_wp_error($response)) {
            return false;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($body['candidates'][0]['content']['parts'][0]['text'])) {
            return trim($body['candidates'][0]['content']['parts'][0]['text']);
        }

        return false;
    }

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    // Static accessor
    public static function generate_for_product($product_or_id) {
        $instance = self::get_instance();

        // Check if it's already a WC_Product object
        $product = is_a($product_or_id, 'WC_Product') ? $product_or_id : wc_get_product($product_or_id);

        return $product ? $instance->generate_description($product) : false;
    }


    public function generate_description_ajax() {
        // Security check
        if (!check_ajax_referer('wpcmt_aisays_nonce', 'nonce', false)) {
            if (Plugin::$debug && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                error_log('Comet AI Says - Security check failed in generate_description_ajax');
            }
            wp_die(esc_html__('Security check failed', 'comet-ai-says'));
        }

        // Validate product_id exists
        if (!isset($_POST['product_id'])) {
            wp_send_json_error(esc_html__('Product ID is required', 'comet-ai-says'));
        }

        $product_id = intval($_POST['product_id']);
        $product = wc_get_product($product_id);

        if (!$product) {
            wp_send_json_error(esc_html__('Product not found', 'comet-ai-says'));
        }

        // Check for product-specific language
        $product_language = get_post_meta($product_id, '_wpcmt_aisays_language', true);
        if ($product_language && 'global' !== $product_language) {
            // Temporarily override the global language for this generation
            $original_language = get_option('wpcmt_aisays_language');
            update_option('wpcmt_aisays_language', $product_language);

            $description = $this->generate_description($product);

            // Restore original language
            update_option('wpcmt_aisays_language', $original_language);
        } else {
            $description = $this->generate_description($product);
        }

        if ($description) {
            wp_send_json_success(['description' => $description]);
        } else {
            wp_send_json_error(esc_html__('Failed to generate description. Check your API key and provider settings.', 'comet-ai-says'));
        }
    }

    public function save_description_ajax() {
        // Security check
        if (!check_ajax_referer('wpcmt_aisays_nonce', 'nonce', false)) {
            if (Plugin::$debug && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                error_log('Comet AI Says - Security check failed in save_description_ajax');
            }
            wp_die(esc_html__('Security check failed', 'comet-ai-says'));
        }

        // Validate required fields exist
        if (!isset($_POST['product_id']) || !isset($_POST['description'])) {
            wp_send_json_error(esc_html__('Missing required fields', 'comet-ai-says'));
        }

        $product_id = intval($_POST['product_id']);
        $description = wp_kses_post(wp_unslash($_POST['description']));

        update_post_meta($product_id, '_wpcmt_aisays_description', $description);

        wp_send_json_success(esc_html__('Description saved', 'comet-ai-says'));
    }

    public function get_description_ajax() {
        // Security check
        if (!check_ajax_referer('wpcmt_aisays_nonce', 'nonce', false)) {
            if (Plugin::$debug && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                error_log('Comet AI Says - Security check failed in get_description_ajax');
            }
            wp_die(esc_html__('Security check failed', 'comet-ai-says'));
        }

        // Validate product_id exists
        if (!isset($_POST['product_id'])) {
            wp_send_json_error(esc_html__('Product ID is required', 'comet-ai-says'));
        }

        $product_id = intval($_POST['product_id']);
        $description = get_post_meta($product_id, '_wpcmt_aisays_description', true);

        if ($description) {
            wp_send_json_success(['description' => $description]);
        } else {
            wp_send_json_error(esc_html__('No AI description found', 'comet-ai-says'));
        }
    }

    public static function log($message, $data = null) {
        if (!Plugin::$debug) {
            return;
        }

        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            $log_message = 'Comet AI Says: '.$message;
            if ($data) {
                $log_message .= ' - Data: '.wp_json_encode($data);
            }
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log($log_message);
        }
    }
    // Add to your AIGenerator class
public static function generate_single_ajax() {
    // Security check
    if (!check_ajax_referer('wpcmt_aisays_nonce', 'nonce', false)) {
        wp_send_json_error('Security check failed');
    }

    // Validate product_id exists
    if (!isset($_POST['product_id'])) {
        wp_send_json_error('Product ID is required');
    }

    $product_id = intval($_POST['product_id']);
    $product = wc_get_product($product_id);

    if (!$product) {
        wp_send_json_error('Product not found');
    }

    // Check for product-specific language
    $product_language = get_post_meta($product_id, '_wpcmt_aisays_language', true);
    if ($product_language && 'global' !== $product_language) {
        // Temporarily override the global language for this generation
        $original_language = get_option('wpcmt_aisays_language');
        update_option('wpcmt_aisays_language', $product_language);

        $description = self::generate_for_product($product);

        // Restore original language
        update_option('wpcmt_aisays_language', $original_language);
    } else {
        $description = self::generate_for_product($product);
    }

    if ($description) {
        update_post_meta($product_id, '_wpcmt_aisays_description', $description);
        AdminInterface::track_usage('generation');

        wp_send_json_success([
            'product_id' => $product_id,
            'product_name' => $product->get_name(),
            'description' => $description,
            // translators: product name
            'message' => sprintf(__('Successfully generated AI description for: %s', 'comet-ai-says'), $product->get_name())
        ]);
    } else {
        // translators: product name
        wp_send_json_error(sprintf(__('Failed to generate description for: %s. Check your API key and provider settings.', 'comet-ai-says'), $product->get_name()));
    }
}
}
