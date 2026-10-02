<?php

require_once dirname(__DIR__) . '/vendor/autoload.php';

// Define WordPress constants
if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__DIR__) . '/');
}
if (!defined('COMET_AI_SAYS_VERSION')) {
    define('COMET_AI_SAYS_VERSION', '1.4.0');
}
if (!defined('COMET_AISAYS_VERSION')) {
    define('COMET_AISAYS_VERSION', COMET_AI_SAYS_VERSION);
}

// Global in-memory storage for test options & metadata
$GLOBALS['_wp_mock_options']   = [];
$GLOBALS['_wp_mock_post_meta'] = [];

// WordPress mock functions for standalone unit tests
if (!function_exists('__')) {
    function __($text, $domain = 'default') {
        return $text;
    }
}
if (!function_exists('esc_html__')) {
    function esc_html__($text, $domain = 'default') {
        return $text;
    }
}
if (!function_exists('esc_attr__')) {
    function esc_attr__($text, $domain = 'default') {
        return $text;
    }
}
if (!function_exists('esc_html_e')) {
    function esc_html_e($text, $domain = 'default') {
        echo htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
    }
}
if (!function_exists('esc_attr_e')) {
    function esc_attr_e($text, $domain = 'default') {
        echo htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
    }
}
if (!function_exists('esc_html')) {
    function esc_html($text) {
        return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
    }
}
if (!function_exists('esc_attr')) {
    function esc_attr($text) {
        return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
    }
}
if (!function_exists('esc_url')) {
    function esc_url($url) {
        return htmlspecialchars((string) $url, ENT_QUOTES, 'UTF-8');
    }
}
if (!function_exists('esc_url_raw')) {
    function esc_url_raw($url) {
        return (string) $url;
    }
}
if (!function_exists('is_admin')) {
    function is_admin() {
        return true;
    }
}
if (!function_exists('plugin_dir_url')) {
    function plugin_dir_url($file) {
        return 'http://example.com/wp-content/plugins/comet-ai-says/';
    }
}
if (!function_exists('admin_url')) {
    function admin_url($path = '') {
        return 'http://example.com/wp-admin/' . ltrim($path, '/');
    }
}
if (!function_exists('esc_js')) {
    function esc_js($text) {
        return addslashes((string) $text);
    }
}
$GLOBALS['_wp_mock_filters'] = [];

if (!function_exists('add_filter')) {
    function add_filter($tag, $callback, $priority = 10, $accepted_args = 1) {
        $GLOBALS['_wp_mock_filters'][$tag][] = $callback;
        return true;
    }
}
if (!function_exists('remove_all_filters')) {
    function remove_all_filters($tag, $priority = false) {
        unset($GLOBALS['_wp_mock_filters'][$tag]);
        return true;
    }
}
if (!function_exists('apply_filters')) {
    function apply_filters($tag, $value, ...$args) {
        if (!empty($GLOBALS['_wp_mock_filters'][$tag])) {
            foreach ($GLOBALS['_wp_mock_filters'][$tag] as $cb) {
                $value = call_user_func_array($cb, array_merge([$value], $args));
            }
        }
        return $value;
    }
}
if (!function_exists('get_option')) {
    function get_option($option, $default = false) {
        if (function_exists('apply_filters')) {
            $filtered = apply_filters("pre_option_{$option}", false, $option, $default);
            if (false !== $filtered) {
                return $filtered;
            }
        }
        return $GLOBALS['_wp_mock_options'][$option] ?? $default;
    }
}
if (!function_exists('update_option')) {
    function update_option($option, $value, $autoload = null) {
        if (function_exists('apply_filters')) {
            $filtered = apply_filters("pre_update_option_{$option}", $value, $value, $GLOBALS['_wp_mock_options'][$option] ?? false);
            if (false === $filtered) {
                return false;
            }
            $value = $filtered;
        }
        $GLOBALS['_wp_mock_options'][$option] = $value;
        return true;
    }
}
if (!function_exists('delete_option')) {
    function delete_option($option) {
        unset($GLOBALS['_wp_mock_options'][$option]);
        return true;
    }
}
if (!function_exists('get_post_meta')) {
    function get_post_meta($post_id, $key = '', $single = false) {
        $meta = $GLOBALS['_wp_mock_post_meta'][$post_id][$key] ?? null;
        if ($single) {
            return is_array($meta) ? ($meta[0] ?? '') : ($meta ?? '');
        }
        return is_array($meta) ? $meta : ($meta !== null ? [$meta] : []);
    }
}
if (!function_exists('update_post_meta')) {
    function update_post_meta($post_id, $key, $value, $prev_value = '') {
        $GLOBALS['_wp_mock_post_meta'][$post_id][$key] = $value;
        return true;
    }
}
if (!function_exists('delete_post_meta')) {
    function delete_post_meta($post_id, $key, $value = '') {
        unset($GLOBALS['_wp_mock_post_meta'][$post_id][$key]);
        return true;
    }
}
if (!function_exists('wp_strip_all_tags')) {
    function wp_strip_all_tags($string, $remove_breaks = false) {
        return strip_tags((string) $string);
    }
}

