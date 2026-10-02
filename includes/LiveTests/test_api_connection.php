<?php

defined('ABSPATH') || exit;

use WpComet\AISays\Config;

return function (): array {
    $steps = [];
    $title = __('AI Provider Handshake & Latency', 'comet-ai-says');

    $provider = Config::get_option(Config::KEY_PROVIDER, 'gemini');
    /* translators: %s: AI provider name (e.g. GEMINI or OPENAI). */
    $steps[]  = sprintf(__('Checking configured provider: %s', 'comet-ai-says'), strtoupper($provider));

    if ('gemini' === $provider) {
        $api_key = Config::get_option(Config::KEY_GEMINI_KEY, '');
        $raw_model = Config::get_option(Config::KEY_GEMINI_MODEL, 'gemini-3.6-flash');
        $model     = Config::normalize_gemini_model($raw_model);

        if (empty($api_key)) {
            return [
                'status' => 'error',
                'title'  => $title,
                'steps'  => $steps,
                'result' => __('Gemini API key is not configured. Go to Settings to enter your key.', 'comet-ai-says'),
            ];
        }

        /* translators: %s: Model identifier. */
        $steps[] = sprintf(__('Connecting to Google Gemini API (model: %s)...', 'comet-ai-says'), $model);
        // phpcs:ignore PluginCheck.CodeAnalysis.AIProvider.DirectIntegration
        $url     = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key=" . $api_key;
        $body    = [
            'contents'         => [['parts' => [['text' => 'Ping. Respond with Pong.']]]],
            'generationConfig' => ['maxOutputTokens' => 10],
        ];

        $start    = microtime(true);
        $response = wp_remote_post($url, [
            'headers' => ['Content-Type' => 'application/json'],
            'body'    => wp_json_encode($body),
            'timeout' => 15,
        ]);
        $duration = round((microtime(true) - $start) * 1000, 1);

        if (is_wp_error($response)) {
            return [
                'status' => 'error',
                'title'  => $title,
                'steps'  => $steps,
                /* translators: %s: Error message details. */
                'result' => sprintf(__('Connection failed: %s', 'comet-ai-says'), $response->get_error_message()),
            ];
        }

        $code = wp_remote_retrieve_response_code($response);
        $data = json_decode(wp_remote_retrieve_body($response), true);

        if (200 === $code) {
            $reply = trim($data['candidates'][0]['content']['parts'][0]['text'] ?? 'OK');
            /* translators: 1: Response latency in milliseconds, 2: Response text received. */
            $steps[] = sprintf(__('Handshake succeeded in %1$sms. Received: "%2$s"', 'comet-ai-says'), $duration, esc_html($reply));
            return [
                'status' => 'pass',
                'title'  => $title,
                'steps'  => $steps,
                /* translators: 1: Model name, 2: Latency in milliseconds. */
                'result' => sprintf(__('Connected successfully to Gemini %1$s (HTTP 200, %2$sms latency).', 'comet-ai-says'), $model, $duration),
            ];
        }

        if (429 === $code) {
            /* translators: %s: Model name. */
            $steps[] = sprintf(__('Primary model %s hit 429 rate limit. Checking fallback cascade...', 'comet-ai-says'), $model);
            foreach (['gemini-3.5-flash', 'gemini-2.5-flash'] as $fb) {
                // phpcs:ignore PluginCheck.CodeAnalysis.AIProvider.DirectIntegration
                $fb_url = "https://generativelanguage.googleapis.com/v1beta/models/{$fb}:generateContent?key=" . $api_key;
                $fb_res = wp_remote_post($fb_url, [
                    'headers' => ['Content-Type' => 'application/json'],
                    'body'    => wp_json_encode($body),
                    'timeout' => 10,
                ]);
                if (200 === wp_remote_retrieve_response_code($fb_res)) {
                    /* translators: %s: Fallback model name. */
                    $steps[] = sprintf(__('Fallback model %s responded with HTTP 200.', 'comet-ai-says'), $fb);
                    return [
                        'status' => 'warn',
                        'title'  => $title,
                        'steps'  => $steps,
                        /* translators: 1: Fallback model name, 2: Primary model name. */
                        'result' => sprintf(__('Handshake succeeded via fallback %1$s. Primary %2$s has reached temporary 20 RPD free tier limits.', 'comet-ai-says'), $fb, $model),
                    ];
                }
            }
        }

        $err_msg = $data['error']['message'] ?? "HTTP {$code}";
        return [
            'status' => 'error',
            'title'  => $title,
            'steps'  => $steps,
            /* translators: 1: HTTP status code, 2: Error message. */
            'result' => sprintf(__('Gemini returned HTTP %1$d: %2$s', 'comet-ai-says'), $code, esc_html($err_msg)),
        ];
    } else {
        // OpenAI
        $api_key = Config::get_option(Config::KEY_OPENAI_KEY, '');
        $model   = Config::get_option(Config::KEY_OPENAI_MODEL, 'gpt-4o');

        if (empty($api_key)) {
            return [
                'status' => 'error',
                'title'  => $title,
                'steps'  => $steps,
                'result' => __('OpenAI API key is not configured. Go to Settings to enter your key.', 'comet-ai-says'),
            ];
        }

        /* translators: %s: Model identifier. */
        $steps[] = sprintf(__('Connecting to OpenAI API (model: %s)...', 'comet-ai-says'), $model);
        // phpcs:ignore PluginCheck.CodeAnalysis.AIProvider.DirectIntegration
        $url     = 'https://api.openai.com/v1/chat/completions';
        $body    = [
            'model'      => $model,
            'messages'   => [['role' => 'user', 'content' => 'Ping. Respond with Pong.']],
            'max_tokens' => 10,
        ];

        $start    = microtime(true);
        $response = wp_remote_post($url, [
            'headers' => [
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $api_key,
            ],
            'body'    => wp_json_encode($body),
            'timeout' => 15,
        ]);
        $duration = round((microtime(true) - $start) * 1000, 1);

        if (is_wp_error($response)) {
            return [
                'status' => 'error',
                'title'  => $title,
                'steps'  => $steps,
                /* translators: %s: Error message details. */
                'result' => sprintf(__('Connection failed: %s', 'comet-ai-says'), $response->get_error_message()),
            ];
        }

        $code = wp_remote_retrieve_response_code($response);
        $data = json_decode(wp_remote_retrieve_body($response), true);

        if (200 === $code) {
            $reply = trim($data['choices'][0]['message']['content'] ?? 'OK');
            /* translators: 1: Response latency in milliseconds, 2: Response text received. */
            $steps[] = sprintf(__('Handshake succeeded in %1$sms. Received: "%2$s"', 'comet-ai-says'), $duration, esc_html($reply));
            return [
                'status' => 'pass',
                'title'  => $title,
                'steps'  => $steps,
                /* translators: 1: Model name, 2: Latency in milliseconds. */
                'result' => sprintf(__('Connected successfully to OpenAI %1$s (HTTP 200, %2$sms latency).', 'comet-ai-says'), $model, $duration),
            ];
        }

        $err_msg = $data['error']['message'] ?? "HTTP {$code}";
        return [
            'status' => 'error',
            'title'  => $title,
            'steps'  => $steps,
            /* translators: 1: HTTP status code, 2: Error message. */
            'result' => sprintf(__('OpenAI returned HTTP %1$d: %2$s', 'comet-ai-says'), $code, esc_html($err_msg)),
        ];
    }
};
