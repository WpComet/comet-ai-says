<?php

// Find wp-load.php dynamically
$wp_load_candidates = [
    dirname(__DIR__, 4) . '/wp-load.php',
    'D:/wamp64/www/alldemos/wp-load.php',
    'D:/wamp64/www/WPlatest/wp-load.php',
];

$loaded = false;
foreach ($wp_load_candidates as $candidate) {
    if (file_exists($candidate)) {
        require_once $candidate;
        $loaded = true;
        break;
    }
}

if (!$loaded) {
    echo "❌ Error: Could not locate wp-load.php. Please ensure WordPress environment is accessible.\n";
    exit(1);
}

// Log in as administrator for capability checks in tests
if (!is_user_logged_in()) {
    wp_set_current_user(1);
}

require_once __DIR__ . '/../includes/LiveTests/TestRunner.php';

$runner = \WpComet\AISays\LiveTests\TestRunner::get_instance();
$runner->init();
$tests  = $runner->get_test_list();

$passed  = 0;
$warned  = 0;
$failed  = 0;
$skipped = 0;

echo "=========================================================\n";
echo "  Comet AI Says: Live Environment & Diagnostic Tests\n";
echo "=========================================================\n\n";

foreach ($tests as $i => $test) {
    $num = $i + 1;
    echo "[{$num}/" . count($tests) . "] Running: {$test['name']}...\n";

    $result = $runner->run_test($test['key']);
    $status = $result['status'] ?? 'unknown';
    $latency = $result['latency'] ?? 0;

    if (!empty($result['steps'])) {
        foreach ($result['steps'] as $step) {
            echo "    -> {$step}\n";
        }
    }

    if ('pass' === $status) {
        $passed++;
        echo "  \033[32m✔ PASS\033[0m ({$latency}ms): {$result['result']}\n\n";
    } elseif ('warn' === $status) {
        $warned++;
        echo "  \033[33m⚠ WARN\033[0m ({$latency}ms): {$result['result']}\n\n";
    } else {
        $failed++;
        echo "  \033[31m✖ FAIL\033[0m ({$latency}ms): {$result['result']}\n\n";
    }
}

echo "---------------------------------------------------------\n";
echo "Summary: {$passed} Passed, {$warned} Warnings, {$failed} Failed.\n";
echo "---------------------------------------------------------\n";

exit($failed > 0 ? 1 : 0);
