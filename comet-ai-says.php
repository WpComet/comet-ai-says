<?php

/**
 * Plugin Name: Comet AI Says: Product Descriptions
 * Description: Generate contextual AI product descriptions on-the-fly and store them in custom fields without messing with your existing descriptions.
 * Version: 1.0.1
 * Author: WpComet
 * Plugin URI: https://wpcomet.com/ai-says/
 * Author URI: https://wpcomet.com/
 * License: GPLv3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: comet-ai-says
 * Domain Path: /i18n/languages/
 * Requires at least: 5.8
 * Requires PHP: 7.4
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

// Define plugin constants with prefix
define('WPCMT_AISAYS_PATH', plugin_dir_path(__FILE__));
define('WPCMT_AISAYS_URL', plugin_dir_url(__FILE__));

// Include required files
require_once WPCMT_AISAYS_PATH.'includes/class-ai-generator.php';
require_once WPCMT_AISAYS_PATH.'includes/class-admin-interface.php';
require_once WPCMT_AISAYS_PATH.'includes/class-frontend-display.php';
require_once WPCMT_AISAYS_PATH.'includes/class-ai-products-table.php';

class Plugin {
    public static $plugin_url;
    public static $plugin_path;
    public static $plugin_version;
    private static $instance;
    public static $debug = false;

    private function __construct() {
        self::$plugin_path = plugin_dir_path(__FILE__);
        self::$plugin_url = plugin_dir_url(__FILE__);
        self::$plugin_version = '1.0.0';

        $this->init_hooks();
    }

    private function get_plugin_version_from_header() {
        if (!function_exists('get_plugin_data')) {
            require_once ABSPATH.'wp-admin/includes/plugin.php';
        }

        $plugin_data = get_plugin_data(__FILE__);

        return $plugin_data['Version'] ?? '1.0.0';
    }

    private function init_hooks() {
        add_action('init', [$this, 'init']);
        add_filter('plugin_action_links_'.plugin_basename(__FILE__), [$this, 'add_plugin_action_links']);
    }

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public static function get_asset_url($path) {
        // Add cache busting, CDN URL processing, etc.
        return self::$plugin_url.'assets/'.ltrim($path, '/').'?v='.self::$plugin_version;
    }

    public function init() {
        // Include required files
        require_once self::$plugin_path.'includes/class-ai-generator.php';
        require_once self::$plugin_path.'includes/class-admin-interface.php';
        require_once self::$plugin_path.'includes/class-frontend-display.php';
        require_once self::$plugin_path.'includes/class-ai-products-table.php';

        // Initialize classes
        new AIGenerator();
        new AdminInterface();
        new FrontendDisplay();
    }

    /**
     * Static activation method.
     */
    public static function activate() {
        require_once self::$plugin_path.'includes/class-admin-interface.php';

        // Set default settings
        $defaults = [
            'wpcmt_aisays_provider' => 'gemini',
            'wpcmt_aisays_gemini_api_key' => '',
            'wpcmt_aisays_openai_api_key' => '',
            'wpcmt_aisays_language' => 'english',
            'wpcmt_aisays_custom_language' => '',
            'wpcmt_aisays_gemini_model' => 'gemini-2.0-flash',
            'wpcmt_aisays_openai_model' => 'gpt-4o',
            'wpcmt_aisays_display_mode' => 'automatic',
            'wpcmt_aisays_display_position' => 'after_description',
            'wpcmt_aisays_shortcode' => '[ai_product_description]',
            'wpcmt_aisays_prompt_template' => AdminInterface::get_default_prompt_template_public(),
            'wpcmt_aisays_max_tokens' => 1500,
        ];

        foreach ($defaults as $option => $value) {
            if (false === get_option($option)) {
                add_option($option, $value, '', false);
            }
        }
    }

    /**
     * Add plugin action links.
     *
     * @param mixed $links
     */
    public function add_plugin_action_links($links) {
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

// Initialize the plugin
Plugin::get_instance();

// Activation hook
register_activation_hook(__FILE__, [__NAMESPACE__.'\\Plugin', 'activate']);
