<?php

namespace WpComet\AISays;

defined('ABSPATH') || exit;

/**
 * AI Description Generator supporting Google Gemini and OpenAI.
 */
class AIGenerator
{
    private $provider;
    private $api_key;
    private static $instance = null;

    public function __construct()
    {
        $this->provider = Config::get_option(Config::KEY_PROVIDER, 'gemini');

        if ('gemini' === $this->provider) {
            $this->api_key = Config::get_option(Config::KEY_GEMINI_KEY, '');
        } else {
            $this->api_key = Config::get_option(Config::KEY_OPENAI_KEY, '');
        }

        $this->init_ajax_hooks();
    }

    /**
     * Register AJAX endpoints with security checks.
     */
    private function init_ajax_hooks(): void
    {
        add_action('wp_ajax_wpcmt_aisays_generate_ai_description', [$this, 'generate_description_ajax']);
        add_action('wp_ajax_wpcmt_aisays_save_ai_description', [$this, 'save_description_ajax']);
        add_action('wp_ajax_wpcmt_aisays_get_ai_description', [$this, 'get_description_ajax']);
        add_action('wp_ajax_wpcmt_aisays_delete_ai_description', [$this, 'delete_description_ajax']);
        add_action('wp_ajax_wpcmt_aisays_generate_single_ai_description', [$this, 'generate_single_ajax']);
    }

