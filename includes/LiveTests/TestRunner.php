<?php

namespace WpComet\AISays\LiveTests;

defined('ABSPATH') || exit;

/**
 * Diagnostic Live Test Runner for Comet AI Says.
 */
class TestRunner
{
    private static ?self $instance = null;

    public static function get_instance(): self
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function init(): void
    {
        add_action('wp_ajax_wpcmt_aisays_get_live_test_list', [$this, 'ajax_get_test_list']);
        add_action('wp_ajax_wpcmt_aisays_run_live_test', [$this, 'ajax_run_live_test']);
    }

    /**
     * List of all curated live tests.
     */
    public function get_test_list(): array
    {
        return [
            [
                'key'         => 'api_connection',
                'name'        => __('AI Provider Handshake & Latency', 'comet-ai-says'),
                'description' => __('Tests live connectivity, authentication key, and round-trip response time to the active AI provider.', 'comet-ai-says'),
                'icon'        => 'dashicons-cloud',
            ],
            [
                'key'         => 'quota_and_fallbacks',
                'name'        => __('Rate Limits & Fallback Cascade Readiness', 'comet-ai-says'),
                'description' => __('Verifies current model quota and tests whether backup models (Gemini 3.6, 3.5, 2.5) are available.', 'comet-ai-says'),
                'icon'        => 'dashicons-shield',
            ],
            [
                'key'         => 'image_processor',
                'name'        => __('Server Image Downsampling & AVIF Vision', 'comet-ai-says'),
                'description' => __('Validates wp_get_image_editor capabilities and confirms support for AVIF, WebP, JPEG, and PNG image resizing.', 'comet-ai-says'),
                'icon'        => 'dashicons-format-image',
            ],
            [
                'key'         => 'woocommerce_metadata',
                'name'        => __('WooCommerce Product Metadata Storage', 'comet-ai-says'),
                'description' => __('Performs an in-memory postmeta read/write/delete cycle to ensure custom AI description fields work reliably.', 'comet-ai-says'),
                'icon'        => 'dashicons-database',
            ],
            [
                'key'         => 'ajax_security',
                'name'        => __('AJAX Endpoints & Security Checks', 'comet-ai-says'),
                'description' => __('Confirms that generation, regeneration, deletion, and bulk actions enforce nonces and user capabilities.', 'comet-ai-says'),
                'icon'        => 'dashicons-lock',
            ],
        ];
    }

    /**
     * Execute a specific live test by key.
     */
    public function run_test(string $key): array
    {
        $file = __DIR__ . '/test_' . sanitize_key($key) . '.php';

        if (!file_exists($file)) {
            return [
                'key'     => $key,
                'status'  => 'error',
                'title'   => $key,
                'steps'   => [__('Locating test file...', 'comet-ai-says')],
                /* translators: %s: Missing test definition filename. */
                'result'  => sprintf(__('Test definition file missing: %s', 'comet-ai-says'), basename($file)),
                'latency' => 0,
            ];
        }

        $closure = require $file;
        if (!is_callable($closure)) {
            return [
                'key'     => $key,
                'status'  => 'error',
                'title'   => $key,
                'steps'   => [__('Validating test executable closure...', 'comet-ai-says')],
                'result'  => __('Test file did not return a callable closure.', 'comet-ai-says'),
                'latency' => 0,
            ];
        }

        $start = microtime(true);
        try {
            $response = $closure();
            $elapsed  = round((microtime(true) - $start) * 1000, 1);

            $response['key']     = $key;
            $response['latency'] = $elapsed;
            return $response;
        } catch (\Throwable $e) {
            $elapsed = round((microtime(true) - $start) * 1000, 1);
            return [
                'key'     => $key,
                'status'  => 'error',
                'title'   => $key,
                'steps'   => [__('Running test execution...', 'comet-ai-says')],
                /* translators: 1: Exception message, 2: File name, 3: Line number. */
                'result'  => sprintf(__('Uncaught Exception: %1$s (in %2$s:%3$d)', 'comet-ai-says'), $e->getMessage(), basename($e->getFile()), $e->getLine()),
                'latency' => $elapsed,
            ];
        }
    }

    public function ajax_get_test_list(): void
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized access.', 'comet-ai-says')], 403);
        }

        check_ajax_referer('wpcmt_aisays_nonce', 'nonce');
        wp_send_json_success(['tests' => $this->get_test_list()]);
    }

    public function ajax_run_live_test(): void
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized access.', 'comet-ai-says')], 403);
        }

        check_ajax_referer('wpcmt_aisays_nonce', 'nonce');

        $key = sanitize_key($_POST['test_key'] ?? '');
        if (empty($key)) {
            wp_send_json_error(['message' => __('Missing test key parameter.', 'comet-ai-says')], 400);
        }

        $result = $this->run_test($key);
        wp_send_json_success($result);
    }
}
