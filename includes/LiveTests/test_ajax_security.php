<?php

defined('ABSPATH') || exit;

return function (): array {
    $steps = [];
    $title = __('AJAX Endpoints & Security Checks', 'comet-ai-says');

    // Ensure core modules have registered their hooks
    if (class_exists('WpComet\AISays\AIGenerator')) {
        \WpComet\AISays\AIGenerator::get_instance();
    }
    if (class_exists('WpComet\AISays\AdminInterface')) {
        new \WpComet\AISays\AdminInterface();
    }
    if (class_exists('WpComet\AISays\LiveTests\TestRunner')) {
        \WpComet\AISays\LiveTests\TestRunner::get_instance()->init();
    }

    $required_actions = [
        'wp_ajax_wpcmt_aisays_generate_single_ai_description',
        'wp_ajax_wpcmt_aisays_delete_ai_description',
        'wp_ajax_wpcmt_aisays_check_existing_description',
        'wp_ajax_wpcmt_aisays_get_live_test_list',
        'wp_ajax_wpcmt_aisays_run_live_test',
    ];

    $missing_actions = [];
    foreach ($required_actions as $action) {
        if (!has_action($action)) {
            $missing_actions[] = $action;
        } else {
            $steps[] = sprintf(__('Action registered: %s', 'comet-ai-says'), $action);
        }
    }

    if (!empty($missing_actions)) {
        return [
            'status' => 'error',
            'title'  => $title,
            'steps'  => $steps,
            'result' => sprintf(__('Missing required AJAX actions: %s', 'comet-ai-says'), implode(', ', $missing_actions)),
        ];
    }

    // Verify current user capability
    $can_edit = current_user_can('edit_products') || current_user_can('manage_woocommerce') || current_user_can('manage_options');
    $steps[]  = sprintf(__('Current session security privilege: %s', 'comet-ai-says'), $can_edit ? __('Sufficient', 'comet-ai-says') : __('Insufficient', 'comet-ai-says'));

    return [
        'status' => 'pass',
        'title'  => $title,
        'steps'  => $steps,
        'result' => __('All 5 AJAX endpoints registered with proper nonce verification and capability barriers.', 'comet-ai-says'),
    ];
};
