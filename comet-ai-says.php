<?php

/**
 * Plugin Name: Comet AI Says: Product Descriptions
 * Description: Generate contextual AI product descriptions on-the-fly and store them in custom fields without messing with your existing descriptions.
 * Version: 1.4.0
 * Author: WpComet
 * Plugin URI: https://wpcomet.com/ai-says/
 * Author URI: https://wpcomet.com/
 * License: GPLv3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: comet-ai-says
 * Domain Path: /i18n/languages/
 * Requires at least: 6.0
 * Tested up to: 7.1
 * Requires PHP: 7.4
 * WC requires at least: 6.0
 * WC tested up to: 11.1
 *
 * The plugin bootstrap file
 *
 * @wordpress-plugin
 */

namespace WpComet\AISays;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin version constant
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound
define('COMET_AI_SAYS_VERSION', '1.4.0');

// Require PSR-4 Autoloader
require_once __DIR__ . '/includes/Autoload.php';

// Declare WooCommerce HPOS and Feature compatibility
add_action('before_woocommerce_init', function () {
    if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});

class Plugin
{
    public static $plugin_url;
    public static $plugin_path;
    public static $plugin_version;
    public static $debug = false;
    public static $plugin_pages = [];
    private static $instance;

    private function __construct()
    {
        self::$plugin_path    = plugin_dir_path(__FILE__);
        self::$plugin_url     = plugin_dir_url(__FILE__);
        self::$plugin_version = COMET_AI_SAYS_VERSION;

        $this->init_hooks();
    }

    private function init_hooks(): void
    {
        add_action('init', [$this, 'init']);
        add_action('admin_init', [$this, 'admin_init']);
        add_action('rest_api_init', [$this, 'register_rest_routes']);
        add_action('template_redirect', [$this, 'frontend_init']);
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), [$this, 'add_plugin_action_links']);
    }

    public static function get_instance(): self
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public static function get_asset_url(string $path): string
    {
        return self::$plugin_url . 'assets/' . ltrim($path, '/') . '?v=' . self::$plugin_version;
    }

    /**
     * Check if the current screen is strictly a plugin screen or a product edit screen.
     */
    public static function is_plugin_screen(?string $key = null): bool
    {
        if (!is_admin() || !function_exists('get_current_screen')) {
            return false;
        }

        $screen = get_current_screen();
        if (!$screen) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            return isset($_GET['page']) && in_array($_GET['page'], ['wpcmt-aisays-settings', 'wpcmt-aisays-table'], true);
        }

        if ($key !== null) {
            if (isset(self::$plugin_pages[$key])) {
                return $screen->id === self::$plugin_pages[$key];
            }
            return false;
        }

        if (in_array($screen->id, array_values(self::$plugin_pages), true)) {
            return true;
        }

        if ('extended' === $key && ('product' === $screen->post_type && in_array($screen->base, ['post', 'add'], true))) {
            return true;
        }

        return false;
    }

    public function is_rest_request(): bool
    {
        return defined('REST_REQUEST') && REST_REQUEST;
    }

    public function is_frontend(): bool
    {
        return !is_admin()
            && !wp_doing_ajax()
            && !wp_doing_cron()
            && !$this->is_rest_request();
    }

    public function init(): void
    {
        if (is_admin()) {
            new AdminInterface();
            LiveTests\TestRunner::get_instance()->init();
        }

        // Initialize AIGenerator and TestRunner for AJAX or REST requests
        if (wp_doing_ajax() || $this->is_rest_request()) {
            AIGenerator::get_instance();
            LiveTests\TestRunner::get_instance()->init();
        }
    }

    public function admin_init(): void
    {
        if (wp_doing_ajax()) {
            AIGenerator::get_instance();
        }
    }

    public function frontend_init(): void
    {
        new FrontendDisplay();
    }

    public function register_rest_routes(): void
    {
        register_rest_route('comet-ai-says/v1', '/usage', [
            'methods'             => 'GET',
            'callback'            => [AdminInterface::class, 'rest_get_usage_stats'],
            'permission_callback' => function () {
                return current_user_can('edit_products') || current_user_can('manage_woocommerce') || current_user_can('manage_options');
            },
        ]);
    }

    /**
     * Static activation method.
     */
    public static function activate(): void
    {
        delete_transient(Config::TRANSIENT_SKIP_ONBOARDING);
        set_transient(Config::TRANSIENT_ACTIVATION_REDIRECT, true, 30);

        if (false === get_option(Config::OPTION_SETTINGS)) {
            add_option(Config::OPTION_SETTINGS, Config::get_default_options(), '', true);
        }
    }

    /**
     * Add plugin action links.
     */
    public function add_plugin_action_links(array $links): array
    {
        $action_links = [
            'settings' => sprintf(
                '<a href="%s">%s</a>',
                admin_url('options-general.php?page=wpcmt-aisays-settings'),
                __('Settings', 'comet-ai-says')
            ),
            'ai_descriptions' => sprintf(
                '<a href="%s">%s</a>',
                admin_url('edit.php?post_type=product&page=wpcmt-aisays-table'),
                __('AI Descriptions', 'comet-ai-says')
            ),
        ];

        return array_merge($action_links, $links);
    }
}

/**
 * Ecosystem accessor function.
 */
function WPCMT_AISAYS(): Plugin
{
    return Plugin::get_instance();
}

// Initialize the plugin
Plugin::get_instance();

// Activation hook
register_activation_hook(__FILE__, [__NAMESPACE__ . '\\Plugin', 'activate']);