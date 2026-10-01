<?php

namespace WpComet\AISays;

defined('ABSPATH') || exit;

/**
 * Handles front-end rendering of AI descriptions for WooCommerce products.
 */
class FrontendDisplay
{
    public function __construct()
    {
        $display_mode     = Config::get_option(Config::KEY_DISPLAY_MODE, 'automatic');
        $display_position = Config::get_option(Config::KEY_DISPLAY_POSITION, 'after_description');

        if ('automatic' === $display_mode) {
            $this->register_automatic_hooks($display_position);
        }

        // Always register shortcode so store owners can display AI descriptions anywhere
        $raw_shortcode = Config::get_option(Config::KEY_SHORTCODE, 'comet-ai-says-product-description');
        $tag           = trim($raw_shortcode, '[]');
        if (!empty($tag)) {
            add_shortcode($tag, [$this, 'display_ai_description_shortcode']);
        }
        if ($tag !== 'comet-ai-says-product-description') {
            add_shortcode('comet-ai-says-product-description', [$this, 'display_ai_description_shortcode']);
        }

        add_action('wp_enqueue_scripts', [$this, 'enqueue_styles']);
    }

    /**
     * Register WooCommerce single product display hooks.
     */
    private function register_automatic_hooks(string $position): void
    {
        switch ($position) {
            case 'after_short_description':
                add_action('woocommerce_single_product_summary', [$this, 'display_ai_description'], 60);
                break;
            case 'after_description':
                add_action('woocommerce_after_single_product_summary', [$this, 'display_ai_description'], 5);
                break;
            case 'after_tabs':
                add_action('woocommerce_after_single_product_summary', [$this, 'display_ai_description'], 15);
                break;
            case 'product_bottom':
                add_action('woocommerce_after_single_product', [$this, 'display_ai_description'], 15);
                break;
        }
    }

    /**
     * Get HTML output for the AI description container.
     */
    private function get_ai_description_html(string $content): string
    {
        ob_start();
        ?>
        <div class="wpcmt-aisays-description">
            <h3 class="wpcmt-aisays-title">
                <?php esc_html_e('This is what ✨ AI says about this product', 'comet-ai-says'); ?>
            </h3>
            <div class="wpcmt-aisays-content">
                <?php echo wp_kses_post(wpautop($content)); ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Display AI description via WooCommerce action hook.
     */
    public function display_ai_description(): void
    {
        global $product;
        $wpcmt_product = $product;

        if (!$wpcmt_product instanceof \WC_Product) {
            $wpcmt_product = wc_get_product(get_the_ID());
        }

        if (!$wpcmt_product instanceof \WC_Product) {
            return;
        }

        $ai_description = get_post_meta($wpcmt_product->get_id(), '_wpcmt_aisays_description', true);

        if (empty($ai_description) || !AIGenerator::is_valid_description($ai_description)) {
            return;
        }

        echo $this->get_ai_description_html($ai_description); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    /**
     * Shortcode callback for displaying AI description.
     */
    public function display_ai_description_shortcode($atts = []): string
    {
        global $product;
        $wpcmt_product = $product;

        if (!$wpcmt_product instanceof \WC_Product) {
            $wpcmt_product = wc_get_product(get_the_ID());
        }

        if (!$wpcmt_product instanceof \WC_Product) {
            return '';
        }

        $ai_description = get_post_meta($wpcmt_product->get_id(), '_wpcmt_aisays_description', true);

        if (empty($ai_description) || !AIGenerator::is_valid_description($ai_description)) {
            return '';
        }

        return $this->get_ai_description_html($ai_description);
    }

    /**
     * Enqueue front-end stylesheet.
     */
    public function enqueue_styles(): void
    {
        if (function_exists('is_product') && is_product()) {
            wp_enqueue_style(
                'wpcmt-aisays-frontend',
                Plugin::$plugin_url . 'assets/frontend.css',
                [],
                Plugin::$plugin_version
            );
        }
    }
}
