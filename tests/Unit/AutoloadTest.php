<?php

namespace WpComet\AISays\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WpComet\AISays\Autoload;

class AutoloadTest extends TestCase
{
    public function test_autoload_loads_existing_plugin_classes(): void
    {
        $this->assertTrue(class_exists(\WpComet\AISays\Config::class));
        $this->assertTrue(class_exists(\WpComet\AISays\AIGenerator::class));
        $this->assertTrue(class_exists(\WpComet\AISays\FrontendDisplay::class));
        $this->assertTrue(class_exists(\WpComet\AISays\Autoload::class));
    }

    public function test_autoload_ignores_unrelated_namespaces(): void
    {
        // Should not throw or crash on unrelated classes
        Autoload::autoload('SomeOtherPlugin\\NonExistentClass');
        $this->assertFalse(class_exists('SomeOtherPlugin\\NonExistentClass', false));
    }
}
