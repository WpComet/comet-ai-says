<?php

namespace WpComet\AISays;

class AIGenerator
{
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

        add_action('wp_ajax_wpcmt_aisays_generate_ai_description', [$this, 'generate_description_ajax']);
        add_action('wp_ajax_wpcmt_aisays_save_ai_description', [$this, 'save_description_ajax']);
        add_action('wp_ajax_wpcmt_aisays_get_ai_description', [$this, 'get_description_ajax']);
        add_action('wp_ajax_wpcmt_aisays_delete_ai_description', [$this, 'delete_description_ajax']);
    }

    private function generate_description($product) {
        if (empty($this->api_key)) {
            return false;
        }

        // Get product-specific language or fallback to global
        $product_id = $product->get_id();
        $product_language = get_post_meta($product_id, '_wpcmt_aisays_language', true);

        if (empty($product_language) || 'global' === $product_language) {
            $language = get_option('wpcmt_aisays_language', 'english');
        } else {
            $language = $product_language;
        }

        $product_name = $product->get_name();
        $short_description = $product->get_short_description();
        $tags = wp_get_post_terms($product->get_id(), 'product_tag', ['fields' => 'names']);
        $categories = wp_get_post_terms($product->get_id(), 'product_cat', ['fields' => 'names']);
        $attributes = $product->get_attributes();
        $store_context = get_bloginfo('name');

        // Get the custom prompt template or use default
        $prompt_template = get_option('wpcmt_aisays_prompt_template', '');
        if (empty($prompt_template)) {
            $prompt_template = AdminInterface::get_default_prompt_template_public();
        }

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
                '{tags}',
                '{store_context}',
                '{attributes}',
                '{image_analysis}',
                '{instructions}',
            ],
            [
                $introduction,
                $product_name,
                $short_description ?: __('No short description provided', 'comet-ai-says'),
                !empty($categories) ? implode(', ', $categories) : __('No categories', 'comet-ai-says'),
                !empty($tags) ? implode(', ', $tags) : __('No tags', 'comet-ai-says'),
                $store_context,
                $attributes_string ?: __('No specifications provided', 'comet-ai-says'),
                $image_analysis ?: __('No image analysis available', 'comet-ai-says'),
                $instructions,
            ],
            $prompt_template
        );

        if (Plugin::$debug && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log('Prompt sent: '.$prompt);
        }

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
     * Normalize Gemini model names for API compatibility (Updated March 2026)
     * Support: Latest-flash (permanent), Pro & Flash for newest branch, Flash for wider - older branch, finally slug correction for renamed, misnamed, removed branches - meaning 3.x,2.x,1.x etc.
     * Fallback to $model if no mapping found, to allow for future models without code changes. Ex: gemini-2.0-flash, gemini-2.0-flash-001, gemini-3-flash.
     *
     * @param mixed $model
     */
    private function normalize_gemini_model($model) {
        $mappings = [
            'gemini-flash-latest' => 'gemini-flash-latest',
            'gemini-3.5-flash' => 'gemini-3.5-flash',
            'gemini-3.1-flash-lite' => 'gemini-3.1-flash-lite',
            'gemini-3.1-flash-lite-preview' => 'gemini-3.1-flash-lite',
            'gemini-3.1-flash-preview' => 'gemini-3.5-flash',
            'gemini-3-flash-preview' => 'gemini-3-flash-preview',
            'gemini-3.1-pro' => 'gemini-3.1-pro-preview', // alias correction
            'gemini-2.5-flash' => 'gemini-3.5-flash', // Migration
            'gemini-2.5-pro' => 'gemini-3.1-pro-preview', // Migration
            'gemini-2.5-flash-lite' => 'gemini-3.1-flash-lite', // Migration
            'gemini-2.0-flash' => 'gemini-3.5-flash', // Migration for sunset model
        ];

        return $mappings[$model] ?? $model;
    }

    /**
     * Unified Gemini API call that handles both text and image prompts.
     *
     * Note: Gemini 3.x models are optimized for default settings. Sampling parameters
     * (temperature, top_p, top_k) are no longer recommended and have been removed.
     * Reasoning is controlled by thinking_level (defaults to 'medium' for Gemini 3.5 Flash).
     * See: https://ai.google.dev/gemini-api/docs/whats-new-gemini-3.5
     *
     * @param mixed      $prompt
     * @param null|mixed $product_id
     */
    private function call_gemini_api($prompt, $product_id = null) {
        $raw_model = get_option('wpcmt_aisays_gemini_model', 'gemini-3.5-flash');
        $gemini_model = $this->normalize_gemini_model($raw_model);

        $max_tokens = (int) get_option('wpcmt_aisays_max_tokens', 1500);
        /**
         * The v1beta endpoint is backward‑compatible and supports every model according to current docs. Kept for future reference.
         *
         * $beta_aliases = ['gemini-flash-latest','gemini-3'];
         * $api_version = \in_array($gemini_model, $beta_aliases) ? 'v1beta' : 'v1';
         */
        $api_version = 'v1beta';
        $api_url = "https://generativelanguage.googleapis.com/{$api_version}/models/{$gemini_model}:generateContent?key=" . $this->api_key;
        $parts = [['text' => $prompt]];

        if ($product_id && get_post_thumbnail_id($product_id)) {
            $image_data = $this->prepare_gemini_image_data($product_id);
            if (!empty($image_data['base64'])) {
                $parts[] = [
                    'inline_data' => [
                        'mime_type' => $image_data['mime_type'],
                        'data' => $image_data['base64'],
                    ],
                ];
            }
        }

        $request_body = [
            'contents' => [['parts' => $parts]],
            'generationConfig' => [
                'maxOutputTokens' => $max_tokens,
            ],
        ];

        $response = wp_remote_post($api_url, [
            'headers' => ['Content-Type' => 'application/json'],
            'body' => wp_json_encode($request_body),
            'timeout' => 60,
        ]);

        if (is_wp_error($response)) {
            return 'Network Error: '.$response->get_error_message();
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (200 === $response_code && isset($body['candidates'][0]['content']['parts'])) {
            $output_text = '';
            foreach ($body['candidates'][0]['content']['parts'] as $part) {
                if (isset($part['thought']) && true === $part['thought']) {
                    continue;
                }
                if (isset($part['text'])) {
                    $output_text .= $part['text'];
                }
            }

            return trim($output_text);
        }

        // ==================== IMPROVED ERROR HANDLING ====================
        $error = $body['error'] ?? [];
        $error_code = $error['code'] ?? $response_code;
        $error_message = $error['message'] ?? 'Unknown Gemini API error';
        $status = $error['status'] ?? '';

        if (429 == $error_code || 'RESOURCE_EXHAUSTED' === $status) {
            if (Plugin::$debug && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                error_log('Comet AI Says - Gemini Quota Error | Model: '.$gemini_model.' | '.$error_message);
            }

            if (false !== strpos($gemini_model, '3.') || false !== strpos($gemini_model, 'pro')) {
                $fallback = $this->fallback_gemini_api($prompt);
                if ($fallback) {
                    return $fallback;
                }
            }

            return 'AI Quota Error: '.esc_html__('Gemini API quota exceeded for the selected model. Please try a lighter model (e.g. Gemini 3 Flash) or check your usage at https://ai.dev/rate-limit', 'comet-ai-says');
        }

        return sprintf(
            'AI Error (%d - %s) for model %s: %s',
            $error_code,
            $status,
            $gemini_model,
            esc_html($error_message)
        );
    }

    /**
     * Prepare image data for Gemini API (Bugs Fixed).
     *
     * @param mixed $product_id
     */
    private function prepare_gemini_image_data($product_id) {
        $featured_image_id = get_post_thumbnail_id($product_id);
        if (!$featured_image_id) {
            return false;
        }

        $image_url = wp_get_attachment_image_url($featured_image_id, 'large');
        if (!$image_url) {
            return false;
        }

        $image_response = wp_remote_get($image_url);
        if (is_wp_error($image_response)) {
            return false;
        }

        $raw_image_data = wp_remote_retrieve_body($image_response);
        $content_type = wp_remote_retrieve_header($image_response, 'content-type');
        $mime_type = $content_type ? $content_type : 'image/jpeg';

        $supported_mimes = ['image/png', 'image/jpeg', 'image/webp', 'image/heic', 'image/heif', 'image/gif'];
        $image_data = '';

        if (!in_array($mime_type, $supported_mimes)) {
            $editor = wp_get_image_editor(get_attached_file($featured_image_id));
            if (!is_wp_error($editor)) {
                $temp_file = wp_tempnam('gemini_conv_');
                $editor->set_quality(80);
                $saved = $editor->save($temp_file, 'image/jpeg');

                if (!is_wp_error($saved)) {
                    $image_data = file_get_contents($saved['path']);
                    $mime_type = 'image/jpeg';
                    @unlink($saved['path']);
                    @unlink($temp_file);
                }
            }
        }

        if (empty($image_data)) {
            $image_data = $raw_image_data;
        }

        return [
            'base64' => base64_encode($image_data),
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
        $model = get_option('wpcmt_aisays_openai_model', 'gpt-4o');
        $max_tokens = (int) get_option('wpcmt_aisays_max_tokens', 1500);

        $use_image = $product_id && get_post_thumbnail_id($product_id);
        $image_data = null;

        if ($use_image) {
            $image_data = $this->prepare_gemini_image_data($product_id);

            if (!$image_data) {
                $use_image = false;
                if (Plugin::$debug && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                    error_log('Comet AI Says - OpenAI image preparation failed, falling back to text-only');
                }
            }
        }

        if ($use_image && $image_data) {
            $messages = [
                [
                    'role' => 'user',
                    'content' => [
                        ['type' => 'text', 'text' => $prompt],
                        [
                            'type' => 'image_url',
                            'image_url' => [
                                'url' => 'data:'.$image_data['mime_type'].';base64,'.$image_data['base64'],
                            ],
                        ],
                    ],
                ],
            ];
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
                'max_tokens' => $max_tokens,
                //'temperature' => 0.7,
            ]),
            'timeout' => 30,
        ];

        $response = wp_remote_post($api_url, $args);

        if (is_wp_error($response)) {
            if (Plugin::$debug && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                error_log('Comet AI Says - OpenAI API Error: '.$response->get_error_message());
            }

            return false;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($body['choices'][0]['message']['content'])) {
            return trim($body['choices'][0]['message']['content']);
        }

        if (Plugin::$debug && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log('Comet AI Says - OpenAI API Error Response: '.wp_json_encode($body));
        }

        return false;
    }

    /**
     * Send webhook notification after a successful AI description save.
     *
     * @param mixed $product
     */
    private function trigger_webhook_notification($product, string $description, string $event = 'ai_description_generated'): void
    {
        $webhook_url = get_option('wpcmt_aisays_webhook_url', '');
        if (empty($webhook_url)) {
            return;
        }

        $payload = [
            'event' => $event,
            'product_id' => $product->get_id(),
            'product_name' => $product->get_name(),
            'product_sku' => $product->get_sku(),
            'provider' => $this->provider,
            'model' => $this->get_current_model_name(),
            'language' => get_post_meta($product->get_id(), '_wpcmt_aisays_language', true) ?: get_option('wpcmt_aisays_language', 'english'),
            'description' => $description,
            'generated_at' => gmdate('c'),
            'site_url' => get_site_url(),
            'post_url' => get_edit_post_link($product->get_id(), 'raw'),
        ];

        $response = wp_remote_post($webhook_url, [
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode($payload),
            'timeout' => 10,
        ]);

        if (is_wp_error($response) && Plugin::$debug && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log('Comet AI Says - Webhook Error: '.$response->get_error_message());
        }
    }

    private function fallback_gemini_api($prompt) {
        $api_url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-3.5-flash:generateContent?key='.$this->api_key;
        $max_tokens = (int) get_option('wpcmt_aisays_max_tokens', 1500);

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
                    'maxOutputTokens' => $max_tokens,
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

    /**
     * Get the current model name for display.
     */
    private function get_current_model_name() {
        $provider = get_option('wpcmt_aisays_provider', 'gemini');

        if ('gemini' === $provider) {
            $model = get_option('wpcmt_aisays_gemini_model', 'gemini-3.5-flash');
            $model = str_replace(['gemini-', '-preview'], ['Gemini ', ''], $model);
            $model = str_replace('-', ' ', $model);

            return ucwords($model);
        }
        $model = get_option('wpcmt_aisays_openai_model', 'gpt-4o');
        $model = str_replace('gpt-', 'GPT-', $model);
        $model = str_replace('-', ' ', $model);

        return ucwords($model);
    }

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public static function generate_for_product($product_or_id) {
        $instance = self::get_instance();

        $product = is_a($product_or_id, 'WC_Product') ? $product_or_id : wc_get_product($product_or_id);

        return $product ? $instance->generate_description($product) : false;
    }

    public function generate_description_ajax() {
        if (!check_ajax_referer('wpcmt_aisays_nonce', 'nonce', false)) {
            if (Plugin::$debug && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                error_log('Comet AI Says - Security check failed in generate_description_ajax');
            }
            wp_die(esc_html__('Security check failed', 'comet-ai-says'));
        }

        if (!isset($_POST['product_id'])) {
            wp_send_json_error(esc_html__('Product ID is required', 'comet-ai-says'));
        }

        $product_id = intval($_POST['product_id']);
        $product = wc_get_product($product_id);

        if (!$product) {
            wp_send_json_error(esc_html__('Product not found', 'comet-ai-says'));
        }

        $description = $this->generate_description($product);

        if ($description && false === strpos($description, 'AI Error') && false === strpos($description, 'Network Error')) {
            wp_send_json_success(['description' => $description]);
        } else {
            $error_msg = ($description && (false !== strpos($description, 'AI Error') || false !== strpos($description, 'Network Error'))) ? $description : 'Check your API key and provider settings.';
            // translators: %s error message that was returned from the API
            wp_send_json_error(sprintf(__('Failed to generate description. Error: %s', 'comet-ai-says'), esc_html($error_msg)));
        }
    }

    public function save_description_ajax() {
        if (!check_ajax_referer('wpcmt_aisays_nonce', 'nonce', false)) {
            if (Plugin::$debug && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                error_log('Comet AI Says - Security check failed in save_description_ajax');
            }
            wp_die(esc_html__('Security check failed', 'comet-ai-says'));
        }

        if (!isset($_POST['product_id']) || !isset($_POST['description'])) {
            wp_send_json_error(esc_html__('Missing required fields', 'comet-ai-says'));
        }

        $product_id = intval($_POST['product_id']);
        $description = wp_kses_post(wp_unslash($_POST['description']));

        update_post_meta($product_id, '_wpcmt_aisays_description', $description);

        $product = wc_get_product($product_id);
        if ($product) {
            $this->trigger_webhook_notification($product, $description, 'ai_description_saved');
        }

        wp_send_json_success(esc_html__('Description saved', 'comet-ai-says'));
    }

    public function get_description_ajax() {
        if (!check_ajax_referer('wpcmt_aisays_nonce', 'nonce', false)) {
            if (Plugin::$debug && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                error_log('Comet AI Says - Security check failed in get_description_ajax');
            }
            wp_die(esc_html__('Security check failed', 'comet-ai-says'));
        }

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

    public function delete_description_ajax() {
        if (!check_ajax_referer('wpcmt_aisays_nonce', 'nonce', false)) {
            if (Plugin::$debug && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                error_log('Comet AI Says - Security check failed in delete_description_ajax');
            }
            wp_die(esc_html__('Security check failed', 'comet-ai-says'));
        }

        if (!isset($_POST['product_id'])) {
            wp_send_json_error(esc_html__('Product ID is required', 'comet-ai-says'));
        }

        $product_id = intval($_POST['product_id']);

        $product = wc_get_product($product_id);
        if (!$product) {
            wp_send_json_error(esc_html__('Product not found', 'comet-ai-says'));
        }

        if (delete_post_meta($product_id, '_wpcmt_aisays_description')) {
            wp_send_json_success([
                'product_id' => $product_id,
                'product_name' => $product->get_name(),
                'message' => sprintf(__('AI description deleted for: %s', 'comet-ai-says'), $product->get_name()),
            ]);
        } else {
            // translators: %s is the product name
            wp_send_json_error(sprintf(__('Failed to delete AI description for: %s', 'comet-ai-says'), $product->get_name()));
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
            error_log($log_message);
        }
    }

    public static function generate_single_ajax() {
        if (!check_ajax_referer('wpcmt_aisays_nonce', 'nonce', false)) {
            wp_send_json_error('Security check failed');
        }

        if (!isset($_POST['product_id'])) {
            wp_send_json_error('Product ID is required');
        }

        $product_id = intval($_POST['product_id']);
        $product = wc_get_product($product_id);

        if (!$product) {
            wp_send_json_error('Product not found');
        }

        if (isset($_POST['language']) && !empty($_POST['language'])) {
            $language = sanitize_text_field(wp_unslash($_POST['language']));
            update_post_meta($product_id, '_wpcmt_aisays_language', $language);

            if (Plugin::$debug && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                error_log("Comet AI Says - Saved language for product {$product_id}: {$language}");
            }
        }

        $description = self::generate_for_product($product);

        if ($description && false === strpos($description, 'AI Error') && false === strpos($description, 'Network Error')) {
            update_post_meta($product_id, '_wpcmt_aisays_description', $description);

            $instance = self::get_instance();
            $instance->trigger_webhook_notification($product, $description, 'ai_description_generated');

            $model_name = $instance->get_current_model_name();

            wp_send_json_success([
                'product_id' => $product_id,
                'product_name' => $product->get_name(),
                'description' => $description,
                // translators: %1$s is the product name, %2$s is the AI model name
                'message' => sprintf(__('AI description generated and saved for: %1$s via: %2$s', 'comet-ai-says'), $product->get_name(), $model_name),
            ]);
        } else {
            $error_msg = ($description && (false !== strpos($description, 'AI Error') || false !== strpos($description, 'Network Error'))) ? $description : 'Check your API key and provider settings.';
            // translators: %1$s is the product name, %2$s is the error message
            wp_send_json_error(sprintf(__('Failed to generate description for: %1$s. Error: %2$s', 'comet-ai-says'), $product->get_name(), esc_html($error_msg)));
        }
    }
}
