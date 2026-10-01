<?php

namespace WpComet\AISays\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WpComet\AISays\AdminInterface;
use WpComet\AISays\Config;

class RestoreDefaultsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['_wp_mock_options'] = [];
        $GLOBALS['_wp_mock_transients'] = [];
    }

    public function test_gemini_models_has_unique_36_flash_as_recommended(): void
    {
        $models = Config::get_gemini_models();
        $keys = array_keys($models);

        // Ensure gemini-3.6-flash is present and unique
        $occurrences = array_count_values($keys);
        $this->assertSame(1, $occurrences['gemini-3.6-flash'] ?? 0);

        // First model is gemini-3.6-flash and marked recommended
        $this->assertSame('gemini-3.6-flash', $keys[0]);
        $this->assertTrue($models['gemini-3.6-flash']['recommended'] ?? false);
    }

    public function test_sanitize_gemini_model_protects_against_empty_or_invalid(): void
    {
        $admin = new AdminInterface();

        // Valid model
        $this->assertSame('gemini-3.8-flash', $admin->sanitize_gemini_model('gemini-3.8-flash'));

        // Invalid model falls back to default
        $this->assertSame('gemini-3.6-flash', $admin->sanitize_gemini_model('non-existent-model'));

        // Empty string falls back to default
        $this->assertSame('gemini-3.6-flash', $admin->sanitize_gemini_model(''));
    }

    public function test_sanitize_openai_model_protects_against_empty_or_invalid(): void
    {
        $admin = new AdminInterface();

        // Valid model
        $this->assertSame('gpt-4o-mini', $admin->sanitize_openai_model('gpt-4o-mini'));

        // Invalid model falls back to default
        $this->assertSame('gpt-4o', $admin->sanitize_openai_model('invalid-openai-model'));

        // Empty string falls back to default
        $this->assertSame('gpt-4o', $admin->sanitize_openai_model(''));
    }

    public function test_restore_defaults_preserves_api_keys_and_sets_default_models(): void
    {
        Config::clear_cache();
        $GLOBALS['_wp_mock_options'] = [];

        // Simulate existing configured credentials and custom settings in unified array
        Config::update_settings([
            Config::KEY_GEMINI_KEY   => 'SECRET_GEMINI_KEY_ABC123',
            Config::KEY_OPENAI_KEY   => 'SECRET_OPENAI_KEY_XYZ789',
            Config::KEY_GEMINI_MODEL => 'gemini-3.7-flash',
            Config::KEY_OPENAI_MODEL => 'o1-mini',
            Config::KEY_MAX_TOKENS   => 3500,
        ]);

        // Execute defaults restoration logic
        $defaults = Config::get_default_options();
        $current  = Config::get_settings();
        $defaults[Config::KEY_GEMINI_KEY]   = $current[Config::KEY_GEMINI_KEY] ?? '';
        $defaults[Config::KEY_OPENAI_KEY]   = $current[Config::KEY_OPENAI_KEY] ?? '';
        $defaults[Config::KEY_GEMINI_MODEL] = 'gemini-3.6-flash';
        $defaults[Config::KEY_OPENAI_MODEL] = 'gpt-4o';
        Config::update_settings($defaults);

        // Credentials MUST NOT be wiped
        $this->assertSame('SECRET_GEMINI_KEY_ABC123', Config::get_option(Config::KEY_GEMINI_KEY));
        $this->assertSame('SECRET_OPENAI_KEY_XYZ789', Config::get_option(Config::KEY_OPENAI_KEY));

        // Models MUST be restored to recommended defaults
        $this->assertSame('gemini-3.6-flash', Config::get_option(Config::KEY_GEMINI_MODEL));
        $this->assertSame('gpt-4o', Config::get_option(Config::KEY_OPENAI_MODEL));

        // Other options must be reset
        $this->assertSame(1500, Config::get_option(Config::KEY_MAX_TOKENS));
        $this->assertSame('gemini', Config::get_option(Config::KEY_PROVIDER));
    }

    public function test_render_model_tiles_fallback_when_empty_or_invalid(): void
    {
        $admin = new AdminInterface();
        $ref = new \ReflectionClass($admin);

        // Test Gemini model rendering with empty string
        $geminiMethod = $ref->getMethod('render_gemini_model_select');
        $geminiMethod->setAccessible(true);

        ob_start();
        $geminiMethod->invoke($admin, '');
        $geminiHtml = ob_get_clean();

        $this->assertStringContainsString('is-selected', $geminiHtml, 'A tile must be selected even when empty model passed');
        $this->assertStringContainsString('value="gemini-3.6-flash"', $geminiHtml);
        $this->assertStringContainsString("checked='checked'", $geminiHtml);

        // Test OpenAI model rendering with empty string
        $openaiMethod = $ref->getMethod('render_openai_model_select');
        $openaiMethod->setAccessible(true);

        ob_start();
        $openaiMethod->invoke($admin, '');
        $openaiHtml = ob_get_clean();

        $this->assertStringContainsString('is-selected', $openaiHtml, 'A tile must be selected even when empty model passed');
        $this->assertStringContainsString('value="gpt-4o"', $openaiHtml);
        $this->assertStringContainsString("checked='checked'", $openaiHtml);
    }

    public function test_needs_onboarding_logic(): void
    {
        Config::clear_cache();
        $GLOBALS['_wp_mock_options'] = [];
        $GLOBALS['_wp_mock_transients'] = [];

        $admin = new AdminInterface();
        $ref = new \ReflectionClass($admin);
        $needsMethod = $ref->getMethod('needs_onboarding');
        $needsMethod->setAccessible(true);

        // When no keys and no skip transient -> true
        Config::update_setting(Config::KEY_GEMINI_KEY, '');
        Config::update_setting(Config::KEY_OPENAI_KEY, '');
        delete_transient(Config::TRANSIENT_SKIP_ONBOARDING);
        $this->assertTrue($needsMethod->invoke($admin));

        // When gemini key is set -> false
        Config::update_setting(Config::KEY_GEMINI_KEY, 'AIzaSyTest123');
        $this->assertFalse($needsMethod->invoke($admin));

        // When only openai key is set -> false
        Config::update_setting(Config::KEY_GEMINI_KEY, '');
        Config::update_setting(Config::KEY_OPENAI_KEY, 'sk-test123456');
        $this->assertFalse($needsMethod->invoke($admin));

        // When both empty but skip transient is active -> false
        Config::update_setting(Config::KEY_GEMINI_KEY, '');
        Config::update_setting(Config::KEY_OPENAI_KEY, '');
        set_transient(Config::TRANSIENT_SKIP_ONBOARDING, true, 3600);
        $this->assertFalse($needsMethod->invoke($admin));

        // When ?onboarding=1 is present, forces onboarding -> true
        $_GET['onboarding'] = '1';
        $this->assertTrue($needsMethod->invoke($admin));

        // But when settings-updated is present, it must exit onboarding -> false
        $_GET['settings-updated'] = 'true';
        $this->assertFalse($needsMethod->invoke($admin));

        unset($_GET['onboarding'], $_GET['settings-updated']);
    }

    public function test_render_onboarding_screen_contains_bulma_elements(): void
    {
        $admin = new AdminInterface();
        $ref = new \ReflectionClass($admin);
        $renderMethod = $ref->getMethod('render_onboarding_screen');
        $renderMethod->setAccessible(true);

        ob_start();
        $renderMethod->invoke($admin);
        $html = ob_get_clean();

        // Check key Bulma classes and semantic elements
        $this->assertStringContainsString('comet-onboarding-wrap', $html);
        $this->assertStringContainsString('comet-onboarding-box', $html);
        $this->assertStringContainsString('comet-provider-tile', $html);
        $this->assertStringContainsString('onboarding_provider_gemini', $html);
        $this->assertStringContainsString('onboarding_provider_openai', $html);
        $this->assertStringContainsString('wpcmt_aisays_onboarding_gemini_api_key', $html);
        $this->assertStringContainsString('wpcmt_aisays_onboarding_openai_api_key', $html);
        $this->assertStringContainsString('wpcmt-aisays-skip-onboarding', $html);
        $this->assertStringContainsString('toggle-key-visibility', $html);
    }

    public function test_notice_stashing_and_rendering(): void
    {
        $admin = new AdminInterface();

        // Simulate settings-updated transient error from WordPress core
        set_transient('settings_errors', [
            [
                'setting' => 'general',
                'code' => 'settings_updated',
                'message' => 'Settings saved.',
                'type' => 'updated',
            ]
        ], 30);

        $_GET['page'] = 'wpcmt-aisays-settings';

        // Stash errors
        $admin->stash_settings_errors();
        $this->assertFalse(get_transient('settings_errors'));

        // Render stashed errors
        ob_start();
        $admin->render_settings_errors();
        $output = ob_get_clean();

        $this->assertStringContainsString('Settings saved.', $output);
        $this->assertStringContainsString('notice-success', $output);

        unset($_GET['page']);
    }

    public function test_render_settings_errors_handles_restored_flag(): void
    {
        $admin = new AdminInterface();
        $_GET['restored'] = 'true';

        ob_start();
        $admin->render_settings_errors();
        $output = ob_get_clean();

        $this->assertStringContainsString('Default settings have been restored successfully.', $output);
        $this->assertStringContainsString('notice-success', $output);

        unset($_GET['restored']);
    }

    public function test_display_tab_navigation_includes_wp_header_end(): void
    {
        $admin = new AdminInterface();
        $_GET['page'] = 'wpcmt-aisays-settings';

        $ref = new \ReflectionClass($admin);
        $method = $ref->getMethod('display_tab_navigation');
        $method->setAccessible(true);

        ob_start();
        $method->invoke($admin);
        $html = ob_get_clean();

        $this->assertStringContainsString('class="wp-header-end"', $html);
        $this->assertStringContainsString('comet-aisays-header', $html);
        $this->assertStringContainsString('tabs mb-5', $html);

        unset($_GET['page']);
    }
}


