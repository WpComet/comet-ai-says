<?php

defined('ABSPATH') || exit;

return function (): array {
    $steps = [];
    $title = __('WooCommerce Product Metadata Storage', 'comet-ai-says');

    // 1. Check WooCommerce installation
    if (!class_exists('WooCommerce')) {
        return [
            'status' => 'error',
            'title'  => $title,
            'steps'  => [__('Checking for WooCommerce...', 'comet-ai-says')],
            'result' => __('WooCommerce plugin is not active. Comet AI Says requires WooCommerce to manage product descriptions.', 'comet-ai-says'),
        ];
    }
    $steps[] = sprintf(__('WooCommerce active (v%s).', 'comet-ai-says'), WC()->version);

    // 2. Query available products
    $product_posts = get_posts([
        'post_type'      => 'product',
        'posts_per_page' => 1,
        'post_status'    => 'any',
        'fields'         => 'ids',
    ]);

    if (empty($product_posts)) {
        return [
            'status' => 'warn',
            'title'  => $title,
            'steps'  => $steps,
            'result' => __('No products found in WooCommerce catalog. Create at least one product to begin generating descriptions.', 'comet-ai-says'),
        ];
    }

    $sample_id = $product_posts[0];
    $steps[]   = sprintf(__('Found catalog product ID %d for storage integrity check.', 'comet-ai-says'), $sample_id);

    // 3. Test non-destructive meta write/read
    $test_key   = '_wpcmt_aisays_storage_test';
    $test_val   = 'comet_test_' . wp_generate_password(8, false);

    update_post_meta($sample_id, $test_key, $test_val);
    $read_back = get_post_meta($sample_id, $test_key, true);
    delete_post_meta($sample_id, $test_key);

    if ($read_back !== $test_val) {
        return [
            'status' => 'error',
            'title'  => $title,
            'steps'  => $steps,
            'result' => __('Failed postmeta read/write cycle. Database may have restricted permissions or custom meta caching issue.', 'comet-ai-says'),
        ];
    }

    $steps[] = __('Metadata read/write/delete cycle passed with 100% integrity.', 'comet-ai-says');

    // 4. Count total catalog stats
    $total_products = (int) wp_count_posts('product')->publish;
    return [
        'status' => 'pass',
        'title'  => $title,
        'steps'  => $steps,
        'result' => sprintf(
            __('WooCommerce integration operational. %d published products in store catalog. Custom field storage verified.', 'comet-ai-says'),
            $total_products
        ),
    ];
};
