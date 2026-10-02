<?php

defined('ABSPATH') || exit;

use WpComet\AISays\Config;

return function (): array {
    $steps = [];
    $title = __('Rate Limits & Fallback Cascade Readiness', 'comet-ai-says');

    $provider = Config::get_option(Config::KEY_PROVIDER, 'gemini');

    if ('gemini' !== $provider) {
        $steps[] = __('OpenAI active: Quota and tier status are managed via prepaid account billing.', 'comet-ai-says');
        return [
            'status' => 'pass',
            'title'  => $title,
            'steps'  => $steps,
            'result' => __('OpenAI provider is active. Account balance and token tier are managed on platform.openai.com.', 'comet-ai-says'),
        ];
    }

    $api_key = Config::get_option(Config::KEY_GEMINI_KEY, '');
    if (empty($api_key)) {
        return [
            'status' => 'error',
            'title'  => $title,
            'steps'  => [__('Gemini API key missing.', 'comet-ai-says')],
            'result' => __('Cannot verify rate limits or fallback cascades without an API key.', 'comet-ai-says'),
        ];
    }

    $active_model = Config::normalize_gemini_model(Config::get_option(Config::KEY_GEMINI_MODEL, 'gemini-3.6-flash'));
    $fallbacks    = ['gemini-3.6-flash', 'gemini-3.5-flash', 'gemini-2.5-flash'];
    $models_to_check = array_unique(array_merge([$active_model], $fallbacks));

    $healthy_models = [];
    $exhausted_models = [];

    foreach ($models_to_check as $m) {
        // phpcs:ignore PluginCheck.CodeAnalysis.AIProvider.DirectIntegration
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$m}:generateContent?key=" . $api_key;
        $body = [
            'contents'         => [['parts' => [['text' => 'Health check']]]],
            'generationConfig' => ['maxOutputTokens' => 5],
        ];

        $res = wp_remote_post($url, [
            'headers' => ['Content-Type' => 'application/json'],
            'body'    => wp_json_encode($body),
            'timeout' => 10,
        ]);

        $code = wp_remote_retrieve_response_code($res);
        $data = json_decode(wp_remote_retrieve_body($res), true);

        if (200 === $code) {
            $healthy_models[] = $m;
            /* translators: %s: Model name. */
            $steps[] = sprintf(__('Model %s: Operational (HTTP 200).', 'comet-ai-says'), $m);
        } elseif (429 === $code || 'RESOURCE_EXHAUSTED' === ($data['error']['status'] ?? '')) {
            $exhausted_models[] = $m;
            /* translators: %s: Model name. */
            $steps[] = sprintf(__('Model %s: Free-tier limit reached (HTTP 429 RESOURCE_EXHAUSTED).', 'comet-ai-says'), $m);
        } else {
            $err = $data['error']['message'] ?? "HTTP {$code}";
            /* translators: 1: Model name, 2: HTTP status code, 3: Error message snippet. */
            $steps[] = sprintf(__('Model %1$s: HTTP %2$d (%3$s).', 'comet-ai-says'), $m, $code, substr($err, 0, 50));
        }
    }

    $active_healthy = in_array($active_model, $healthy_models, true);
    $backup_count   = count(array_intersect($fallbacks, $healthy_models));

    if ($active_healthy) {
        return [
            'status' => 'pass',
            'title'  => $title,
            'steps'  => $steps,
            'result' => sprintf(
                /* translators: 1: Primary model name, 2: Number of backup models available. */
                __('Primary model %1$s is healthy. %2$d resilient fallback model(s) operational as automatic safety net.', 'comet-ai-says'),
                $active_model,
                $backup_count
            ),
        ];
    }

    if ($backup_count > 0) {
        return [
            'status' => 'warn',
            'title'  => $title,
            'steps'  => $steps,
            'result' => sprintf(
                /* translators: 1: Primary model name, 2: Number of backup models available, 3: Comma-separated list of backup model names. */
                __('Primary model %1$s has reached temporary quota limits. Automatic fallback cascade is ready with %2$d active backup models (%3$s).', 'comet-ai-says'),
                $active_model,
                $backup_count,
                implode(', ', array_intersect($fallbacks, $healthy_models))
            ),
        ];
    }

    return [
        'status' => 'error',
        'title'  => $title,
        'steps'  => $steps,
        'result' => __('All tested Gemini models have reached their rate limits or are unreachable.', 'comet-ai-says'),
    ];
};
