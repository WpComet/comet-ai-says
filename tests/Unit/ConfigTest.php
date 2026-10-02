<?php

namespace WpComet\AISays\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WpComet\AISays\Config;

class ConfigTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['_wp_mock_options'] = [];
    }

    public function test_normalize_gemini_model_maps_known_aliases(): void
    {
        $this->assertSame('gemini-3.8-flash', Config::normalize_gemini_model('gemini-3.8-flash'));
        $this->assertSame('gemini-3.7-flash', Config::normalize_gemini_model('gemini-3.7-flash'));
        $this->assertSame('gemini-3.6-flash', Config::normalize_gemini_model('gemini-3.6-flash'));
        $this->assertSame('gemini-flash-latest', Config::normalize_gemini_model('gemini-flash-latest'));
        $this->assertSame('gemini-3.1-pro-preview', Config::normalize_gemini_model('gemini-3.1-pro'));
        $this->assertSame('gemini-3.1-pro-preview', Config::normalize_gemini_model('gemini-2.5-pro'));
        $this->assertSame('gemini-3.6-flash', Config::normalize_gemini_model('gemini-2.5-flash'));
        $this->assertSame('gemini-3.6-flash', Config::normalize_gemini_model('gemini-2.0-flash'));
        $this->assertSame('gemini-3.6-flash', Config::normalize_gemini_model('gemini-1.5-flash'));
    }

    public function test_default_options_specifies_gemini_36_flash(): void
    {
        $defaults = Config::get_default_options();
        $this->assertSame('gemini-3.6-flash', $defaults[Config::OPTION_GEMINI_MODEL]);
    }

    public function test_normalize_gemini_model_returns_unmapped_model_intact(): void
    {
        $this->assertSame('custom-gemini-model', Config::normalize_gemini_model('custom-gemini-model'));
        $this->assertSame('gemini-unknown-experimental', Config::normalize_gemini_model('gemini-unknown-experimental'));
    }

    public function test_get_gemini_models_returns_valid_catalog(): void
    {
        $models = Config::get_gemini_models();
        $this->assertIsArray($models);
        $this->assertArrayHasKey('gemini-3.8-flash', $models);
        $this->assertArrayHasKey('gemini-3.6-flash', $models);

        foreach ($models as $id => $data) {
            $this->assertArrayHasKey('name', $data, "Model {$id} missing name");
            $this->assertArrayHasKey('description', $data, "Model {$id} missing description");
            $this->assertArrayHasKey('tier', $data, "Model {$id} missing tier");
            $this->assertNotEmpty($data['name']);
        }
    }

    public function test_get_openai_models_returns_valid_catalog(): void
    {
        $models = Config::get_openai_models();
        $this->assertIsArray($models);
        $this->assertArrayHasKey('gpt-4o', $models);
        $this->assertArrayHasKey('gpt-4o-mini', $models);

        foreach ($models as $id => $data) {
            $this->assertArrayHasKey('name', $data, "Model {$id} missing name");
            $this->assertArrayHasKey('description', $data, "Model {$id} missing description");
            $this->assertArrayHasKey('tier', $data, "Model {$id} missing tier");
            $this->assertNotEmpty($data['name']);
        }
    }

    public function test_get_default_prompt_contains_required_placeholders(): void
    {
        $prompt = Config::get_default_prompt_template();
        $requiredPlaceholders = [
            '{introduction}',
            '{product_name}',
            '{short_description}',
            '{categories}',
            '{tags}',
            '{store_context}',
            '{attributes}',
            '{image_analysis}',
            '{instructions}',
        ];

        foreach ($requiredPlaceholders as $placeholder) {
            $this->assertStringContainsString($placeholder, $prompt, "Default prompt missing {$placeholder}");
        }
    }

    public function test_get_language_part_supports_multi_language_fallbacks(): void
    {
        $languages = ['english', 'german', 'french', 'spanish', 'italian', 'dutch', 'portuguese', 'turkish'];

        foreach ($languages as $lang) {
            $intro = Config::get_language_part($lang, 'intro');
            $instructions = Config::get_language_part($lang, 'instructions');

            $this->assertNotEmpty($intro, "Language {$lang} has empty intro");
            $this->assertNotEmpty($instructions, "Language {$lang} has empty instructions");
        }

        // Custom / unknown language falls back to custom options or English
        $unknownIntro = Config::get_language_part('klingon', 'intro');
        $this->assertNotEmpty($unknownIntro);
    }

    public function test_unified_settings_defaults_and_caching(): void
    {
        Config::clear_cache();
        $GLOBALS['_wp_mock_options'] = [];

        $settings = Config::get_settings();

        $this->assertSame('gemini', $settings[Config::KEY_PROVIDER]);
        $this->assertSame('gemini-3.6-flash', $settings[Config::KEY_GEMINI_MODEL]);
        $this->assertSame('gpt-4o', $settings[Config::KEY_OPENAI_MODEL]);
        $this->assertSame('english', $settings[Config::KEY_LANGUAGE]);
        $this->assertSame(1500, $settings[Config::KEY_MAX_TOKENS]);
    }

    public function test_update_setting_and_get_option(): void
    {
        Config::clear_cache();
        $GLOBALS['_wp_mock_options'] = [];

        Config::update_setting(Config::KEY_OPENAI_KEY, 'sk-test-key-456');
        Config::update_setting(Config::KEY_OPENAI_MODEL, 'gpt-4o-mini');

        $this->assertSame('sk-test-key-456', Config::get_option(Config::KEY_OPENAI_KEY));
        $this->assertSame('gpt-4o-mini', Config::get_option(Config::KEY_OPENAI_MODEL));

        // Stored in single unified option
        $this->assertArrayHasKey(Config::OPTION_SETTINGS, $GLOBALS['_wp_mock_options']);
        $this->assertSame('sk-test-key-456', $GLOBALS['_wp_mock_options'][Config::OPTION_SETTINGS][Config::KEY_OPENAI_KEY]);
    }

    public function test_update_settings_batch(): void
    {
        Config::clear_cache();
        $GLOBALS['_wp_mock_options'] = [];

        Config::update_settings([
            Config::KEY_PROVIDER   => 'gemini',
            Config::KEY_GEMINI_KEY => 'AIzaSyBatchKey',
            Config::KEY_LANGUAGE   => 'spanish',
        ]);

        $this->assertSame('gemini', Config::get_option(Config::KEY_PROVIDER));
        $this->assertSame('AIzaSyBatchKey', Config::get_option(Config::KEY_GEMINI_KEY));
        $this->assertSame('spanish', Config::get_option(Config::KEY_LANGUAGE));
    }

    public function test_transient_constants_are_defined(): void
    {
        $this->assertSame('wpcmt_aisays_onboarding_skipped', Config::TRANSIENT_SKIP_ONBOARDING);
        $this->assertSame('wpcmt_aisays_activation_redirect', Config::TRANSIENT_ACTIVATION_REDIRECT);
    }
}
