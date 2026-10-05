<?php

namespace WpComet\AISays\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WpComet\AISays\AdminInterface;
use WpComet\AISays\Config;

class PromptPresetsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['_wp_mock_options'] = [];
    }

    public function test_get_prompt_presets_returns_expected_catalog(): void
    {
        $presets = Config::get_prompt_presets();

        $this->assertIsArray($presets);
        $this->assertArrayHasKey('default', $presets);
        $this->assertArrayHasKey('luxury_elegant', $presets);
        $this->assertArrayHasKey('short_punchy', $presets);
        $this->assertArrayHasKey('technical_specs', $presets);
        $this->assertArrayHasKey('seo_benefits', $presets);
        $this->assertArrayHasKey('storyteller', $presets);

        foreach ($presets as $id => $data) {
            $this->assertArrayHasKey('name', $data, "Preset {$id} must define name");
            $this->assertArrayHasKey('description', $data, "Preset {$id} must define description");
            $this->assertArrayHasKey('template', $data, "Preset {$id} must define template");

            $this->assertNotEmpty($data['name'], "Preset {$id} name cannot be empty");
            $this->assertNotEmpty($data['description'], "Preset {$id} description cannot be empty");
            $this->assertNotEmpty($data['template'], "Preset {$id} template cannot be empty");

            // Ensure essential prompt placeholders are present in every tone template
            $this->assertStringContainsString('{product_name}', $data['template'], "Preset {$id} must contain {product_name}");
            $this->assertStringContainsString('{instructions}', $data['template'], "Preset {$id} must contain {instructions}");
            $this->assertStringContainsString('{attributes}', $data['template'], "Preset {$id} must contain {attributes}");
            $this->assertStringContainsString('{image_analysis}', $data['template'], "Preset {$id} must contain {image_analysis}");
        }
    }

    public function test_default_preset_matches_get_default_prompt_template(): void
    {
        $presets = Config::get_prompt_presets();
        $this->assertSame(Config::get_default_prompt_template(), $presets['default']['template']);
    }

    public function test_script_localization_includes_prompt_presets_on_settings_screen(): void
    {
        $admin = new AdminInterface();
        $ref = new \ReflectionClass($admin);
        $method = $ref->getMethod('get_script_localization_data');
        $method->setAccessible(true);

        $data = $method->invoke($admin, 'settings');

        $this->assertArrayHasKey('promptPresets', $data);
        $this->assertIsArray($data['promptPresets']);
        $this->assertArrayHasKey('luxury_elegant', $data['promptPresets']);
        $this->assertArrayHasKey('short_punchy', $data['promptPresets']);
        $this->assertArrayHasKey('preset_applied', $data['i18n']);
        $this->assertArrayHasKey('apply_preset', $data['i18n']);
    }
}
