<?php

namespace WpComet\AISays\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WpComet\AISays\AdminInterface;
use WpComet\AISays\Config;

class ActivationRedirectTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['_wp_mock_transients']       = [];
        $GLOBALS['_wp_mock_options']          = [];
        $GLOBALS['_wp_mock_doing_ajax']       = false;
        $GLOBALS['_wp_mock_is_network_admin'] = false;
        $GLOBALS['_wp_mock_user_can']         = true;
        unset($_GET['activate-multi']);
    }

    public function test_do_activation_redirect_returns_when_no_transient(): void
    {
        $admin = new AdminInterface();

        // Transient is NOT set
        $this->assertFalse(get_transient(Config::TRANSIENT_ACTIVATION_REDIRECT));

        // Calling do_activation_redirect should safely return without error or exit
        $admin->do_activation_redirect();

        $this->assertFalse(get_transient(Config::TRANSIENT_ACTIVATION_REDIRECT));
    }

    public function test_do_activation_redirect_bails_on_ajax(): void
    {
        set_transient(Config::TRANSIENT_ACTIVATION_REDIRECT, true, 30);
        $GLOBALS['_wp_mock_doing_ajax'] = true;

        $admin = new AdminInterface();
        $admin->do_activation_redirect();

        // Transient should have been deleted, and execution returned without redirecting
        $this->assertFalse(get_transient(Config::TRANSIENT_ACTIVATION_REDIRECT));
    }

    public function test_do_activation_redirect_bails_on_network_admin(): void
    {
        set_transient(Config::TRANSIENT_ACTIVATION_REDIRECT, true, 30);
        $GLOBALS['_wp_mock_is_network_admin'] = true;

        $admin = new AdminInterface();
        $admin->do_activation_redirect();

        $this->assertFalse(get_transient(Config::TRANSIENT_ACTIVATION_REDIRECT));
    }

    public function test_do_activation_redirect_bails_on_bulk_activation(): void
    {
        set_transient(Config::TRANSIENT_ACTIVATION_REDIRECT, true, 30);
        $_GET['activate-multi'] = '1';

        $admin = new AdminInterface();
        $admin->do_activation_redirect();

        $this->assertFalse(get_transient(Config::TRANSIENT_ACTIVATION_REDIRECT));
    }

    public function test_do_activation_redirect_bails_when_user_cannot_manage_options(): void
    {
        set_transient(Config::TRANSIENT_ACTIVATION_REDIRECT, true, 30);
        $GLOBALS['_wp_mock_user_can'] = false;

        $admin = new AdminInterface();
        $admin->do_activation_redirect();

        $this->assertFalse(get_transient(Config::TRANSIENT_ACTIVATION_REDIRECT));
    }
}
