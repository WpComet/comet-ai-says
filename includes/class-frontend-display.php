<?php

namespace WpComet\AISays;

class FrontendDisplay {
    
    public function __construct() {
        $display_mode = get_option('wpcmt_aisays_display_mode', 'automatic');
        $display_position = get_option('wpcmt_aisays_display_position', 'after_description');
        
        // Only register hooks if automatic mode is enabled
        if ($display_mode === 'automatic') {
            $this->register_automatic_hooks($display_position);
        }
        
        // Always register shortcode and styles
        add_shortcode('ai_product_description', array($this, 'display_ai_description_shortcode'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_styles'));
    }
    
    private function register_automatic_hooks($position) {
        switch ($position) {
            
            case 'after_short_description':
                add_action('woocommerce_single_product_summary', array($this, 'display_ai_description'), 60);
                break;
            case 'after_description':
                add_action('woocommerce_after_single_product_summary', array($this, 'display_ai_description'), 5);
                break;
                
            case 'after_tabs':
                add_action('woocommerce_after_single_product_summary', array($this, 'display_ai_description'), 15);
                break;
                
            case 'product_bottom':
                add_action('woocommerce_after_single_product', array($this, 'display_ai_description'), 15);
                break;
        }
    }
    
    public function display_ai_description() {
        global $product;
        
        if (!$product) {
            return;
        }
        
        $ai_description = get_post_meta($product->get_id(), '_wpcmt_aisays_description', true);
        
        if (empty($ai_description)) {
            return;
        }
        
        echo $this->get_ai_description_html($ai_description); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }
    
    public function display_ai_description_shortcode($atts) {
        global $product;
        
        if (!$product) {
            return '';
        }
        
        $ai_description = get_post_meta($product->get_id(), '_wpcmt_aisays_description', true);
        
        if (empty($ai_description)) {
            return '';
        }
        
        return $this->get_ai_description_html($ai_description);
    }
    
    private function get_ai_description_html($content) {
        ob_start();
        ?>
        <div class="wpcmt-aisays-description">
            <h2><?php esc_html_e('This is what AI says about this product', 'comet-ai-says'); ?></h2>
            <div class="wpcmt-aisays-content">
                <?php echo wp_kses_post(wpautop($content)); ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    public function enqueue_styles() {
        if (is_product()) {
            wp_enqueue_style(
                'wpcmt-aisays-frontend',
                WPCMT_AISAYS_URL . 'assets/frontend.css',
                array(),
                '1.0.0'
            );
        }
    }
}