// REST API mocks
if (!class_exists('WP_REST_Response')) {
    class WP_REST_Response {
        public $data;
        public $status;
        public function __construct($data = null, $status = 200) {
            $this->data = $data;
            $this->status = $status;
        }
    }
}
if (!class_exists('WP_REST_Server')) {
    class WP_REST_Server {
        const READABLE  = 'GET';
        const CREATABLE = 'POST';
    }
}
if (!function_exists('register_rest_route')) {
    function register_rest_route($namespace, $route, $args = []) {
        return true;
    }
}
if (!function_exists('rest_url')) {
    function rest_url($path = '') {
        return '/wp-json/' . ltrim($path, '/');
    }
}
if (!function_exists('wp_create_nonce')) {
    function wp_create_nonce($action = -1) {
        return 'mock_nonce_' . $action;
    }
}
if (!function_exists('add_action')) {
    function add_action($hook, $callback, $priority = 10, $accepted_args = 1) {
        return true;
    }
}
if (!function_exists('checked')) {
    function checked($checked, $current = true, $echo = true) {
        $result = ((string) $checked === (string) $current) ? " checked='checked'" : '';
        if ($echo) {
            echo $result;
        }
        return $result;
    }
}
if (!function_exists('sanitize_html_class')) {
    function sanitize_html_class($class, $fallback = '') {
        $sanitized = preg_replace('/[^\-_a-zA-Z0-9]/', '', (string) $class);
        return $sanitized ?: $fallback;
    }
}
if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($str) {
        return trim(strip_tags((string) $str));
    }
}
if (!function_exists('wp_unslash')) {
    function wp_unslash($val) {
        return is_string($val) ? stripslashes($val) : $val;
    }
}

// Transient mocks
$GLOBALS['_wp_mock_transients'] = [];

if (!function_exists('get_transient')) {
    function get_transient($transient) {
        return $GLOBALS['_wp_mock_transients'][$transient] ?? false;
    }
}
if (!function_exists('set_transient')) {
    function set_transient($transient, $value, $expiration = 0) {
        $GLOBALS['_wp_mock_transients'][$transient] = $value;
        return true;
    }
}
if (!function_exists('delete_transient')) {
    function delete_transient($transient) {
        unset($GLOBALS['_wp_mock_transients'][$transient]);
        return true;
    }
}
if (!function_exists('settings_fields')) {
    function settings_fields($option_group) {
        echo '<input type="hidden" name="option_page" value="' . esc_attr($option_group) . '" />';
    }
}

if (!function_exists('add_settings_error')) {
    function add_settings_error($setting, $code, $message, $type = 'error') {
        global $wp_settings_errors;
        if (!is_array($wp_settings_errors)) {
            $wp_settings_errors = [];
        }
        $wp_settings_errors[] = [
            'setting' => $setting,
            'code'    => $code,
            'message' => $message,
            'type'    => $type,
        ];
    }
}

if (!function_exists('settings_errors')) {
    function settings_errors($setting = '', $sanitize = false, $hide_on_update = false) {
        global $wp_settings_errors;
        if (empty($wp_settings_errors) || !is_array($wp_settings_errors)) {
            return;
        }
        foreach ($wp_settings_errors as $err) {
            $class = 'error' === ($err['type'] ?? '') ? 'notice-error' : 'notice-success';
            echo '<div class="notice ' . $class . ' settings-error is-dismissible"><p>' . ($err['message'] ?? '') . '</p></div>';
        }
    }
}