    public static function get_instance(): self
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Generate description for a product.
     *
     * @param \WC_Product $product
     * @return string|false
     */
    public function generate_description($product)
    {
        if (is_numeric($product)) {
            $product = wc_get_product($product);
        }

        if (!$product instanceof \WC_Product) {
            return 'AI Error: ' . esc_html__('Invalid product.', 'comet-ai-says');
        }

        if (empty($this->api_key)) {
            return 'AI Error: ' . esc_html__('API key is missing. Please configure your API key in Settings.', 'comet-ai-says');
        }

        $product_id = $product->get_id();
        $product_language = get_post_meta($product_id, '_wpcmt_aisays_language', true);

        if (empty($product_language) || 'global' === $product_language) {
            $language = Config::get_option(Config::KEY_LANGUAGE, 'english');
        } else {
            $language = $product_language;
        }

        $product_name      = $product->get_name();
        $short_description = $product->get_short_description();
        $tags              = wp_get_post_terms($product_id, 'product_tag', ['fields' => 'names']);
        $categories        = wp_get_post_terms($product_id, 'product_cat', ['fields' => 'names']);
        $attributes        = $product->get_attributes();
        $store_context     = get_bloginfo('name');

        $prompt_template = Config::get_option(Config::KEY_PROMPT_TEMPLATE, '');
        if (empty($prompt_template)) {
            $prompt_template = Config::get_default_prompt_template();
        }

        // Format attributes
        $attributes_string = '';
        if (!empty($attributes)) {
            foreach ($attributes as $attribute) {
                if (is_a($attribute, 'WC_Product_Attribute')) {
                    if ($attribute->is_taxonomy()) {
                        $terms = wp_get_post_terms($product_id, $attribute->get_name(), ['fields' => 'names']);
                        if (!empty($terms) && !is_wp_error($terms)) {
                            $attributes_string .= '- ' . wc_attribute_label($attribute->get_name()) . ': ' . implode(', ', $terms) . "\n";
                        }
                    } else {
                        $attributes_string .= '- ' . wc_attribute_label($attribute->get_name()) . ': ' . implode(', ', $attribute->get_options()) . "\n";
                    }
                } elseif (is_array($attribute)) {
                    if (!empty($attribute['is_taxonomy'])) {
                        $terms = wp_get_post_terms($product_id, $attribute['name'], ['fields' => 'names']);
                        if (!empty($terms) && !is_wp_error($terms)) {
                            $attributes_string .= '- ' . wc_attribute_label($attribute['name']) . ': ' . implode(', ', $terms) . "\n";
                        }
                    } else {
                        $attributes_string .= '- ' . wc_attribute_label($attribute['name']) . ': ' . ($attribute['value'] ?? '') . "\n";
                    }
                }
            }
        }

        $image_analysis = $this->get_featured_image_analysis($product_id);
        $introduction   = Config::get_language_part($language, 'intro');
        $instructions   = Config::get_language_part($language, 'instructions');

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
                !empty($categories) && !is_wp_error($categories) ? implode(', ', $categories) : __('No categories', 'comet-ai-says'),
                !empty($tags) && !is_wp_error($tags) ? implode(', ', $tags) : __('No tags', 'comet-ai-says'),
                $store_context,
                $attributes_string ?: __('No specifications provided', 'comet-ai-says'),
                $image_analysis ?: __('No image analysis available', 'comet-ai-says'),
                $instructions,
            ],
            $prompt_template
        );

        $this->log_debug('Prompt sent: ' . $prompt);

        switch ($this->provider) {
            case 'openai':
                AdminInterface::track_usage('generation');
                return $this->call_openai_api($prompt, $product_id);

            case 'gemini':
            default:
                AdminInterface::track_usage('generation');
                return $this->call_gemini_api($prompt, $product_id);
        }
    }

    /**
     * Log debug message when debug mode and WP_DEBUG_LOG are active.
     */
    private function log_debug(string $message): void
    {
        if (Plugin::$debug && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log('Comet AI Says - ' . $message);
        }
    }

    /**
     * Static helper for direct invocation.
     */
    public static function generate_for_product($product_or_id)
    {
        $instance = self::get_instance();
        $product  = is_a($product_or_id, 'WC_Product') ? $product_or_id : wc_get_product($product_or_id);

        return $product ? $instance->generate_description($product) : false;
    }

    /**
     * Get image analysis context for prompt.
     */
    private function get_featured_image_analysis(int $product_id): string
    {
        $featured_image_id = get_post_thumbnail_id($product_id);
        if (!$featured_image_id) {
            return __('No featured image available for analysis.', 'comet-ai-says');
        }

        $image_url = wp_get_attachment_image_url($featured_image_id, 'large');
        if (!$image_url) {
            return __('Featured image could not be processed.', 'comet-ai-says');
        }

        $image_alt     = get_post_meta($featured_image_id, '_wp_attachment_image_alt', true);
        $image_caption = wp_get_attachment_caption($featured_image_id);

        $analysis_context = '';
        if ($image_alt) {
            /* translators: %s: Alternative text for the product image. */
            $analysis_context .= sprintf(__('Image alt text: "%s". ', 'comet-ai-says'), $image_alt);
        }
        if ($image_caption) {
            /* translators: %s: Caption for the product image. */
            $analysis_context .= sprintf(__('Image caption: "%s". ', 'comet-ai-says'), $image_caption);
        }

        return $analysis_context . __('The product has a featured image showing the actual item. Incorporate visual cues from the image into the description.', 'comet-ai-says');
    }

    /**
     * Resilient HTTP POST with automatic retry on transient network/DNS blips.
     */
    private function safe_remote_post(string $url, array $args, int $max_retries = 2)
    {
        // Enforce IPv4 on Windows to prevent IPv6 DNS resolution drops (cURL error 6)
        $hook_callback = function (&$handle, $r, $target_url) use ($url) {
            if (is_resource($handle) || (is_object($handle) && $handle instanceof \CurlHandle)) {
                if (defined('CURLOPT_IPRESOLVE') && defined('CURL_IPRESOLVE_V4')) {
                    // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_setopt
                    curl_setopt($handle, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
                }
            }
        };

        add_action('http_api_curl', $hook_callback, 10, 3);

        $attempt  = 0;
        $response = null;

        while ($attempt <= $max_retries) {
            $attempt++;
            $response = wp_remote_post($url, $args);

            if (!is_wp_error($response)) {
                break;
            }

            $error_message = $response->get_error_message();
            $is_transient  = (
                false !== strpos($error_message, 'cURL error 6') ||
                false !== strpos($error_message, 'Could not resolve host') ||
                false !== strpos($error_message, 'cURL error 28') ||
                false !== strpos($error_message, 'timed out') ||
                false !== strpos($error_message, 'Connection reset')
            );

            if ($is_transient && $attempt <= $max_retries) {
                $this->log_debug("Network blip ({$error_message}). Retrying attempt {$attempt}/{$max_retries}...");
                usleep(500000); // 500ms
                continue;
            }

            break;
        }

        remove_action('http_api_curl', $hook_callback, 10);

        return $response;
    }

    /**
     * Execute Gemini API call with multimodal support.
     */
    private function call_gemini_api(string $prompt, ?int $product_id = null)
    {
        $raw_model    = Config::get_option(Config::KEY_GEMINI_MODEL, 'gemini-3.6-flash');
        $gemini_model = Config::normalize_gemini_model($raw_model);
        $max_tokens   = (int) Config::get_option(Config::KEY_MAX_TOKENS, 1500);

        $api_version = 'v1beta';
        // phpcs:ignore PluginCheck.CodeAnalysis.AIProvider.DirectIntegration
        $api_url     = "https://generativelanguage.googleapis.com/{$api_version}/models/{$gemini_model}:generateContent?key=" . $this->api_key;
        $parts       = [['text' => $prompt]];

        if ($product_id && get_post_thumbnail_id($product_id)) {
            $image_data = $this->prepare_image_data($product_id);
            if (!empty($image_data['base64'])) {
                $parts[] = [
                    'inline_data' => [
                        'mime_type' => $image_data['mime_type'],
                        'data'      => $image_data['base64'],
                    ],
                ];
            }
        }

        $request_body = [
            'contents'         => [['parts' => $parts]],
            'generationConfig' => [
                'maxOutputTokens' => $max_tokens,
            ],
        ];

        $response = $this->safe_remote_post($api_url, [
            'headers' => ['Content-Type' => 'application/json'],
            'body'    => wp_json_encode($request_body),
            'timeout' => 60,
        ]);

        if (is_wp_error($response)) {
            return 'Network Error: ' . $response->get_error_message();
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $body          = json_decode(wp_remote_retrieve_body($response), true);

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

            $output_text = trim($output_text);
            if (!empty($output_text)) {
                return $output_text;
            }
        }

        if (isset($body['candidates'][0]['finishReason']) && 'STOP' !== $body['candidates'][0]['finishReason']) {
            return sprintf(
                'AI Error: Generation halted with reason: %s',
                esc_html($body['candidates'][0]['finishReason'])
            );
        }

        // Error handling & quota fallback
        $error         = $body['error'] ?? [];
        $error_code    = $error['code'] ?? $response_code;
        $error_message = $error['message'] ?? 'Unknown Gemini API error';
        $status        = $error['status'] ?? '';

        if (429 == $error_code || 'RESOURCE_EXHAUSTED' === $status || 503 == $error_code || 'UNAVAILABLE' === $status) {
            $this->log_debug("Gemini Busy/Quota Error | Model: {$gemini_model} | Code: {$error_code} | {$error_message}");

            // If selected model is overloaded or quota-restricted, attempt fallback across resilient candidate models
            $candidate_fallbacks = ['gemini-3.6-flash', 'gemini-3.5-flash', 'gemini-2.5-flash'];
            foreach ($candidate_fallbacks as $candidate) {
                if ($candidate === $gemini_model) {
                    continue;
                }
                $fallback = $this->fallback_gemini_api($prompt, $product_id, $candidate);
                if ($fallback) {
                    return $fallback;
                }
            }

            if (503 == $error_code || 'UNAVAILABLE' === $status) {
                return 'AI Service Unavailable: ' . esc_html__('Google AI service is currently experiencing temporary high demand for this model. Please try again in a moment, or switch to Gemini 3.5 Flash or Gemini 2.5 Flash in Settings.', 'comet-ai-says');
            }

            return 'AI Quota Error: ' . esc_html__('Gemini API quota exceeded for the selected model. Try switching to Gemini 3.5 Flash or check your limits at https://ai.google.dev/gemini-api/docs/rate-limits', 'comet-ai-says');
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
     * Fallback to lightweight Gemini Flash when model quota is exceeded or unavailable.
     */
    private function fallback_gemini_api(string $prompt, ?int $product_id = null, string $fallback_model = 'gemini-3.6-flash')
    {
        // phpcs:ignore PluginCheck.CodeAnalysis.AIProvider.DirectIntegration
        $api_url    = "https://generativelanguage.googleapis.com/v1beta/models/{$fallback_model}:generateContent?key=" . $this->api_key;
        $max_tokens = (int) Config::get_option(Config::KEY_MAX_TOKENS, 1500);

        $parts = [['text' => $prompt]];

        if ($product_id && get_post_thumbnail_id($product_id)) {
            $image_data = $this->prepare_image_data($product_id);
            if (!empty($image_data['base64'])) {
                $parts[] = [
                    'inline_data' => [
                        'mime_type' => $image_data['mime_type'],
                        'data'      => $image_data['base64'],
                    ],
                ];
            }
        }

        $request_body = [
            'contents'         => [['parts' => $parts]],
            'generationConfig' => [
                'maxOutputTokens' => $max_tokens,
            ],
        ];

        $response = $this->safe_remote_post($api_url, [
            'headers' => ['Content-Type' => 'application/json'],
            'body'    => wp_json_encode($request_body),
            'timeout' => 45,
        ]);

        if (is_wp_error($response)) {
            return false;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($body['candidates'][0]['content']['parts'])) {
            $output_text = '';
            foreach ($body['candidates'][0]['content']['parts'] as $part) {
                if (isset($part['thought']) && true === $part['thought']) {
                    continue;
                }
                if (isset($part['text'])) {
                    $output_text .= $part['text'];
                }
            }
            $output_text = trim($output_text);
            return !empty($output_text) ? $output_text : false;
        }

        return false;
    }

    /**
     * Execute OpenAI API call with multimodal support.
     */
    private function call_openai_api(string $prompt, ?int $product_id = null)
    {
        // phpcs:ignore PluginCheck.CodeAnalysis.AIProvider.DirectIntegration
        $api_url    = 'https://api.openai.com/v1/chat/completions';
        $model      = Config::get_option(Config::KEY_OPENAI_MODEL, 'gpt-4o');
        $max_tokens = (int) Config::get_option(Config::KEY_MAX_TOKENS, 1500);

        $use_image  = $product_id && get_post_thumbnail_id($product_id);
        $image_data = null;

        if ($use_image) {
            $image_data = $this->prepare_image_data($product_id);
            if (!$image_data) {
                $use_image = false;
            }
        }

        if ($use_image && $image_data) {
            $messages = [
                [
                    'role'    => 'user',
                    'content' => [
                        ['type' => 'text', 'text' => $prompt],
                        [
                            'type'      => 'image_url',
                            'image_url' => [
                                'url' => 'data:' . $image_data['mime_type'] . ';base64,' . $image_data['base64'],
                            ],
                        ],
                    ],
                ],
            ];
        } else {
            $messages = [
                [
                    'role'    => 'user',
                    'content' => $prompt,
                ],
            ];
        }

        $request_data = [
            'model'      => $model,
            'messages'   => $messages,
            'max_tokens' => $max_tokens,
        ];

        $response = $this->safe_remote_post($api_url, [
            'headers' => [
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $this->api_key,
            ],
            'body'    => wp_json_encode($request_data),
            'timeout' => 45,
        ]);

        if (is_wp_error($response)) {
            $this->log_debug('OpenAI API Error: ' . $response->get_error_message());
            return 'Network Error: ' . $response->get_error_message();
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($body['choices'][0]['message']['content'])) {
            $content = trim($body['choices'][0]['message']['content']);
            if (!empty($content)) {
                return $content;
            }
        }

        $error_msg = $body['error']['message'] ?? 'OpenAI API returned an empty or invalid response.';
        return 'AI Error: ' . esc_html($error_msg);
    }

    /**
     * Prepare image base64 data for multimodal API calls.
     */
    private function prepare_image_data(int $product_id)
    {
        $featured_image_id = get_post_thumbnail_id($product_id);
        if (!$featured_image_id) {
            return false;
        }

        $local_path     = get_attached_file($featured_image_id);
        $raw_image_data = null;
        $mime_type      = null;

        // Try reading local file directly first (instant, avoids loopback HTTP calls)
        if ($local_path && file_exists($local_path) && is_readable($local_path)) {
            $raw_image_data = @file_get_contents($local_path);
            $mime_type      = get_post_mime_type($featured_image_id);
            if (empty($mime_type) && function_exists('mime_content_type')) {
                $mime_type = @mime_content_type($local_path);
            }
        }

        // Fallback to HTTP if local file not accessible on disk
        if (empty($raw_image_data)) {
            $image_url = wp_get_attachment_image_url($featured_image_id, 'large');
            if (!$image_url) {
                return false;
            }

            $image_response = wp_remote_get($image_url, ['timeout' => 15]);
            if (is_wp_error($image_response)) {
                return false;
            }

            $raw_image_data = wp_remote_retrieve_body($image_response);
            $content_type   = wp_remote_retrieve_header($image_response, 'content-type');
            $mime_type      = $content_type ?: 'image/jpeg';
        }

        if (empty($raw_image_data)) {
            return false;
        }

        // Clean mime type (strip any parameters like ; charset=...)
        if (false !== strpos((string) $mime_type, ';')) {
            $parts     = explode(';', (string) $mime_type);
            $mime_type = trim($parts[0]);
        }
        $mime_type = strtolower((string) $mime_type);

        $file_ext   = $local_path ? strtolower(pathinfo($local_path, PATHINFO_EXTENSION)) : '';
        $is_avif    = ('avif' === $file_ext || 'image/avif' === $mime_type);
        $needs_opt  = $is_avif || !in_array($mime_type, ['image/png', 'image/jpeg', 'image/webp'], true) || strlen($raw_image_data) > 300 * 1024;
        $image_data = '';

        if ($needs_opt && $local_path && file_exists($local_path)) {
            try {
                $editor = wp_get_image_editor($local_path);
                if (!is_wp_error($editor)) {
                    $editor->resize(800, 800, false);
                    $editor->set_quality(80);
                    $temp_file = wp_tempnam('gemini_thumb_');
                    $saved     = $editor->save($temp_file, 'image/jpeg');

                    if (!is_wp_error($saved) && file_exists($saved['path'])) {
                        $image_data = @file_get_contents($saved['path']);
                        $mime_type  = 'image/jpeg';
                        wp_delete_file($saved['path']);
                        wp_delete_file($temp_file);
                    }
                }
            } catch (\Throwable $e) {
                // If image optimization fails, continue gracefully
            }
        }

        if (empty($image_data)) {
            $image_data = $raw_image_data;
        }

        if (empty($image_data)) {
            return false;
        }

        return [
            'base64'    => base64_encode($image_data),
            'mime_type' => $mime_type ?: 'image/jpeg',
        ];
    }

    /**
     * Trigger webhook notification upon generation/saving.
     */
    private function trigger_webhook_notification($product, string $description, string $event = 'ai_description_generated'): void
    {
        $webhook_url = Config::get_option(Config::KEY_WEBHOOK_URL, '');
        if (empty($webhook_url)) {
            return;
        }

        $payload = [
            'event'        => $event,
            'product_id'   => $product->get_id(),
            'product_name' => $product->get_name(),
            'product_sku'  => $product->get_sku(),
            'provider'     => $this->provider,
            'model'        => $this->get_current_model_name(),
            'language'     => get_post_meta($product->get_id(), '_wpcmt_aisays_language', true) ?: Config::get_option(Config::KEY_LANGUAGE, 'english'),
            'description'  => $description,
            'generated_at' => gmdate('c'),
            'site_url'     => get_site_url(),
            'post_url'     => get_edit_post_link($product->get_id(), 'raw'),
        ];

        wp_remote_post($webhook_url, [
            'headers' => ['Content-Type' => 'application/json'],
            'body'    => wp_json_encode($payload),
            'timeout' => 10,
        ]);
    }

    /**
     * Get human-readable active model name.
     */
    public function get_current_model_name(): string
    {
        $provider = Config::get_option(Config::KEY_PROVIDER, 'gemini');

        if ('gemini' === $provider) {
            $model = Config::get_option(Config::KEY_GEMINI_MODEL, 'gemini-3.6-flash');
            $model = str_replace(['gemini-', '-preview'], ['Gemini ', ''], $model);
            return ucwords(str_replace('-', ' ', $model));
        }

        $model = Config::get_option(Config::KEY_OPENAI_MODEL, 'gpt-4o');
        $model = str_replace('gpt-', 'GPT-', $model);
        return ucwords(str_replace('-', ' ', $model));
    }

    /**
     * Strictly validate that a generated or submitted text is an actual valid description,
     * and NOT an error message, API failure, or empty stub.
     *
     * @param mixed $description
     * @return bool True if valid description, false if error/invalid.
     */
    public static function is_valid_description($description): bool
    {
        if (!is_string($description)) {
            return false;
        }

        $trimmed = trim(wp_strip_all_tags($description));

        // Substantive length check - real product descriptions are paragraphs, not single words or short error alerts
        if (mb_strlen($trimmed) < 25) {
            return false;
        }

        // Check if JSON error payload
        if (substr($trimmed, 0, 1) === '{' && substr($trimmed, -1) === '}') {
            $decoded = json_decode($trimmed, true);
            if (is_array($decoded) && (isset($decoded['error']) || isset($decoded['errors']) || isset($decoded['code']))) {
                return false;
            }
        }

        // Generic error prefix check (e.g. "Error:", "Fatal:", etc.)
        if (preg_match('/^(error|warning|fatal|exception)\s*[:\-]/i', $trimmed)) {
            return false;
        }

        // Case-insensitive check for known error markers
        $lower = strtolower($trimmed);
        $error_signatures = [
            'ai error',
            'network error',
            'ai quota error',
            'quota error',
            'ai service unavailable',
            'service unavailable',
            'gemini api error',
            'openai api error',
            'curl error',
            'could not resolve host',
            'operation timed out',
            'connection timed out',
            'resource_exhausted',
            'rate limit',
            'high demand',
            'spikes in demand',
            'check your api key',
            'invalid api key',
            'api key not valid',
            'api_key_invalid',
            'unauthorized',
            'permission denied',
            'temporarily unavailable',
            'error code:',
            'http error',
            'unknown gemini api error',
            'unknown openai api error',
        ];

        foreach ($error_signatures as $sig) {
            if (false !== strpos($lower, $sig)) {
                return false;
            }
        }

        return true;
    }

    /**
     * AJAX endpoint: Generate description.
     */
    public function generate_description_ajax(): void
    {
        check_ajax_referer('wpcmt_aisays_nonce', 'nonce');

        if (!current_user_can('edit_products')) {
            wp_send_json_error(esc_html__('Permission denied.', 'comet-ai-says'), 403);
        }

        if (empty($_POST['product_id'])) {
            wp_send_json_error(esc_html__('Product ID is required', 'comet-ai-says'));
        }

        $product_id = intval($_POST['product_id']);
        $product    = wc_get_product($product_id);

        if (!$product) {
            wp_send_json_error(esc_html__('Product not found', 'comet-ai-says'));
        }

        $description = $this->generate_description($product);

        if (self::is_valid_description($description)) {
            wp_send_json_success(['description' => $description]);
        } else {
            $error_msg = is_string($description) && !empty($description)
                ? $description
                : __('Check your API key and provider settings.', 'comet-ai-says');
            /* translators: %s: Error message details. */
            wp_send_json_error(sprintf(esc_html__('Failed to generate description: %s', 'comet-ai-says'), esc_html($error_msg)));
        }
    }

    /**
     * AJAX endpoint: Save description.
     */
    public function save_description_ajax(): void
    {
        check_ajax_referer('wpcmt_aisays_nonce', 'nonce');

        if (!current_user_can('edit_products')) {
            wp_send_json_error(esc_html__('Permission denied.', 'comet-ai-says'), 403);
        }

        if (empty($_POST['product_id']) || !isset($_POST['description'])) {
            wp_send_json_error(esc_html__('Missing required fields', 'comet-ai-says'));
        }

        $product_id  = intval($_POST['product_id']);
        $description = wp_kses_post(wp_unslash($_POST['description']));

        if (!self::is_valid_description($description)) {
            wp_send_json_error(esc_html__('Cannot save invalid content or error messages as an AI description.', 'comet-ai-says'));
        }

        update_post_meta($product_id, '_wpcmt_aisays_description', $description);

        $product = wc_get_product($product_id);
        if ($product) {
            $this->trigger_webhook_notification($product, $description, 'ai_description_saved');
        }

        wp_send_json_success(esc_html__('Description saved successfully', 'comet-ai-says'));
    }

    /**
     * AJAX endpoint: Get existing description.
     */
    public function get_description_ajax(): void
    {
        check_ajax_referer('wpcmt_aisays_nonce', 'nonce');

        if (!current_user_can('edit_products')) {
            wp_send_json_error(esc_html__('Permission denied.', 'comet-ai-says'), 403);
        }

        if (empty($_POST['product_id'])) {
            wp_send_json_error(esc_html__('Product ID is required', 'comet-ai-says'));
        }

        $product_id  = intval($_POST['product_id']);
        $description = get_post_meta($product_id, '_wpcmt_aisays_description', true);

        if (self::is_valid_description($description)) {
            wp_send_json_success(['description' => $description]);
        } else {
            wp_send_json_error(esc_html__('No AI description found', 'comet-ai-says'));
        }
    }

    /**
     * AJAX endpoint: Delete description.
     */
    public function delete_description_ajax(): void
    {
        check_ajax_referer('wpcmt_aisays_nonce', 'nonce');

        if (!current_user_can('edit_products')) {
            wp_send_json_error(esc_html__('Permission denied.', 'comet-ai-says'), 403);
        }

        if (empty($_POST['product_id'])) {
            wp_send_json_error(esc_html__('Product ID is required', 'comet-ai-says'));
        }

        $product_id = intval($_POST['product_id']);
        $product    = wc_get_product($product_id);

        if (!$product) {
            wp_send_json_error(esc_html__('Product not found', 'comet-ai-says'));
        }

        delete_post_meta($product_id, '_wpcmt_aisays_description');
        delete_post_meta($product_id, '_wpcmt_aisays_language');

        wp_send_json_success([
            'product_id'   => $product_id,
            'product_name' => $product->get_name(),
            /* translators: %s: Name of the product whose AI description was deleted. */
            'message'      => sprintf(esc_html__('AI description deleted for: %s', 'comet-ai-says'), $product->get_name()),
        ]);
    }

    /**
     * AJAX endpoint: Generate and save single description.
     */
    public static function generate_single_ajax(): void
    {
        check_ajax_referer('wpcmt_aisays_nonce', 'nonce');

        if (!current_user_can('edit_products')) {
            wp_send_json_error(esc_html__('Permission denied.', 'comet-ai-says'), 403);
        }

        if (empty($_POST['product_id'])) {
            wp_send_json_error(esc_html__('Product ID is required', 'comet-ai-says'));
        }

        $product_id = intval($_POST['product_id']);
        $product    = wc_get_product($product_id);

        if (!$product) {
            wp_send_json_error(esc_html__('Product not found', 'comet-ai-says'));
        }

        if (!empty($_POST['language'])) {
            $language = sanitize_text_field(wp_unslash($_POST['language']));
            update_post_meta($product_id, '_wpcmt_aisays_language', $language);
        }

        $description = self::generate_for_product($product);

        if (self::is_valid_description($description)) {
            update_post_meta($product_id, '_wpcmt_aisays_description', $description);

            $instance = self::get_instance();
            $instance->trigger_webhook_notification($product, $description, 'ai_description_generated');
            $model_name = $instance->get_current_model_name();

            wp_send_json_success([
                'product_id'   => $product_id,
                'product_name' => $product->get_name(),
                'description'  => $description,
                /* translators: 1: Name of the product, 2: AI model identifier. */
                'message'      => sprintf(esc_html__('AI description generated and saved for: %1$s via %2$s', 'comet-ai-says'), $product->get_name(), $model_name),
            ]);
        } else {
            $error_msg = is_string($description) && !empty($description)
                ? $description
                : __('Check your API key and provider settings.', 'comet-ai-says');
            /* translators: 1: Name of the product, 2: Error message details. */
            wp_send_json_error(sprintf(esc_html__('Failed to generate description for %1$s: %2$s', 'comet-ai-says'), $product->get_name(), esc_html($error_msg)));
        }
    }
}
