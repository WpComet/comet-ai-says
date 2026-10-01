<?php

namespace WpComet\AISays;

defined('ABSPATH') || exit;

/**
 * PSR-4 Class Autoloader for WpComet\AISays.
 */
class Autoload
{
    /**
     * Registers the autoloader.
     */
    public static function register(): void
    {
        spl_autoload_register([__CLASS__, 'autoload']);
    }

    /**
     * Autoloads classes within the WpComet\AISays namespace.
     *
     * @param string $class Fully qualified class name.
     */
    public static function autoload(string $class): void
    {
        $prefix = 'WpComet\\AISays\\';
        $base_dir = dirname(__FILE__) . '/';

        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            return;
        }

        $relative_class = substr($class, $len);

        // Standard PSR-4 path: includes/ClassName.php or includes/Subdir/ClassName.php
        $psr4_file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
        if (file_exists($psr4_file)) {
            require_once $psr4_file;
            return;
        }

        // Support WordPress legacy file naming conventions
        $parts = explode('\\', $relative_class);
        $class_name = end($parts);
        $dashed = strtolower(preg_replace('/(?<!^)[A-Z]/', '-$0', $class_name));

        $legacy_candidates = [
            $base_dir . 'class-' . $dashed . '.php',
            $base_dir . 'class-ai-' . $dashed . '.php',
        ];

        foreach ($legacy_candidates as $legacy_file) {
            if (file_exists($legacy_file)) {
                require_once $legacy_file;
                return;
            }
        }
    }
}

Autoload::register();
