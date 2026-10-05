<?php

namespace WpComet\AISays;

defined('ABSPATH') || exit;

/**
 * Handles WordPress Admin Menu, Settings Page, Meta Boxes, and Onboarding.
 */
class AdminInterface
{
    private static array $stashed_settings_errors = [];

    public function __construct()
    {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('add_meta_boxes', [$this, 'add_meta_box']);
        add_action('in_admin_header', [$this, 'remove_admin_notices'], 99);
        add_action('admin_notices', [$this, 'suppress_core_settings_errors'], 1);
        add_action('all_admin_notices', [$this, 'stash_settings_errors'], 9999);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
        add_action('admin_post_generate_bulk_ai_descriptions', [$this, 'handle_bulk_generation']);
        add_action('admin_init', [$this, 'do_activation_redirect']);
        add_action('admin_init', [$this, 'maybe_restore_defaults']);
        add_action('admin_init', [$this, 'maybe_restart_onboarding']);
        add_action('wp_ajax_wpcmt_aisays_check_existing_description', [$this, 'check_existing_description_callback']);
        add_action('wp_ajax_wpcmt_aisays_generate_single_ai_description', [$this, 'generate_single_ai_description_callback']);
        add_action('wp_ajax_wpcmt_aisays_skip_onboarding', [$this, 'skip_onboarding_callback']);
        add_action('save_post_product', [$this, 'save_product_language']);
    }

    private function get_asset_url(string $path): string
    {
        return plugin_dir_url(__FILE__).'../assets/'.ltrim($path, '/');
    }

    private function get_plugin_path(string $path = ''): string
    {
        return plugin_dir_path(__FILE__).'../'.ltrim($path, '/');
    }

    private function get_plugin_version(): string
    {
        return Plugin::$plugin_version ?? '1.3.0';
    }

    private function is_product_screen($hook): bool
    {
        return ('post.php' === $hook || 'post-new.php' === $hook) && 'product' === get_post_type();
    }

    private function permission_check(): bool
    {
        return current_user_can('edit_products') || current_user_can('manage_woocommerce');
    }

    private function enqueue_admin_settings_js(): void
    {
        wp_register_script(
            'wpcmt-aisays-admin-settings',
            $this->get_asset_url('admin-plugin-settings.js'),
            ['jquery'],
            $this->get_plugin_version(),
            true
        );

        wp_localize_script('wpcmt-aisays-admin-settings', 'wpcmt_aisays', $this->get_script_localization_data('settings'));
        wp_enqueue_script('wpcmt-aisays-admin-settings');
    }

    private function enqueue_plugin_admin_scripts(): void
    {
        wp_register_script(
            'wpcmt-aisays-admin',
            $this->get_asset_url('admin-shared.js'),
            ['jquery'],
            $this->get_plugin_version(),
            true
        );

        wp_localize_script('wpcmt-aisays-admin', 'wpcmt_aisays', $this->get_script_localization_data('general'));
        wp_enqueue_script('wpcmt-aisays-admin');
    }

    private function enqueue_plugin_admin_styles(): void
    {
        $version = $this->get_plugin_version();

        wp_enqueue_style(
            'wpcmt-aisays-bulma',
            $this->get_asset_url('admin-bulma.css'),
            [],
            $version
        );

        wp_enqueue_style(
            'wpcmt-aisays-overrides',
            $this->get_asset_url('admin-overrides.css'),
            ['wpcmt-aisays-bulma'],
            $version
        );

        wp_enqueue_style(
            'wpcmt-aisays-admin',
            $this->get_asset_url('plugin-admin.css'),
            ['wpcmt-aisays-overrides'],
            $version
        );
    }

    private function get_script_localization_data($screen = null): array
    {
        $common = [
            'generate_error' => esc_html__('Error: ', 'comet-ai-says'),
            'generate_error_generic' => esc_html__('An error occurred while generating the description.', 'comet-ai-says'),
            'saving' => esc_html__('Saving...', 'comet-ai-says'),
            'saved' => esc_html__('Saved!', 'comet-ai-says'),
            'save_error' => esc_html__('Error saving', 'comet-ai-says'),
            'generating' => esc_html__('Generating...', 'comet-ai-says'),
            'generate_ai_description' => esc_html__('Generate AI Description', 'comet-ai-says'),
            /* translators: %s: Product title or name. */
            'save_error_specific' => esc_html__('Error saving description for: %s', 'comet-ai-says'),
            /* translators: %s: Product title or name. */
            'generate_error_specific' => esc_html__('Error generating description for: %s', 'comet-ai-says'),
            /* translators: %s: Product title or name. */
            'generate_error_generic_specific' => esc_html__('An error occurred while generating description for: %s', 'comet-ai-says'),
            'view_error' => esc_html__('Error loading AI description', 'comet-ai-says'),
            'regenerate' => esc_html__('Regenerate', 'comet-ai-says'),
            'delete_ai_description' => esc_html__('Delete AI desc', 'comet-ai-says'),
            /* translators: %s: Product title or name. */
            'delete_confirm' => esc_html__('Are you sure you want to delete the AI description for "%s"?', 'comet-ai-says'),
            'deleting' => esc_html__('Deleting...', 'comet-ai-says'),
            /* translators: %s: Name of the product whose AI description was deleted. */
            'deleted_success' => esc_html__('AI description deleted for: %s', 'comet-ai-says'),
            'delete_error' => esc_html__('Error deleting AI description: ', 'comet-ai-says'),
            /* translators: %s: Product title or name. */
            'delete_error_generic' => esc_html__('Error deleting AI description for: %s', 'comet-ai-says'),
        ];

        $screen_strings = [];

        if ('settings' === $screen) {
            $screen_strings = [
                'show' => esc_html__('Show', 'comet-ai-says'),
                'hide' => esc_html__('Hide', 'comet-ai-says'),
                'preset_applied' => esc_html__('Applied!', 'comet-ai-says'),
                'apply_preset' => esc_html__('Apply Preset', 'comet-ai-says'),
                'custom_preset' => esc_html__('-- Custom / Choose a Tone Preset --', 'comet-ai-says'),
                'preset_hint_default' => esc_html__('Choose from ready-to-use tone archetypes or customize manually.', 'comet-ai-says'),
                'tokens' => esc_html__('tokens', 'comet-ai-says'),
                'tokens_4000_10000' => esc_html__('4000-10000 tokens for complex analysis', 'comet-ai-says'),
                'tokens_1500_5000' => esc_html__('1500-5000 tokens for detailed descriptions', 'comet-ai-says'),
                'tokens_800_2500' => esc_html__('800-2500 tokens for efficient descriptions', 'comet-ai-says'),
                'tokens_1000_4000' => esc_html__('1000-4000 tokens for balanced performance', 'comet-ai-says'),
                'tokens_800_2000' => esc_html__('800-2000 tokens for lightweight tasks', 'comet-ai-says'),
                'tokens_1500_4000' => esc_html__('1500-4000 tokens for preview testing', 'comet-ai-says'),
                'tokens_3000_8000' => esc_html__('3000-8000 tokens for pro preview', 'comet-ai-says'),
                'tokens_1000_5000' => esc_html__('1000-5000 tokens for comprehensive descriptions', 'comet-ai-says'),
                'cap_125k_5' => esc_html__('125K TPM, 5 RPM', 'comet-ai-says'),
                'cap_250k_10' => esc_html__('250K TPM, 10 RPM', 'comet-ai-says'),
                'cap_250k_15' => esc_html__('250K TPM, 15 RPM', 'comet-ai-says'),
                'cap_1m_15' => esc_html__('1M TPM, 15 RPM', 'comet-ai-says'),
                'cap_1m_30' => esc_html__('1M TPM, 30 RPM', 'comet-ai-says'),
                'cap_limited_free' => esc_html__('Limited Free Tier', 'comet-ai-says'),
                'cap_15k_30' => esc_html__('15K TPM, 30 RPM', 'comet-ai-says'),
                'cap_standard' => esc_html__('Standard configuration', 'comet-ai-says'),
                'cap_gemini_25' => esc_html__('Generous daily token allowance with Gemini 3.5 Flash', 'comet-ai-says'),
                'cap_gemini_20' => esc_html__('Up to 1,000,000 TPM with Gemini Flash models', 'comet-ai-says'),
                'tokens_2000_6000' => esc_html__('2000-6000 tokens for complex reasoning', 'comet-ai-says'),
                'tokens_2000_8000' => esc_html__('2000-8000 tokens for high-quality analysis', 'comet-ai-says'),
                'cap_30k_2' => esc_html__('30K TPM, 2 RPM', 'comet-ai-says'),
                'cap_32k_2' => esc_html__('32K TPM, 2 RPM', 'comet-ai-says'),
            ];

            $language_data = [
                'intro' => [],
                'instructions' => [],
            ];

            $languages = array_keys(Config::LANGUAGE_DATA);
            $languages[] = 'custom';

            foreach ($languages as $language) {
                $language_data['intro'][$language] = Config::get_language_part($language, 'intro');
                $language_data['instructions'][$language] = Config::get_language_part($language, 'instructions');
            }
        } elseif ('general' === $screen || 'product-descriptions' === $screen || 'product-edit' === $screen) {
            $screen_strings = [
                'no_products_selected' => esc_html__('Please select at least one product.', 'comet-ai-says'),
                /* translators: %d: Number of selected products. */
                'bulk_confirm' => esc_html__('Generate AI descriptions for %d selected products?', 'comet-ai-says'),
                'completed' => esc_html__('Completed!', 'comet-ai-says'),
                /* translators: %d: Number of products generated. */
                'generated_count' => esc_html__('Generated descriptions for %d products.', 'comet-ai-says'),
                'already_has_description' => esc_html__('This product already has an AI description.', 'comet-ai-says'),
                'replace_existing' => esc_html__('Replace Existing', 'comet-ai-says'),
                'discard_new' => esc_html__('Discard New', 'comet-ai-says'),
                'view_existing' => esc_html__('View AI desc', 'comet-ai-says'),
                'new_description' => esc_html__('New AI desc', 'comet-ai-says'),
                'close' => esc_html__('Close', 'comet-ai-says'),
                'bulk_generating' => esc_html__('Bulk generating descriptions...', 'comet-ai-says'),
                'bulk_complete' => esc_html__('Bulk generation complete!', 'comet-ai-says'),
                'bulk_error' => esc_html__('Error during bulk generation', 'comet-ai-says'),
                /* translators: %d: Number of selected products. */
                'bulk_delete_confirm' => esc_html__('Are you sure you want to delete AI descriptions for %d selected products?', 'comet-ai-says'),
                /* translators: %d: Number of deleted product descriptions. */
                'deleted_count' => esc_html__('Successfully deleted AI descriptions for %d products.', 'comet-ai-says'),
                /* translators: %s: Number or error details for failed deletions. */
                'delete_error_specific' => esc_html__('Failed to delete %s AI descriptions.', 'comet-ai-says'),
            ];
        }

        $return_data = [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'rest_url' => esc_url_raw(rest_url('comet-ai-says/v1/usage')),
            'rest_nonce' => wp_create_nonce('wp_rest'),
            'nonce' => wp_create_nonce('wpcmt_aisays_nonce'),
            'bulk_nonce' => wp_create_nonce('wpcmt_aisays_bulk_nonce'),
            'skip_onboarding_nonce' => wp_create_nonce('wpcmt_aisays_skip_onboarding'),
            'i18n' => array_merge($common, $screen_strings),
        ];

        if ('settings' === $screen && isset($language_data)) {
            $return_data['languageData'] = $language_data;
            $return_data['promptPresets'] = Config::get_prompt_presets();
        }

        return $return_data;
    }

    private function display_tab_navigation(): void
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $current_page = sanitize_text_field(wp_unslash($_GET['page'] ?? ''));
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $current_tab = sanitize_text_field(wp_unslash($_GET['tab'] ?? ''));
        $settings_url = admin_url('options-general.php?page=wpcmt-aisays-settings');
        $products_url = admin_url('edit.php?post_type=product&page=wpcmt-aisays-table');
        $status_url = admin_url('options-general.php?page=wpcmt-aisays-settings&tab=status');
        ?>
<div class="comet-aisays-header mb-5">
    <div class="p-5 is-flex is-justify-content-space-between is-align-items-center">
        <div class="is-flex is-align-items-center">
            <img src="<?php echo esc_url($this->get_asset_url('solo-color.svg')); ?>"
                width="38" height="38" alt="WpComet" class="mr-3" />
            <div>
                <h1 class="title is-4 mb-0 has-text-weight-bold is-flex is-align-items-center">
                    <?php esc_html_e('Comet AI Says: Product Descriptions', 'comet-ai-says'); ?>
                    <span
                        class="tag is-primary is-light ml-3">v<?php echo esc_html(COMET_AI_SAYS_VERSION); ?></span>
                    <span id="wpcmt-aisays-bulk-loading"
                        style="display: none; margin-left: 10px; font-size: 13px; font-weight: normal;">
                        <span class="spinner is-active" style="float: none; margin-top: 0;"></span>
                        <?php esc_html_e('Generating descriptions...', 'comet-ai-says'); ?>
                    </span>
                </h1>
                <p class="subtitle is-6 has-text-grey mb-0">
                    <?php esc_html_e('Contextual WooCommerce AI descriptions', 'comet-ai-says'); ?>
                </p>
            </div>
        </div>
        <div class="is-flex is-align-items-center">
            <button type="button" id="comet-theme-toggle" class="comet-theme-toggle mr-3"
                title="<?php esc_attr_e('Toggle Light/Dark Theme', 'comet-ai-says'); ?>">
                <span class="theme-icon">🌙</span>
            </button>
            <button type="button" id="comet-usage-toggle-btn" class="button is-small is-outlined is-primary"
                aria-expanded="false" style="white-space: nowrap; display: inline-flex; align-items: center;"
                title="<?php esc_attr_e('Toggle API Usage & Rate Limits', 'comet-ai-says'); ?>">
                <span class="dashicons dashicons-chart-bar"
                    style="font-size: 15px; width: 15px; height: 15px; line-height: 15px; margin-right: 5px;"></span>
                <span
                    style="font-weight: 600; line-height: 1;"><?php esc_html_e('Usage', 'comet-ai-says'); ?></span>
                <span class="dashicons dashicons-arrow-down-alt2 comet-usage-chevron"
                    style="font-size: 13px; width: 13px; height: 13px; line-height: 13px; margin-left: 5px; transition: transform 0.2s ease;"></span>
            </button>
        </div>
    </div>

    <!-- Collapsible Usage Drawer (closed by default, loaded via REST API on show with Bulma skeleton) -->
    <div id="comet-aisays-usage-drawer" class="p-5" style="display: none;">
        <!-- Bulma Skeleton (animated placeholder on load) -->
        <div id="comet-usage-skeleton" style="display: none;">
            <div class="level mb-4 is-mobile">
                <div class="level-left">
                    <div class="skeleton-block" style="width: 220px; height: 26px; border-radius: 4px;"></div>
                    <div class="skeleton-block ml-3" style="width: 100px; height: 24px; border-radius: 12px;"></div>
                </div>
                <div class="level-right">
                    <div class="skeleton-block" style="width: 130px; height: 24px; border-radius: 12px;"></div>
                </div>
            </div>
            <div class="columns is-variable is-4">
                <div class="column is-8">
                    <div class="skeleton-lines" style="gap: 1.25rem;">
                        <div style="height: 38px; width: 100%;"></div>
                        <div style="height: 38px; width: 100%;"></div>
                        <div style="height: 38px; width: 100%;"></div>
                    </div>
                </div>
                <div class="column is-4">
                    <div class="skeleton-block" style="height: 150px; width: 100%; border-radius: 6px;"></div>
                </div>
            </div>
        </div>

        <!-- Dynamic Content populated via REST API -->
        <div id="comet-usage-content" style="display: none;"></div>
    </div>
</div>

<hr class="wp-header-end" style="display:none;">

<?php $this->render_settings_errors(); ?>

<div class="tabs mb-5">
    <ul>
        <li
            class="<?php echo ('wpcmt-aisays-settings' === $current_page && 'status' !== $current_tab) ? 'is-active' : ''; ?>">
            <a href="<?php echo esc_url($settings_url); ?>">
                <span class="dashicons dashicons-admin-settings mr-2"></span>
                <?php esc_html_e('Settings', 'comet-ai-says'); ?>
            </a>
        </li>
        <li
            class="<?php echo ('wpcmt-aisays-table' === $current_page && 'status' !== $current_tab) ? 'is-active' : ''; ?>">
            <a href="<?php echo esc_url($products_url); ?>">
                <span class="dashicons dashicons-products mr-2"></span>
                <?php esc_html_e('Product Descriptions', 'comet-ai-says'); ?>
            </a>
        </li>
        <li
            class="<?php echo ('status' === $current_tab) ? 'is-active' : ''; ?>">
            <a href="<?php echo esc_url($status_url); ?>">
                <span class="dashicons dashicons-heart mr-2"></span>
                <?php esc_html_e('Status & Diagnostics', 'comet-ai-says'); ?>
            </a>
        </li>
    </ul>
</div>

<script>
    (function() {
        var toggleBtn = document.getElementById('comet-usage-toggle-btn');
        var drawer = document.getElementById('comet-aisays-usage-drawer');
        var skeleton = document.getElementById('comet-usage-skeleton');
        var content = document.getElementById('comet-usage-content');
        if (!toggleBtn || !drawer) return;

        var isLoaded = false;
        var isLoading = false;

        function fetchUsageStats(force) {
            if (isLoading) return;
            if (isLoaded && !force) return;

            isLoading = true;
            skeleton.style.display = 'block';
            content.style.display = 'none';

            var restUrl = (typeof wpcmt_aisays !== 'undefined' && wpcmt_aisays.rest_url) ?
                wpcmt_aisays.rest_url :
                '<?php echo esc_url_raw(rest_url('comet-ai-says/v1/usage')); ?>';

            var restNonce = (typeof wpcmt_aisays !== 'undefined' && wpcmt_aisays.rest_nonce) ?
                wpcmt_aisays.rest_nonce :
                ((typeof wpApiSettings !== 'undefined' && wpApiSettings.nonce) ? wpApiSettings.nonce :
                    '<?php echo esc_js(wp_create_nonce('wp_rest')); ?>'
                );

            fetch(restUrl, {
                    method: 'GET',
                    headers: {
                        'X-WP-Nonce': restNonce,
                        'Content-Type': 'application/json'
                    }
                })
                .then(function(r) {
                    return r.json();
                })
                .then(function(data) {
                    isLoading = false;
                    isLoaded = true;
                    renderUsage(data);
                    skeleton.style.display = 'none';
                    content.style.display = 'block';
                })
                .catch(function(err) {
                    isLoading = false;
                    skeleton.style.display = 'none';
                    content.style.display = 'block';
                    content.innerHTML =
                        '<div class="notification is-danger is-light p-3 is-flex is-justify-content-space-between is-align-items-center">' +
                        '<span><strong><?php echo esc_js(__('Error loading usage statistics:', 'comet-ai-says')); ?></strong> ' +
                        (err.message || 'Network error') + '</span>' +
                        '<button type="button" class="button is-small is-danger" id="comet-usage-retry-btn"><?php echo esc_js(__('Retry', 'comet-ai-says')); ?></button>' +
                        '</div>';
                    var retryBtn = document.getElementById('comet-usage-retry-btn');
                    if (retryBtn) {
                        retryBtn.addEventListener('click', function() {
                            fetchUsageStats(true);
                        });
                    }
                });
        }

        function renderUsage(data) {
            var metricsHtml = '';
            if (data.metrics && data.metrics.length) {
                data.metrics.forEach(function(m) {
                    metricsHtml += '<tr>' +
                        '<td><strong>' + m.label +
                        '</strong> <span class="is-size-7 has-text-grey ml-1">(' + m.reset_text +
                        ')</span></td>' +
                        '<td>' + m.used + '</td>' +
                        '<td>' + m.limit + '</td>' +
                        '<td>' +
                        '<div class="is-flex is-align-items-center">' +
                        '<progress class="progress is-small ' + m.class + ' mb-0 mr-2" value="' + m
                        .percent + '" max="100"></progress>' +
                        '<span class="is-size-7 has-text-weight-semibold" style="min-width: 42px;">' + m
                        .percent + '%</span>' +
                        '</div>' +
                        '</td>' +
                        '</tr>';
                });
            }

            var html = '<div class="level mb-4 is-mobile">' +
                '<div class="level-left">' +
                '<div class="level-item">' +
                '<span class="has-text-weight-bold is-size-5 is-flex is-align-items-center">' +
                '<span class="dashicons dashicons-chart-bar mr-2 has-text-primary"></span>' +
                '<?php echo esc_js(__('API Usage & Rate Limits', 'comet-ai-says')); ?>' +
                '</span>' +
                '</div>' +
                '<div class="level-item">' +
                '<span class="tag is-primary is-light">' + (data.model || '') + '</span>' +
                '</div>' +
                '</div>' +
                '<div class="level-right">' +
                '<div class="level-item mr-2">' +
                '<button type="button" id="comet-usage-refresh-btn" class="button is-small is-light" title="<?php echo esc_js(__('Refresh Stats', 'comet-ai-says')); ?>">' +
                '<span class="dashicons dashicons-update" style="font-size: 14px; width: 14px; height: 14px;"></span>' +
                '</button>' +
                '</div>' +
                '<div class="level-item">' +
                '<span class="tag is-info is-light">' +
                '<?php echo esc_js(__('Total Generated: ', 'comet-ai-says')); ?><strong>' +
                (data.total_generated || '0') + '</strong>' +
                '</span>' +
                '</div>' +
                '</div>' +
                '</div>' +
                '<div class="columns is-variable is-4">' +
                '<div class="column is-8">' +
                '<table class="table is-fullwidth is-striped" style="background: transparent;">' +
                '<thead>' +
                '<tr>' +
                '<th><?php echo esc_js(__('Limit Metric', 'comet-ai-says')); ?></th>' +
                '<th><?php echo esc_js(__('Used', 'comet-ai-says')); ?></th>' +
                '<th><?php echo esc_js(__('Limit', 'comet-ai-says')); ?></th>' +
                '<th style="width: 220px;"><?php echo esc_js(__('Capacity', 'comet-ai-says')); ?></th>' +
                '</tr>' +
                '</thead>' +
                '<tbody>' + metricsHtml + '</tbody>' +
                '</table>' +
                '</div>' +
                '<div class="column is-4">' +
                '<div class="card" style="height: 100%; border: 1px solid var(--comet-border, #edf2f7);">' +
                '<div class="card-content">' +
                '<h3 class="title is-6 mb-2"><?php echo esc_js(__('API Quota Status', 'comet-ai-says')); ?></h3>' +
                '<p class="is-size-7 has-text-grey mb-3"><?php echo esc_js(__('Minute limits reset every 60 seconds. Daily limits reset every 24 hours (UTC).', 'comet-ai-says')); ?></p>' +
                '<p class="is-size-7 mb-3"><strong><?php echo esc_js(__('Active Provider: ', 'comet-ai-says')); ?></strong>' +
                (data.provider_label || '') + '</p>' +
                '<div>' +
                '<a href="' + (data.provider_url || '#') +
                '" target="_blank" class="button is-small is-link is-light is-fullwidth">' +
                '<span class="dashicons dashicons-external mr-1"></span>' +
                (data.provider_btn_text ||
                    '<?php echo esc_js(__('Open Dashboard', 'comet-ai-says')); ?>'
                ) +
                '</a>' +
                '</div>' +
                '</div>' +
                '</div>' +
                '</div>' +
                '</div>';

            content.innerHTML = html;

            var refreshBtn = document.getElementById('comet-usage-refresh-btn');
            if (refreshBtn) {
                refreshBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    refreshBtn.classList.add('is-loading');
                    fetchUsageStats(true);
                });
            }
        }

        toggleBtn.addEventListener('click', function(e) {
            e.preventDefault();
            var isOpen = drawer.style.display !== 'none';
            var chevron = toggleBtn.querySelector('.comet-usage-chevron');

            if (isOpen) {
                drawer.style.display = 'none';
                toggleBtn.setAttribute('aria-expanded', 'false');
                toggleBtn.classList.remove('is-active');
                if (chevron) chevron.style.transform = 'rotate(0deg)';
            } else {
                drawer.style.display = 'block';
                toggleBtn.setAttribute('aria-expanded', 'true');
                toggleBtn.classList.add('is-active');
                if (chevron) chevron.style.transform = 'rotate(180deg)';

                // Load via REST API on show
                fetchUsageStats(false);
            }
        });
    })();
</script>
<?php
    }

    private function process_bulk_generation(array $product_ids): array
    {
        $results = [
            'success' => 0,
            'errors' => 0,
            'details' => [],
        ];

        $delay_between = 500000;
        $increase = 100000;

        foreach ($product_ids as $i => $product_id) {
            if ($i > 0) {
                usleep($delay_between + $i * $increase);
            }

            try {
                $product = function_exists('wc_get_product') ? wc_get_product($product_id) : null;
                if (!$product) {
                    $results['errors']++;
                    $results['details'][] = [
                        'product_id' => $product_id,
                        'status' => 'error',
                        'message' => __('Product not found', 'comet-ai-says'),
                    ];

                    continue;
                }

                $description = AIGenerator::generate_for_product($product_id);

                if (AIGenerator::is_valid_description($description)) {
                    update_post_meta($product_id, '_wpcmt_aisays_description', $description);
                    $results['success']++;
                    $results['details'][] = [
                        'product_id' => $product_id,
                        'status' => 'success',
                        /* translators: %s: Product name. */
                        'message' => sprintf(esc_html__('Generated for: %s', 'comet-ai-says'), $product->get_name()),
                    ];
                } else {
                    $results['errors']++;
                    $error_msg = is_string($description) && !empty($description) ? $description : __('Generation failed', 'comet-ai-says');
                    $results['details'][] = [
                        'product_id' => $product_id,
                        'status' => 'error',
                        /* translators: 1: Product name, 2: Error message details. */
                        'message' => sprintf(esc_html__('Generation failed for %1$s: %2$s', 'comet-ai-says'), $product->get_name(), esc_html($error_msg)),
                    ];
                }
            } catch (\Throwable $e) {
                $results['errors']++;
                $results['details'][] = [
                    'product_id' => $product_id,
                    'status' => 'error',
                    'message' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    private function render_gemini_model_select(string $current_gemini_model): void
    {
        $models = Config::get_gemini_models();
        if (empty($current_gemini_model) || !isset($models[$current_gemini_model])) {
            $current_gemini_model = 'gemini-3.6-flash';
        }
        ?>
<div class="comet-model-tiles-wrapper mb-2">
    <div class="comet-model-tiles-grid" data-provider="gemini" role="radiogroup"
        aria-label="<?php esc_attr_e('Select Gemini Model', 'comet-ai-says'); ?>">
        <?php foreach ($models as $key => $model_info): ?>
        <?php
                    $is_checked = ($current_gemini_model === $key);
                    $radio_id = 'model_gemini_'.sanitize_html_class($key);
                    ?>
        <label
            class="comet-model-tile <?php echo $is_checked ? 'is-selected' : ''; ?>"
            for="<?php echo esc_attr($radio_id); ?>">
            <input type="radio"
                id="<?php echo esc_attr($radio_id); ?>"
                name="wpcmt_aisays_settings[<?php echo esc_attr(Config::KEY_GEMINI_MODEL); ?>]"
                value="<?php echo esc_attr($key); ?>"
                <?php checked($is_checked, true); ?>
            class="comet-tile-radio" />
            <div class="comet-tile-card">
                <div class="comet-tile-header">
                    <div class="comet-tile-title-group">
                        <span
                            class="comet-tile-icon"><?php echo esc_html($model_info['icon'] ?? '✦'); ?></span>
                        <span
                            class="comet-tile-title"><?php echo esc_html($model_info['name']); ?></span>
                    </div>
                    <span class="comet-tile-radio-circle"></span>
                </div>
                <p class="comet-tile-desc">
                    <?php echo esc_html($model_info['description']); ?>
                </p>
                <div class="comet-tile-footer">
                    <div class="comet-tile-badges">
                        <?php if (!empty($model_info['badges'])): ?>
                        <?php foreach ($model_info['badges'] as $badge): ?>
                        <span
                            class="tag is-small <?php echo esc_attr($badge['class']); ?>">
                            <?php echo esc_html($badge['text']); ?>
                        </span>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($model_info['quota'])): ?>
                    <span
                        class="comet-tile-quota"><?php echo esc_html($model_info['quota']); ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </label>
        <?php endforeach; ?>
    </div>
    <div class="is-flex is-align-items-center is-justify-content-space-between mt-3">
        <p class="help is-info mb-0">
            <strong><?php esc_html_e('🚀 Active Model Capacity:', 'comet-ai-says'); ?></strong>
            <span id="token-capacity-info" class="tag is-light is-small ml-2">1M TPM, 15 RPM</span>
        </p>
        <div class="comet-tooltip-wrap ml-2">
            <span class="comet-info-icon" tabindex="0" role="button"
                aria-label="<?php esc_attr_e('Model Availability Tip', 'comet-ai-says'); ?>">i</span>
            <div class="comet-tooltip-content" role="tooltip">
                <div class="comet-tooltip-arrow"></div>
                <strong><?php esc_html_e('API Availability Tip:', 'comet-ai-says'); ?></strong><br>
                <?php esc_html_e('Google API model rollouts vary by API key tier and region. If your key encounters an unsupported model error on a pinned version, select', 'comet-ai-says'); ?>
                <code>gemini-flash-latest</code>
                <?php esc_html_e('to automatically route to the newest model supported by your account.', 'comet-ai-says'); ?>
            </div>
        </div>
    </div>
</div>
<?php
    }

    private function render_openai_model_select(string $current_openai_model): void
    {
        $models = Config::get_openai_models();
        if (empty($current_openai_model) || !isset($models[$current_openai_model])) {
            $current_openai_model = 'gpt-4o';
        }
        ?>
<div class="comet-model-tiles-wrapper mb-2">
    <div class="comet-model-tiles-grid" data-provider="openai" role="radiogroup"
        aria-label="<?php esc_attr_e('Select OpenAI Model', 'comet-ai-says'); ?>">
        <?php foreach ($models as $key => $model_info): ?>
        <?php
                    $is_checked = ($current_openai_model === $key);
                    $radio_id = 'model_openai_'.sanitize_html_class($key);
                    ?>
        <label
            class="comet-model-tile <?php echo $is_checked ? 'is-selected' : ''; ?>"
            for="<?php echo esc_attr($radio_id); ?>">
            <input type="radio"
                id="<?php echo esc_attr($radio_id); ?>"
                name="wpcmt_aisays_settings[<?php echo esc_attr(Config::KEY_OPENAI_MODEL); ?>]"
                value="<?php echo esc_attr($key); ?>"
                <?php checked($is_checked, true); ?>
            class="comet-tile-radio" />
            <div class="comet-tile-card">
                <div class="comet-tile-header">
                    <div class="comet-tile-title-group">
                        <span
                            class="comet-tile-icon"><?php echo esc_html($model_info['icon'] ?? '✦'); ?></span>
                        <span
                            class="comet-tile-title"><?php echo esc_html($model_info['name']); ?></span>
                    </div>
                    <span class="comet-tile-radio-circle"></span>
                </div>
                <p class="comet-tile-desc">
                    <?php echo esc_html($model_info['description']); ?>
                </p>
                <div class="comet-tile-footer">
                    <div class="comet-tile-badges">
                        <?php if (!empty($model_info['badges'])): ?>
                        <?php foreach ($model_info['badges'] as $badge): ?>
                        <span
                            class="tag is-small <?php echo esc_attr($badge['class']); ?>">
                            <?php echo esc_html($badge['text']); ?>
                        </span>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($model_info['quota'])): ?>
                    <span
                        class="comet-tile-quota"><?php echo esc_html($model_info['quota']); ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </label>
        <?php endforeach; ?>
    </div>
    <p class="help is-info mt-3">
        <strong><?php esc_html_e('Recommended:', 'comet-ai-says'); ?></strong>
        <?php esc_html_e('GPT-4o or GPT-4o Mini for fast, vision-enabled product descriptions.', 'comet-ai-says'); ?>
    </p>
</div>
<?php
    }

    private function render_display_settings(string $current_display_mode, string $current_display_position, string $current_shortcode): void
    {
        ?>
<tr>
    <th scope="row">
        <label
            for="wpcmt_aisays_display_mode"><?php esc_html_e('Display Mode', 'comet-ai-says'); ?></label>
    </th>
    <td>
        <div class="select" style="max-width: 400px;">
            <select id="wpcmt_aisays_display_mode"
                name="wpcmt_aisays_settings[<?php echo esc_attr(Config::KEY_DISPLAY_MODE); ?>]">
                <option value="automatic" <?php selected($current_display_mode, 'automatic'); ?>>
                    <?php esc_html_e('Automatic - Hook into product page', 'comet-ai-says'); ?>
                </option>
                <option value="manual" <?php selected($current_display_mode, 'manual'); ?>>
                    <?php esc_html_e('Manual - Shortcode only', 'comet-ai-says'); ?>
                </option>
            </select>
        </div>
        <p class="help mt-2">
            <?php esc_html_e('Choose how AI descriptions are displayed on single product pages.', 'comet-ai-says'); ?>
        </p>
    </td>
</tr>

<tr id="display-position-row"
    style="<?php echo ('automatic' !== $current_display_mode) ? 'display: none;' : ''; ?>">
    <th scope="row">
        <label
            for="wpcmt_aisays_display_position"><?php esc_html_e('Display Position', 'comet-ai-says'); ?></label>
    </th>
    <td>
        <div class="select" style="max-width: 400px;">
            <select id="wpcmt_aisays_display_position"
                name="wpcmt_aisays_settings[<?php echo esc_attr(Config::KEY_DISPLAY_POSITION); ?>]">
                <option value="after_short_description" <?php selected($current_display_position, 'after_short_description'); ?>>
                    <?php esc_html_e('After short description', 'comet-ai-says'); ?>
                </option>
                <option value="after_description" <?php selected($current_display_position, 'after_description'); ?>>
                    <?php esc_html_e('After main product description', 'comet-ai-says'); ?>
                </option>
                <option value="after_tabs" <?php selected($current_display_position, 'after_tabs'); ?>>
                    <?php esc_html_e('After product tabs', 'comet-ai-says'); ?>
                </option>
                <option value="product_bottom" <?php selected($current_display_position, 'product_bottom'); ?>>
                    <?php esc_html_e('Bottom of product page', 'comet-ai-says'); ?>
                </option>
            </select>
        </div>
    </td>
</tr>

<tr id="shortcode-row"
    style="<?php echo ('manual' !== $current_display_mode) ? 'display: none;' : ''; ?>">
    <th scope="row">
        <label
            for="wpcmt_aisays_shortcode"><?php esc_html_e('Shortcode', 'comet-ai-says'); ?></label>
    </th>
    <td>
        <div class="field has-addons" style="max-width: 450px;">
            <div class="control is-expanded">
                <input type="text" id="wpcmt_aisays_shortcode"
                    name="wpcmt_aisays_settings[<?php echo esc_attr(Config::KEY_SHORTCODE); ?>]"
                    value="<?php echo esc_attr($current_shortcode); ?>"
                    class="input is-small font-monospace" readonly />
            </div>
            <div class="control">
                <button type="button" class="button is-small is-light"
                    onclick="navigator.clipboard.writeText('<?php echo esc_js($current_shortcode); ?>'); alert('<?php echo esc_js(__('Shortcode copied to clipboard!', 'comet-ai-says')); ?>');">
                    <?php esc_html_e('Copy', 'comet-ai-says'); ?>
                </button>
            </div>
        </div>
        <p class="help mt-1">
            <?php esc_html_e('Use this shortcode anywhere in Elementor, Gutenberg, or theme templates.', 'comet-ai-says'); ?>
        </p>
    </td>
</tr>
<?php
    }

    private function render_language_settings(string $current_language, string $custom_language): void
    {
        ?>
<tr>
    <th scope="row">
        <label
            for="wpcmt_aisays_language"><?php esc_html_e('Description Language', 'comet-ai-says'); ?></label>
    </th>
    <td>
        <div class="select" style="max-width: 400px;">
            <select id="wpcmt_aisays_language"
                name="wpcmt_aisays_settings[<?php echo esc_attr(Config::KEY_LANGUAGE); ?>]">
                <?php foreach (Config::LANGUAGE_DATA as $key => $lang): ?>
                <option value="<?php echo esc_attr($key); ?>" <?php selected($current_language, $key); ?>>
                    <?php echo esc_html($lang[0]); ?>
                    (<?php echo esc_html($lang[1]); ?>)
                </option>
                <?php endforeach; ?>
                <option value="custom" <?php selected($current_language, 'custom'); ?>>
                    <?php esc_html_e('Custom Language', 'comet-ai-says'); ?>
                </option>
            </select>
        </div>
    </td>
</tr>

<tr id="custom-language-row"
    style="<?php echo ('custom' !== $current_language) ? 'display: none;' : ''; ?>">
    <th scope="row">
        <label
            for="wpcmt_aisays_custom_language"><?php esc_html_e('Custom Language Name', 'comet-ai-says'); ?></label>
    </th>
    <td>
        <input type="text" id="wpcmt_aisays_custom_language"
            name="wpcmt_aisays_settings[<?php echo esc_attr(Config::KEY_CUSTOM_LANGUAGE); ?>]"
            value="<?php echo esc_attr($custom_language); ?>"
            class="input regular-text"
            placeholder="<?php esc_attr_e('e.g., Swedish, Polish, Greek', 'comet-ai-says'); ?>" />
    </td>
</tr>
<?php
    }

    private function render_prompt_template(string $current_prompt_template, string $current_language = 'english', string $custom_language = ''): void
    {
        $variables = [
            '{product_name}' => __('Product title / name', 'comet-ai-says'),
            '{short_description}' => __('Existing product short description', 'comet-ai-says'),
            '{categories}' => __('Product categories separated by commas', 'comet-ai-says'),
            '{tags}' => __('Product tags list', 'comet-ai-says'),
            '{attributes}' => __('WooCommerce attributes & specifications', 'comet-ai-says'),
            '{image_analysis}' => __('AI visual inspection context of featured image', 'comet-ai-says'),
            '{introduction}' => __('Opening language instructions', 'comet-ai-says'),
            '{instructions}' => __('Format & tone guidelines', 'comet-ai-says'),
            '{store_context}' => __('Store name or domain branding context', 'comet-ai-says'),
        ];

        $presets = Config::get_prompt_presets();
        $matched_preset_id = '';
        foreach ($presets as $p_id => $p_data) {
            if (trim($current_prompt_template) === trim($p_data['template'])) {
                $matched_preset_id = $p_id;
                break;
            }
        }
        ?>
<!-- Description Language Row -->
<table class="form-table mb-4"
    style="margin-top: 0; border-bottom: 1px solid var(--comet-border-weak); padding-bottom: 1.25rem;">
    <?php $this->render_language_settings($current_language, $custom_language); ?>
</table>

<!-- Preset Prompts Library -->
<div class="comet-prompt-presets-bar mb-3 p-3" style="background: var(--comet-surface-subtle); border: 1px solid var(--comet-border-weak); border-radius: 8px;">
    <div class="is-flex is-justify-content-space-between is-align-items-center is-flex-wrap-wrap gap-2">
        <div class="is-flex is-align-items-center is-flex-wrap-wrap gap-2">
            <span class="dashicons dashicons-admin-customizer has-text-primary" style="font-size: 16px; width: 16px; height: 16px; line-height: 16px;"></span>
            <label for="comet-prompt-preset-select" class="label is-small mb-0 has-text-weight-semibold">
                <?php esc_html_e('Prompt Preset Library:', 'comet-ai-says'); ?>
            </label>
            <div class="select is-small">
                <select id="comet-prompt-preset-select" style="min-width: 230px;">
                    <option value="" <?php selected(empty($matched_preset_id)); ?>>
                        <?php esc_html_e('-- Choose a Tone Preset --', 'comet-ai-says'); ?>
                    </option>
                    <?php foreach ($presets as $p_id => $p_data): ?>
                    <option value="<?php echo esc_attr($p_id); ?>" <?php selected($matched_preset_id, $p_id); ?>>
                        <?php echo esc_html($p_data['name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="button" id="comet-apply-preset-btn" class="button is-small is-primary is-light" <?php disabled(empty($matched_preset_id)); ?>>
                <span class="dashicons dashicons-yes-alt mr-1" style="font-size: 14px; width: 14px; height: 14px; line-height: 14px;"></span>
                <span class="comet-apply-btn-label"><?php esc_html_e('Apply Preset', 'comet-ai-says'); ?></span>
            </button>
        </div>
        <span id="comet-preset-desc-hint" class="is-size-7 has-text-grey" style="font-style: italic;">
            <?php
            if (!empty($matched_preset_id) && isset($presets[$matched_preset_id])) {
                echo esc_html($presets[$matched_preset_id]['description']);
            } else {
                esc_html_e('Choose from ready-to-use tone archetypes or customize manually.', 'comet-ai-says');
            }
            ?>
        </span>
    </div>
</div>

<!-- Quick Insert Variables & Guide Toggle -->
<div class="comet-variable-pills mb-3">
    <div class="is-flex is-justify-content-space-between is-align-items-center mb-2">
        <label
            class="label is-small mb-0 has-text-grey"><?php esc_html_e('Quick Insert Template Variables:', 'comet-ai-says'); ?></label>
        <button type="button" id="comet-toggle-var-guide-btn" class="button is-small is-light is-rounded px-3"
            aria-expanded="false" aria-controls="comet-prompt-vars-guide-box"
            style="height: 26px; font-size: 11px; font-weight: 600; display: inline-flex; align-items: center; justify-content: center; gap: 4px; line-height: 1;">
            <span class="dashicons dashicons-editor-help"
                style="font-size: 14px; width: 14px; height: 14px; display: inline-flex; align-items: center; justify-content: center; margin: 0; line-height: 1;"></span>
            <span
                class="comet-guide-btn-text" style="line-height: 1;"><?php esc_html_e('Guide', 'comet-ai-says'); ?></span>
            <span class="dashicons dashicons-arrow-down-alt2 comet-guide-chevron"
                style="font-size: 12px; width: 12px; height: 12px; display: inline-flex; align-items: center; justify-content: center; margin: 0; line-height: 1; transition: transform 0.2s ease;"></span>
        </button>
    </div>

    <!-- Collapsible Prompt Variables Reference Guide Box -->
    <div id="comet-prompt-vars-guide-box" class="box p-3 mb-3"
        style="display: none; background: var(--comet-surface-subtle); border: 1px solid var(--comet-border-weak); border-radius: 8px;">
        <div class="is-flex is-justify-content-space-between is-align-items-center mb-2 px-1">
            <span class="is-size-7 has-text-weight-semibold has-text-primary is-flex is-align-items-center">
                <span class="dashicons dashicons-info mr-1"
                    style="font-size: 15px; width: 15px; height: 15px; line-height: 15px;"></span>
                <?php esc_html_e('Prompt Variables Reference Guide', 'comet-ai-says'); ?>
            </span>
            <span
                class="is-size-7 has-text-grey"><?php esc_html_e('Click any variable pill to insert directly at cursor', 'comet-ai-says'); ?></span>
        </div>
        <div class="columns is-multiline is-variable is-2 mb-0">
            <?php foreach ($variables as $tag => $desc): ?>
            <div class="column is-6 py-1">
                <div class="comet-var-card is-flex is-justify-content-space-between is-align-items-center p-2"
                    style="background: var(--comet-surface-card); border: 1px solid var(--comet-border-weak); border-radius: 6px; min-height: 38px;">
                    <button type="button" class="comet-var-pill m-0"
                        data-var="<?php echo esc_attr($tag); ?>">
                        <?php echo esc_html($tag); ?>
                    </button>
                    <span class="is-size-7 has-text-grey ml-2" style="text-align: right; line-height: 1.25;">
                        <?php echo esc_html($desc); ?>
                    </span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="is-flex is-flex-wrap-wrap">
        <?php foreach (array_keys($variables) as $tag): ?>
        <button type="button" class="comet-var-pill"
            data-var="<?php echo esc_attr($tag); ?>"><?php echo esc_html($tag); ?></button>
        <?php endforeach; ?>
    </div>
</div>

<div id="prompt-preview" class="comet-prompt-preview-box mb-3" style="display: none;">
    <div class="is-flex is-justify-content-space-between is-align-items-center mb-2">
        <strong
            class="is-size-7"><?php esc_html_e('Live Prompt Preview:', 'comet-ai-says'); ?></strong>
        <span
            class="tag is-small is-light"><?php esc_html_e('Preview Mode', 'comet-ai-says'); ?></span>
    </div>
    <pre id="preview-content"
        style="white-space: pre-wrap; margin: 0; max-height: 250px; overflow-y: auto; font-size: 12px;"></pre>
</div>

<textarea id="wpcmt_aisays_prompt_template"
    name="wpcmt_aisays_settings[<?php echo esc_attr(Config::KEY_PROMPT_TEMPLATE); ?>]"
    rows="9" class="textarea font-monospace" style="width: 100%; font-size: 13px;"
    placeholder="<?php echo esc_attr(Config::get_default_prompt_template()); ?>"><?php echo esc_textarea($current_prompt_template); ?></textarea>

<textarea id="comet-default-prompt-template"
    style="display: none;"><?php echo esc_textarea(Config::get_default_prompt_template()); ?></textarea>

<div class="is-flex is-justify-content-space-between is-align-items-center mt-2">
    <span
        class="help"><?php esc_html_e('Customize prompt instructions and layout per store tone.', 'comet-ai-says'); ?></span>
    <button type="button" id="wpcmt-aisays-reset-prompt" class="button is-small">
        <span class="dashicons dashicons-image-rotate mr-1"
            style="font-size: 14px; width: 14px; height: 14px; line-height: 14px;"></span>
        <?php esc_html_e('Reset to Default Template', 'comet-ai-says'); ?>
    </button>
</div>
<?php
    }

    private function render_prompt_guide_card(): void
    {
        $variables = [
            '{product_name}' => __('Product title / name', 'comet-ai-says'),
            '{short_description}' => __('Existing product short description', 'comet-ai-says'),
            '{categories}' => __('Product categories separated by commas', 'comet-ai-says'),
            '{tags}' => __('Product tags list', 'comet-ai-says'),
            '{attributes}' => __('WooCommerce attributes & specifications', 'comet-ai-says'),
            '{image_analysis}' => __('AI visual inspection context of featured image', 'comet-ai-says'),
            '{introduction}' => __('Opening language instructions', 'comet-ai-says'),
            '{instructions}' => __('Format & tone guidelines', 'comet-ai-says'),
            '{store_context}' => __('Store name or domain branding context', 'comet-ai-says'),
        ];
        ?>
<div class="card mb-4 comet-sidebar-card">
    <header class="card-header">
        <p class="card-header-title is-flex is-align-items-center">
            <span class="dashicons dashicons-editor-help mr-2 has-text-info"></span>
            <?php esc_html_e('Prompt Variables Guide', 'comet-ai-says'); ?>
        </p>
    </header>
    <div class="card-content p-3">
        <p class="is-size-7 has-text-grey mb-3 px-2">
            <?php esc_html_e('Click any variable pill to insert it directly into your prompt template:', 'comet-ai-says'); ?>
        </p>
        <div class="comet-var-list">
            <?php foreach ($variables as $tag => $desc) : ?>
            <div class="comet-var-item is-flex is-justify-content-space-between is-align-items-center p-2 mb-2"
                style="border-radius: 6px; background: var(--comet-surface-subtle); border: 1px solid var(--comet-border-weak);">
                <button type="button" class="comet-var-pill m-0"
                    data-var="<?php echo esc_attr($tag); ?>">
                    <?php echo esc_html($tag); ?>
                </button>
                <span class="is-size-7 has-text-grey ml-2" style="text-align: right; line-height: 1.25;">
                    <?php echo esc_html($desc); ?>
                </span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php
    }

    private function render_setup_instructions_card(): void
    {
        $current_provider = Config::get_option(Config::KEY_PROVIDER, 'gemini');
        ?>
<div class="card mb-4 comet-sidebar-card">
    <header class="card-header">
        <p class="card-header-title is-flex is-align-items-center">
            <span class="dashicons dashicons-admin-generic mr-2 has-text-primary"></span>
            <?php esc_html_e('API Setup Guide', 'comet-ai-says'); ?>
        </p>
        <span
            class="tag is-info is-light m-2"><?php echo esc_html(ucfirst($current_provider)); ?>
            <?php esc_html_e('Active', 'comet-ai-says'); ?></span>
    </header>
    <div class="card-content">
        <!-- Gemini Guide -->
        <div class="setup-guide-item mb-4 pb-3" style="border-bottom: 1px solid var(--comet-border-weak);">
            <div class="is-flex is-justify-content-space-between is-align-items-center mb-1">
                <strong
                    class="is-size-7 has-text-primary"><?php esc_html_e('Google Gemini', 'comet-ai-says'); ?></strong>
                <span
                    class="tag is-small is-success is-light"><?php esc_html_e('Free Tier', 'comet-ai-says'); ?></span>
            </div>
            <p class="is-size-7 has-text-grey mb-2">
                <?php esc_html_e('Get a 100% free API key with 15 requests/min and 1M tokens/min.', 'comet-ai-says'); ?>
            </p>
            <a href="https://aistudio.google.com/app/apikey" target="_blank"
                class="button is-small is-fullwidth is-light is-primary">
                <span><?php esc_html_e('Get Google AI Studio Key', 'comet-ai-says'); ?></span>
                <span class="dashicons dashicons-external ml-1"
                    style="font-size: 14px; width: 14px; height: 14px; line-height: 14px;"></span>
            </a>
        </div>

        <!-- OpenAI Guide -->
        <div class="setup-guide-item">
            <div class="is-flex is-justify-content-space-between is-align-items-center mb-1">
                <strong
                    class="is-size-7"><?php esc_html_e('OpenAI GPT', 'comet-ai-says'); ?></strong>
                <span
                    class="tag is-small is-light"><?php esc_html_e('Paid Tier', 'comet-ai-says'); ?></span>
            </div>
            <p class="is-size-7 has-text-grey mb-2">
                <?php esc_html_e('Requires an OpenAI platform account with prepaid billing credits.', 'comet-ai-says'); ?>
            </p>
            <a href="https://platform.openai.com/api-keys" target="_blank"
                class="button is-small is-fullwidth is-light">
                <span><?php esc_html_e('Get OpenAI API Key', 'comet-ai-says'); ?></span>
                <span class="dashicons dashicons-external ml-1"
                    style="font-size: 14px; width: 14px; height: 14px; line-height: 14px;"></span>
            </a>
        </div>
    </div>
</div>
<?php
    }

    private function render_support_card(): void
    {
        ?>
<div class="card mb-4 comet-sidebar-card">
    <header class="card-header">
        <p class="card-header-title is-flex is-align-items-center">
            <span class="dashicons dashicons-sos mr-2 has-text-success"></span>
            <?php esc_html_e('Resources & Support', 'comet-ai-says'); ?>
        </p>
    </header>
    <div class="card-content">
        <p class="is-size-7 has-text-grey mb-3">
            <?php esc_html_e('Need assistance, custom model tuning, or want to report feedback?', 'comet-ai-says'); ?>
        </p>
        <div class="buttons mb-3">
            <a href="https://wpcomet.com/ai-says/" target="_blank" class="button is-small is-light is-fullwidth">
                <span class="dashicons dashicons-book mr-1"></span>
                <?php esc_html_e('Documentation', 'comet-ai-says'); ?>
            </a>
            <a href="https://wpcomet.com/support/" target="_blank"
                class="button is-small is-outlined is-primary is-fullwidth">
                <span class="dashicons dashicons-email-alt mr-1"></span>
                <?php esc_html_e('Contact Support', 'comet-ai-says'); ?>
            </a>
        </div>
        <div class="is-flex is-justify-content-space-between is-align-items-center pt-2"
            style="border-top: 1px solid var(--comet-border-weak);">
            <span
                class="is-size-7 has-text-grey"><?php esc_html_e('Handcrafted by WpComet', 'comet-ai-says'); ?></span>
            <span
                class="tag is-small is-light">v<?php echo esc_html(COMET_AI_SAYS_VERSION); ?></span>
        </div>
    </div>
</div>
<?php
    }

    private function needs_onboarding(): bool
    {
        // When settings were just saved, proceed directly to settings dashboard
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if (isset($_GET['settings-updated'])) {
            return false;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if (isset($_GET['onboarding'])) {
            delete_transient(Config::TRANSIENT_SKIP_ONBOARDING);

            return true;
        }

        if (get_transient(Config::TRANSIENT_SKIP_ONBOARDING)) {
            return false;
        }

        $gemini_key = Config::get_option(Config::KEY_GEMINI_KEY, '');
        $openai_key = Config::get_option(Config::KEY_OPENAI_KEY, '');

        return empty($gemini_key) && empty($openai_key);
    }

    private function render_onboarding_screen(): void
    {
        $gemini_key = Config::get_option(Config::KEY_GEMINI_KEY, '');
        $openai_key = Config::get_option(Config::KEY_OPENAI_KEY, '');
        // Onboarding is only shown when keys are unconfigured; always default to Gemini (recommended free tier)
        $current_provider = (!empty($openai_key) && empty($gemini_key)) ? 'openai' : 'gemini';
        ?>
<div class="comet-onboarding-wrap">
    <!-- Top Bar: Logo & Theme Toggle -->
    <div class="is-flex is-justify-content-space-between is-align-items-center mb-4"
        style="max-width: 680px; width: 100%;">
        <div class="is-flex is-align-items-center">
            <img src="<?php echo esc_url($this->get_asset_url('solo-color.svg')); ?>"
                width="32" height="32" alt="WpComet" class="mr-2" />
            <span
                class="has-text-weight-bold is-size-6"><?php esc_html_e('Comet AI Says', 'comet-ai-says'); ?></span>
            <span
                class="tag is-primary is-light is-small ml-2">v<?php echo esc_html(COMET_AI_SAYS_VERSION); ?></span>
        </div>
        <div>
            <button type="button" id="comet-theme-toggle" class="comet-theme-toggle"
                title="<?php esc_attr_e('Toggle Light/Dark Theme', 'comet-ai-says'); ?>">
                <span class="theme-icon">🌙</span>
            </button>
        </div>
    </div>

    <!-- Main Wizard Box -->
    <div class="box comet-onboarding-box p-0 mb-4">
        <!-- Header -->
        <div class="comet-onboarding-header">
            <div class="comet-onboarding-logo-badge">
                <img src="<?php echo esc_url($this->get_asset_url('solo-color.svg')); ?>"
                    width="44" height="44" alt="Comet AI Says" />
            </div>
            <h1 class="title is-4 mb-2 has-text-weight-bold">
                <?php esc_html_e('Welcome to Comet AI Says!', 'comet-ai-says'); ?>
            </h1>
            <p class="subtitle is-6 has-text-grey mb-3">
                <?php esc_html_e('Connect your AI engine to start generating contextual product copy for WooCommerce in seconds.', 'comet-ai-says'); ?>
            </p>
            <div class="comet-onboarding-steps">
                <span class="comet-onboarding-step-pill is-active">
                    <span class="dashicons dashicons-admin-generic"
                        style="font-size: 13px; width: 13px; height: 13px; line-height: 13px;"></span>
                    <?php esc_html_e('1. Provider', 'comet-ai-says'); ?>
                </span>
                <span class="comet-onboarding-step-pill is-active">
                    <span class="dashicons dashicons-admin-network"
                        style="font-size: 13px; width: 13px; height: 13px; line-height: 13px;"></span>
                    <?php esc_html_e('2. API Key', 'comet-ai-says'); ?>
                </span>
                <span class="comet-onboarding-step-pill">
                    <span class="dashicons dashicons-yes-alt"
                        style="font-size: 13px; width: 13px; height: 13px; line-height: 13px;"></span>
                    <?php esc_html_e('3. Ready', 'comet-ai-says'); ?>
                </span>
            </div>
        </div>

        <!-- Form Body -->
        <div class="comet-onboarding-body">
            <form id="wpcmt-aisays-onboarding-form" method="post" action="options.php" autocomplete="off"
                data-lpignore="true" data-1p-ignore="true" data-bwignore="true" data-protonpass-ignore="true"
                data-form-type="other">
                <?php settings_fields('wpcmt_aisays_settings'); ?>
                <input type="hidden" name="_wp_http_referer"
                    value="<?php echo esc_attr(admin_url('options-general.php?page=wpcmt-aisays-settings')); ?>" />

                <!-- 1. Choose Provider (Interactive Tiles) -->
                <div class="field mb-5">
                    <label class="label is-small has-text-weight-bold is-uppercase mb-3" style="letter-spacing: 0.5px;">
                        <?php esc_html_e('1. Choose AI Engine', 'comet-ai-says'); ?>
                    </label>

                    <div class="comet-provider-grid">
                        <!-- Gemini Tile -->
                        <label
                            class="comet-provider-tile <?php echo ('gemini' === $current_provider) ? 'is-selected' : ''; ?>"
                            for="onboarding_provider_gemini">
                            <input type="radio" id="onboarding_provider_gemini"
                                name="wpcmt_aisays_settings[<?php echo esc_attr(Config::KEY_PROVIDER); ?>]"
                                value="gemini" class="comet-tile-radio"
                                <?php checked($current_provider, 'gemini'); ?>
                            />
                            <div>
                                <div class="is-flex is-justify-content-space-between is-align-items-center mb-1">
                                    <span class="has-text-weight-bold is-size-6 is-flex is-align-items-center">
                                        <span class="has-text-primary mr-2" style="font-size: 1.15rem;">✦</span>
                                        Google Gemini
                                    </span>
                                    <span class="comet-tile-radio-circle"></span>
                                </div>
                                <p class="is-size-7 has-text-grey mb-3">
                                    <?php esc_html_e('High-speed multimodal vision with 15 RPM / 1M TPM free tier. Ideal for catalog copy.', 'comet-ai-says'); ?>
                                </p>
                            </div>
                            <div>
                                <span
                                    class="tag is-primary is-light is-small"><?php esc_html_e('Recommended (Free Tier)', 'comet-ai-says'); ?></span>
                            </div>
                        </label>

                        <!-- OpenAI Tile -->
                        <label
                            class="comet-provider-tile <?php echo ('openai' === $current_provider) ? 'is-selected' : ''; ?>"
                            for="onboarding_provider_openai">
                            <input type="radio" id="onboarding_provider_openai"
                                name="wpcmt_aisays_settings[<?php echo esc_attr(Config::KEY_PROVIDER); ?>]"
                                value="openai" class="comet-tile-radio"
                                <?php checked($current_provider, 'openai'); ?>
                            />
                            <div>
                                <div class="is-flex is-justify-content-space-between is-align-items-center mb-1">
                                    <span class="has-text-weight-bold is-size-6 is-flex is-align-items-center">
                                        <span class="has-text-info mr-2" style="font-size: 1.15rem;">◈</span>
                                        OpenAI GPT
                                    </span>
                                    <span class="comet-tile-radio-circle"></span>
                                </div>
                                <p class="is-size-7 has-text-grey mb-3">
                                    <?php esc_html_e('Connect your OpenAI developer account to generate copy via GPT-4o or GPT-4o mini.', 'comet-ai-says'); ?>
                                </p>
                            </div>
                            <div>
                                <span
                                    class="tag is-light is-small"><?php esc_html_e('Paid Platform Tier', 'comet-ai-says'); ?></span>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- 2. Gemini API Key Section -->
                <div id="gemini-onboarding-section" class="field mb-5"
                    style="<?php echo ('gemini' !== $current_provider) ? 'display: none;' : ''; ?>">
                    <label class="label is-small has-text-weight-bold is-uppercase mb-2" style="letter-spacing: 0.5px;">
                        <?php esc_html_e('2. Google Gemini API Key', 'comet-ai-says'); ?>
                    </label>
                    <div class="control">
                        <div class="pw-wrap" style="max-width: 100%;">
                            <input type="text" id="wpcmt_aisays_onboarding_gemini_api_key"
                                name="wpcmt_aisays_settings[<?php echo esc_attr(Config::KEY_GEMINI_KEY); ?>]"
                                value="<?php echo esc_attr($gemini_key); ?>"
                                class="input is-medium api-key-field masked" autocomplete="off" autocorrect="off"
                                autocapitalize="off" spellcheck="false" data-lpignore="true" data-1p-ignore="true"
                                data-bwignore="true" data-protonpass-ignore="true" data-form-type="other"
                                placeholder="<?php esc_attr_e('Paste your Gemini API key (AIzaSy...)', 'comet-ai-says'); ?>" />
                            <button type="button" class="button is-small is-light toggle-key-visibility"
                                data-target="wpcmt_aisays_onboarding_gemini_api_key"
                                title="<?php esc_attr_e('Show API Key', 'comet-ai-says'); ?>">
                                <span class="dashicons dashicons-visibility"></span>
                                <span
                                    class="toggle-key-text"><?php esc_html_e('Show', 'comet-ai-says'); ?></span>
                            </button>
                        </div>
                    </div>
                    <div
                        class="comet-onboarding-guide-callout is-flex is-align-items-center is-justify-content-space-between mt-2">
                        <div class="is-flex is-align-items-center">
                            <span class="dashicons dashicons-info-outline mr-2 has-text-primary"
                                style="font-size: 18px; width: 18px; height: 18px;"></span>
                            <span><?php esc_html_e('Get your free API key in under 60 seconds from Google AI Studio (no credit card needed).', 'comet-ai-says'); ?></span>
                        </div>
                        <a href="https://aistudio.google.com/app/apikey" target="_blank" rel="noopener noreferrer"
                            class="button is-small is-primary is-light ml-3" style="white-space: nowrap;">
                            <span><?php esc_html_e('Get Free Key', 'comet-ai-says'); ?></span>
                            <span class="dashicons dashicons-external ml-1"
                                style="font-size: 13px; width: 13px; height: 13px; line-height: 13px;"></span>
                        </a>
                    </div>
                </div>

                <!-- 2. OpenAI API Key Section -->
                <div id="openai-onboarding-section" class="field mb-5"
                    style="<?php echo ('openai' !== $current_provider) ? 'display: none;' : ''; ?>">
                    <label class="label is-small has-text-weight-bold is-uppercase mb-2" style="letter-spacing: 0.5px;">
                        <?php esc_html_e('2. OpenAI API Key', 'comet-ai-says'); ?>
                    </label>
                    <div class="control">
                        <div class="pw-wrap" style="max-width: 100%;">
                            <input type="text" id="wpcmt_aisays_onboarding_openai_api_key"
                                name="wpcmt_aisays_settings[<?php echo esc_attr(Config::KEY_OPENAI_KEY); ?>]"
                                value="<?php echo esc_attr($openai_key); ?>"
                                class="input is-medium api-key-field masked" autocomplete="off" autocorrect="off"
                                autocapitalize="off" spellcheck="false" data-lpignore="true" data-1p-ignore="true"
                                data-bwignore="true" data-protonpass-ignore="true" data-form-type="other"
                                placeholder="<?php esc_attr_e('Paste your OpenAI API key (sk-...)', 'comet-ai-says'); ?>" />
                            <button type="button" class="button is-small is-light toggle-key-visibility"
                                data-target="wpcmt_aisays_onboarding_openai_api_key"
                                title="<?php esc_attr_e('Show API Key', 'comet-ai-says'); ?>">
                                <span class="dashicons dashicons-visibility"></span>
                                <span
                                    class="toggle-key-text"><?php esc_html_e('Show', 'comet-ai-says'); ?></span>
                            </button>
                        </div>
                    </div>
                    <div
                        class="comet-onboarding-guide-callout is-flex is-align-items-center is-justify-content-space-between mt-2">
                        <div class="is-flex is-align-items-center">
                            <span class="dashicons dashicons-info-outline mr-2 has-text-info"
                                style="font-size: 18px; width: 18px; height: 18px;"></span>
                            <span><?php esc_html_e('Get your API secret key from your OpenAI Platform account dashboard.', 'comet-ai-says'); ?></span>
                        </div>
                        <a href="https://platform.openai.com/api-keys" target="_blank" rel="noopener noreferrer"
                            class="button is-small is-info is-light ml-3" style="white-space: nowrap;">
                            <span><?php esc_html_e('OpenAI Keys', 'comet-ai-says'); ?></span>
                            <span class="dashicons dashicons-external ml-1"
                                style="font-size: 13px; width: 13px; height: 13px; line-height: 13px;"></span>
                        </a>
                    </div>
                </div>

                <!-- Pre-configured defaults info -->
                <div class="notification is-light is-primary p-3 mb-5 is-flex is-align-items-center">
                    <span class="dashicons dashicons-yes-alt mr-2 has-text-primary"
                        style="font-size: 20px; width: 20px; height: 20px;"></span>
                    <span class="is-size-7">
                        <?php esc_html_e('Optimized defaults are already configured: Gemini 3.6 Flash engine, 1500 max tokens, contextual spec extraction, and safe WooCommerce custom field isolation.', 'comet-ai-says'); ?>
                    </span>
                </div>

                <!-- Actions -->
                <div class="buttons mb-0">
                    <button type="submit" class="button is-primary is-medium is-fullwidth has-text-weight-bold">
                        <span><?php esc_html_e('Save & Start Generating', 'comet-ai-says'); ?></span>
                        <span class="dashicons dashicons-arrow-right-alt2 ml-2"
                            style="font-size: 18px; width: 18px; height: 18px; line-height: 18px;"></span>
                    </button>
                    <button type="button" id="wpcmt-aisays-skip-onboarding"
                        class="button is-ghost is-small is-fullwidth mt-2 has-text-grey">
                        <?php esc_html_e('Skip setup for now & go to Settings', 'comet-ai-says'); ?>
                    </button>
                </div>
            </form>
        </div>

        <!-- Features Footer -->
        <div class="comet-onboarding-features-bar">
            <div><span class="mr-1">🛡️</span>
                <strong><?php esc_html_e('Safe Storage', 'comet-ai-says'); ?></strong>:
                <?php esc_html_e('Never touches original text', 'comet-ai-says'); ?>
            </div>
            <div><span class="mr-1">⚡</span>
                <strong><?php esc_html_e('Fast Processing', 'comet-ai-says'); ?></strong>:
                <?php esc_html_e('Single or bulk generation', 'comet-ai-says'); ?>
            </div>
            <div><span class="mr-1">🌍</span>
                <strong><?php esc_html_e('Multi-Language', 'comet-ai-says'); ?></strong>:
                <?php esc_html_e('14+ languages supported', 'comet-ai-says'); ?>
            </div>
        </div>
    </div>
</div>

<script type="text/javascript">
    jQuery(document).ready(function($) {
        // Provider tile selection
        $('input[name="wpcmt_aisays_settings[provider]"], input[name="wpcmt_aisays_provider"]').on('change',
            function() {
                var provider = $(this).val();
                $('.comet-provider-tile').removeClass('is-selected');
                $(this).closest('.comet-provider-tile').addClass('is-selected');
                if (provider === 'gemini') {
                    $('#gemini-onboarding-section').slideDown(150);
                    $('#openai-onboarding-section').slideUp(150);
                } else {
                    $('#gemini-onboarding-section').slideUp(150);
                    $('#openai-onboarding-section').slideDown(150);
                }
            });

        // Skip onboarding action
        $('#wpcmt-aisays-skip-onboarding').on('click', function(e) {
            e.preventDefault();
            var $btn = $(this);
            $btn.addClass('is-loading');
            $.post(ajaxurl, {
                action: 'wpcmt_aisays_skip_onboarding',
                nonce: '<?php echo esc_js(wp_create_nonce('wpcmt_aisays_skip_onboarding')); ?>'
            }, function() {
                window.location.href =
                    '<?php echo esc_js(admin_url('options-general.php?page=wpcmt-aisays-settings')); ?>';
            }).fail(function() {
                window.location.href =
                    '<?php echo esc_js(admin_url('options-general.php?page=wpcmt-aisays-settings')); ?>';
            });
        });
    });
</script>
<?php
    }

    /**
     * Render common plugin page layout wrapper.
     */
    private function render_page_wrapper(callable $content_callback): void
    {
        echo '<div class="wrap comet-aisays" id="comet-aisays-root">';
        ?>
<script>
    (function() {
        try {
            var theme = localStorage.getItem('comet-theme-mode');
            if (!theme && window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
                theme = 'dark';
            }
            var root = document.getElementById('comet-aisays-root');
            if (root && theme) {
                root.setAttribute('data-theme', theme);
            }
        } catch (e) {}
    })();
</script>
<div class="container is-fluid pt-5">
    <?php
        $this->display_tab_navigation();
        $content_callback();
        echo '</div>'; // close container
        echo '</div>'; // close wrap
    }

    private function render_table_content(ProductsTable $products_table): void
    {
        ?>
    <div id="wpcmt-aisays-bulk-progress" class="box mb-4" style="display: none;">
        <div class="level mb-2 is-mobile">
            <div class="level-left">
                <strong
                    class="is-size-7 mr-2"><?php echo esc_html__('Bulk Generation Progress:', 'comet-ai-says'); ?></strong>
                <span id="wpcmt-aisays-progress-text" class="tag is-primary is-light">0/0</span>
            </div>
            <div class="level-right">
                <button type="button" id="wpcmt-aisays-stop-bulk" class="button is-small is-danger is-outlined">
                    <?php echo esc_html__('Stop Generation', 'comet-ai-says'); ?>
                </button>
            </div>
        </div>
        <progress id="wpcmt-aisays-progress-bar" class="progress is-primary is-small" value="0" max="100">0%</progress>
    </div>

    <div id="wpcmt-aisays-bulk-results" class="mb-4" style="display: none;"></div>

    <form method="get">
        <input type="hidden" name="post_type" value="product" />
        <input type="hidden" name="page" value="wpcmt-aisays-table" />
        <?php
                $products_table->search_box(esc_html__('Search Products', 'comet-ai-says'), 'search');
                $products_table->display();
                ?>
    </form>

    <!-- Description Preview Modal (Bulma Modal) -->
    <div id="wpcmt-aisays-modal" class="modal" style="z-index: 100000;">
        <div class="modal-background" data-modal-close></div>
        <div class="modal-card" style="max-width: 720px; width: 92%; margin: auto;">
            <header class="modal-card-head is-flex is-justify-content-space-between is-align-items-center">
                <div class="is-flex is-align-items-center" style="overflow: hidden;">
                    <span class="dashicons dashicons-format-aside mr-2 has-text-primary"></span>
                    <span
                        class="modal-card-title is-size-5 mb-0 has-text-weight-bold"><?php esc_html_e('AI Generated Description', 'comet-ai-says'); ?></span>
                    <span id="wpcmt-aisays-modal-title" class="tag is-primary is-light ml-2"
                        style="font-size: 12px; max-width: 280px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"></span>
                </div>
                <button type="button" class="delete" aria-label="close" data-modal-close></button>
            </header>
            <section class="modal-card-body" style="max-height: calc(85vh - 140px); overflow-y: auto;">
                <div id="wpcmt-aisays-content" class="content p-3"
                    style="min-height: 180px; white-space: pre-wrap; font-size: 14px; line-height: 1.65;"></div>
            </section>
            <footer class="modal-card-foot is-flex is-justify-content-flex-end p-3">
                <button type="button" class="button is-small is-light mr-2" id="wpcmt-aisays-copy-modal-desc">
                    <span class="dashicons dashicons-admin-page mr-1"
                        style="font-size: 14px; line-height: 14px; width: 14px; height: 14px;"></span>
                    <?php esc_html_e('Copy Description', 'comet-ai-says'); ?>
                </button>
                <button type="button" class="button is-small is-primary is-outlined" data-modal-close>
                    <?php esc_html_e('Close Preview', 'comet-ai-says'); ?>
                </button>
            </footer>
        </div>
    </div>
    <?php
    }

    public static function initialize_usage_stats(string $model): array
    {
        $current_minute = floor(time() / 60);
        $current_day = gmdate('Y-m-d');

        return [
            'requests_this_minute' => 0,
            'tokens_this_minute' => 0,
            'requests_today' => 0,
            'tokens_today' => 0,
            'current_minute' => $current_minute,
            'current_day' => $current_day,
            'model' => $model,
            'limits' => Config::get_model_limits($model),
            'last_updated' => time(),
        ];
    }

    public static function get_model_limits(string $model): array
    {
        return Config::get_model_limits($model);
    }

    public static function display_usage_stats(): void
    {
        $usage_stats = self::get_usage_stats();
        $limits = $usage_stats['limits'];
        $current_provider = Config::get_option(Config::KEY_PROVIDER, 'gemini');

        $rpm_percent = min(100, ($usage_stats['requests_this_minute'] / max(1, $limits['rpm'])) * 100);
        $tpm_percent = min(100, ($usage_stats['tokens_this_minute'] / max(1, $limits['tpm'])) * 100);
        $rpd_percent = min(100, ($usage_stats['requests_today'] / max(1, $limits['rpd'])) * 100);

        $get_progress_class = function ($percent) {
            return $percent > 80 ? 'is-danger' : ($percent > 60 ? 'is-warning' : 'is-success');
        };
        ?>
<div class="box mb-5">
    <div class="level mb-4 is-mobile">
        <div class="level-left">
            <div class="level-item">
                <span class="has-text-weight-bold is-size-5 is-flex is-align-items-center">
                    <span class="dashicons dashicons-chart-bar mr-2 has-text-primary"></span>
                    <?php esc_html_e('API Usage & Rate Limits', 'comet-ai-says'); ?>
                </span>
            </div>
            <div class="level-item">
                <span
                    class="tag is-primary is-light"><?php echo esc_html($usage_stats['model']); ?></span>
            </div>
        </div>
        <div class="level-right">
            <div class="level-item">
                <span class="tag is-info is-light">
                    <?php
                        echo wp_kses_post(sprintf(
                            /* translators: %s: Formatted total number of generated descriptions (HTML strong tag). */
                            __('Total Generated: %s', 'comet-ai-says'),
                            '<strong>' . esc_html(number_format((int) get_option('wpcmt_aisays_total_generations', 0))) . '</strong>'
                        ));
                    ?>
                </span>
            </div>
        </div>
    </div>

    <div class="columns is-variable is-4">
        <div class="column is-8">
            <table class="table is-fullwidth is-striped" style="background: transparent;">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Limit Metric', 'comet-ai-says'); ?>
                        </th>
                        <th><?php esc_html_e('Used', 'comet-ai-says'); ?>
                        </th>
                        <th><?php esc_html_e('Limit', 'comet-ai-says'); ?>
                        </th>
                        <th style="width: 220px;">
                            <?php esc_html_e('Capacity', 'comet-ai-says'); ?>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ([
                        ['rpm', __('Requests/Minute', 'comet-ai-says'), __('Resets every 60s', 'comet-ai-says'), $usage_stats['requests_this_minute'], $limits['rpm'], $rpm_percent],
                        ['tpm', __('Tokens/Minute', 'comet-ai-says'), __('Resets every 60s', 'comet-ai-says'), $usage_stats['tokens_this_minute'], $limits['tpm'], $tpm_percent],
                        ['rpd', __('Requests/Day', 'comet-ai-says'), __('Resets every 24h', 'comet-ai-says'), $usage_stats['requests_today'], $limits['rpd'], $rpd_percent],
                    ] as $row): ?>
                    <tr>
                        <td>
                            <strong><?php echo esc_html($row[1]); ?></strong>
                            <span
                                class="is-size-7 has-text-grey ml-1">(<?php echo esc_html($row[2]); ?>)</span>
                        </td>
                        <td><?php echo esc_html(number_format($row[3])); ?>
                        </td>
                        <td><?php echo esc_html(number_format($row[4])); ?>
                        </td>
                        <td>
                            <div class="is-flex is-align-items-center">
                                <progress
                                    class="progress is-small <?php echo esc_attr($get_progress_class($row[5])); ?> mb-0 mr-2"
                                    value="<?php echo esc_attr($row[5]); ?>"
                                    max="100"></progress>
                                <span class="is-size-7 has-text-weight-semibold"
                                    style="min-width: 42px;"><?php echo esc_html(number_format($row[5], 0)); ?>%</span>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="column is-4">
            <div class="card" style="height: 100%;">
                <div class="card-content">
                    <h3 class="title is-6 mb-2">
                        <?php esc_html_e('API Quota Status', 'comet-ai-says'); ?>
                    </h3>
                    <p class="is-size-7 has-text-grey mb-3">
                        <?php esc_html_e('Minute limits reset every 60 seconds. Daily limits reset every 24 hours (UTC).', 'comet-ai-says'); ?>
                    </p>
                    <p class="is-size-7 mb-3">
                        <strong><?php esc_html_e('Active Provider:', 'comet-ai-says'); ?></strong>
                        <?php echo ('gemini' === $current_provider) ? 'Google Gemini' : 'OpenAI'; ?>
                    </p>
                    <div>
                        <?php if ('gemini' === $current_provider): ?>
                        <a href="https://aistudio.google.com/app/apikey" target="_blank"
                            class="button is-small is-link is-light is-fullwidth">
                            <span class="dashicons dashicons-external mr-1"></span>
                            <?php esc_html_e('Open Google AI Studio', 'comet-ai-says'); ?>
                        </a>
                        <?php else: ?>
                        <a href="https://platform.openai.com/usage" target="_blank"
                            class="button is-small is-link is-light is-fullwidth">
                            <span class="dashicons dashicons-external mr-1"></span>
                            <?php esc_html_e('Open OpenAI Usage Dashboard', 'comet-ai-says'); ?>
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php
    }

    public static function track_usage(string $request_type = 'generation'): void
    {
        $current_provider = Config::get_option(Config::KEY_PROVIDER, 'gemini');
        if ('gemini' !== $current_provider) {
            return;
        }

        $current_model = Config::get_option(Config::KEY_GEMINI_MODEL, 'gemini-3.6-flash');
        $usage_stats = get_transient('wpcmt_aisays_daily_usage') ?: self::initialize_usage_stats($current_model);

        $current_minute = floor(time() / 60);
        $current_day = gmdate('Y-m-d');

        if ($usage_stats['current_minute'] !== $current_minute) {
            $usage_stats['requests_this_minute'] = 0;
            $usage_stats['tokens_this_minute'] = 0;
            $usage_stats['current_minute'] = $current_minute;
        }

        if ($usage_stats['current_day'] !== $current_day) {
            $usage_stats['requests_today'] = 0;
            $usage_stats['tokens_today'] = 0;
            $usage_stats['current_day'] = $current_day;
        }

        if ('generation' === $request_type) {
            $usage_stats['requests_this_minute']++;
            $usage_stats['requests_today']++;
            $tokens_used = 650;
            $usage_stats['tokens_this_minute'] += $tokens_used;
            $usage_stats['tokens_today'] += $tokens_used;
        }

        set_transient('wpcmt_aisays_daily_usage', $usage_stats, DAY_IN_SECONDS);
        update_option('wpcmt_aisays_total_generations', (int) get_option('wpcmt_aisays_total_generations', 0) + 1, false);
    }

    public static function get_usage_stats(): array
    {
        $current_model = Config::get_option(Config::KEY_GEMINI_MODEL, 'gemini-3.6-flash');
        $usage_stats = get_transient('wpcmt_aisays_daily_usage') ?: self::initialize_usage_stats($current_model);

        if ($usage_stats['model'] !== $current_model) {
            $usage_stats['limits'] = Config::get_model_limits($current_model);
            $usage_stats['model'] = $current_model;
            set_transient('wpcmt_aisays_daily_usage', $usage_stats, DAY_IN_SECONDS);
        }

        return $usage_stats;
    }

    public static function rest_get_usage_stats(): \WP_REST_Response
    {
        $usage_stats = self::get_usage_stats();
        $limits = $usage_stats['limits'];
        $current_provider = Config::get_option(Config::KEY_PROVIDER, 'gemini');

        $rpm_limit = max(1, (int) ($limits['rpm'] ?? 15));
        $tpm_limit = max(1, (int) ($limits['tpm'] ?? 1000000));
        $rpd_limit = max(1, (int) ($limits['rpd'] ?? 1500));

        $rpm_used = (int) ($usage_stats['requests_this_minute'] ?? 0);
        $tpm_used = (int) ($usage_stats['tokens_this_minute'] ?? 0);
        $rpd_used = (int) ($usage_stats['requests_today'] ?? 0);

        $rpm_percent = round(min(100, ($rpm_used / $rpm_limit) * 100), 1);
        $tpm_percent = round(min(100, ($tpm_used / $tpm_limit) * 100), 1);
        $rpd_percent = round(min(100, ($rpd_used / $rpd_limit) * 100), 1);

        $get_progress_class = function ($percent) {
            return $percent > 80 ? 'is-danger' : ($percent > 60 ? 'is-warning' : 'is-success');
        };

        $metrics = [
            [
                'key' => 'rpm',
                'label' => __('Requests/Minute', 'comet-ai-says'),
                'reset_text' => __('Resets every 60s', 'comet-ai-says'),
                'used' => number_format($rpm_used),
                'limit' => number_format($rpm_limit),
                'percent' => $rpm_percent,
                'class' => $get_progress_class($rpm_percent),
            ],
            [
                'key' => 'tpm',
                'label' => __('Tokens/Minute', 'comet-ai-says'),
                'reset_text' => __('Resets every 60s', 'comet-ai-says'),
                'used' => number_format($tpm_used),
                'limit' => number_format($tpm_limit),
                'percent' => $tpm_percent,
                'class' => $get_progress_class($tpm_percent),
            ],
            [
                'key' => 'rpd',
                'label' => __('Requests/Day', 'comet-ai-says'),
                'reset_text' => __('Resets every 24h', 'comet-ai-says'),
                'used' => number_format($rpd_used),
                'limit' => number_format($rpd_limit),
                'percent' => $rpd_percent,
                'class' => $get_progress_class($rpd_percent),
            ],
        ];

        $data = [
            'model' => $usage_stats['model'] ?? 'gemini-3.6-flash',
            'provider' => $current_provider,
            'provider_label' => ('gemini' === $current_provider) ? 'Google Gemini' : 'OpenAI GPT',
            'provider_url' => ('gemini' === $current_provider) ? 'https://aistudio.google.com/app/apikey' : 'https://platform.openai.com/usage',
            'provider_btn_text' => ('gemini' === $current_provider) ? __('Open Google AI Studio', 'comet-ai-says') : __('Open OpenAI Dashboard', 'comet-ai-says'),
            'total_generated' => number_format((int) get_option('wpcmt_aisays_total_generations', 0)),
            'metrics' => $metrics,
        ];

        return new \WP_REST_Response($data, 200);
    }

    public function is_plugin_screen(): bool
    {
        if (class_exists(Plugin::class) && method_exists(Plugin::class, 'is_plugin_screen')) {
            return Plugin::is_plugin_screen();
        }

        if (!is_admin()) {
            return false;
        }

        if (function_exists('get_current_screen')) {
            $screen = get_current_screen();
            if ($screen && in_array($screen->id, ['settings_page_wpcmt-aisays-settings', 'product_page_wpcmt-aisays-table'], true)) {
                return true;
            }
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        return isset($_GET['page']) && in_array($_GET['page'], ['wpcmt-aisays-settings', 'wpcmt-aisays-table'], true);
    }

    /**
     * Mute external admin notices from core, themes, and third-party plugins on our plugin screen.
     */
    public function remove_admin_notices(): void
    {
        if ($this->is_plugin_screen()) {
            $this->stash_settings_errors();
            remove_all_actions('admin_notices');
            remove_all_actions('all_admin_notices');
            remove_all_actions('user_admin_notices');
            remove_all_actions('network_admin_notices');
        }
    }

    public function suppress_core_settings_errors(): void
    {
        if ($this->is_plugin_screen()) {
            remove_action('admin_notices', 'settings_errors');
        }
    }

    public function stash_settings_errors(): void
    {
        if ($this->is_plugin_screen()) {
            global $wp_settings_errors;

            $transient_errors = get_transient('settings_errors');
            if (!empty($transient_errors) && is_array($transient_errors)) {
                self::$stashed_settings_errors = array_merge(self::$stashed_settings_errors, $transient_errors);
                delete_transient('settings_errors');
            }

            if (!empty($wp_settings_errors) && is_array($wp_settings_errors)) {
                self::$stashed_settings_errors = array_merge(self::$stashed_settings_errors, $wp_settings_errors);
                $wp_settings_errors = [];
            }
        }
    }

    public function control_notices(): void
    {
        $this->suppress_core_settings_errors();
    }

    public function render_settings_errors(): void
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if (isset($_GET['restored'])) {
            add_settings_error(
                'wpcmt_aisays_settings',
                'settings_restored',
                esc_html__('Default settings have been restored successfully.', 'comet-ai-says'),
                'updated'
            );
        }

        global $wp_settings_errors;
        $all_errors = array_merge(
            self::$stashed_settings_errors,
            is_array($wp_settings_errors) ? $wp_settings_errors : []
        );
        self::$stashed_settings_errors = [];

        if (!empty($all_errors)) {
            $unique_errors = [];
            $seen_keys = [];
            foreach ($all_errors as $err) {
                $dedup_key = ($err['setting'] ?? '').'|'.($err['code'] ?? '').'|'.($err['message'] ?? '');
                if (!isset($seen_keys[$dedup_key])) {
                    $seen_keys[$dedup_key] = true;
                    $unique_errors[] = $err;
                }
            }
            $wp_settings_errors = $unique_errors;
        } else {
            $wp_settings_errors = [];
        }

        settings_errors();
        $wp_settings_errors = [];
    }

    public function show_notices(): void
    {
        $this->render_settings_errors();
    }

    public static function get_language_part(string $language, string $part = 'intro'): string
    {
        return Config::get_language_part($language, $part);
    }

    public function generate_single_ai_description_callback(): void
    {
        AIGenerator::generate_single_ajax();
    }

    public function check_existing_description_callback(): void
    {
        check_ajax_referer('wpcmt_aisays_nonce', 'nonce');

        if (!current_user_can('edit_products')) {
            wp_send_json_error(esc_html__('Permission denied.', 'comet-ai-says'), 403);
        }

        if (empty($_POST['product_id'])) {
            wp_send_json_error(esc_html__('Product ID is required', 'comet-ai-says'));
        }

        $product_id = intval($_POST['product_id']);
        if (!$product_id || !get_post($product_id)) {
            wp_send_json_error(esc_html__('Invalid product ID', 'comet-ai-says'));
        }

        $existing_description = get_post_meta($product_id, '_wpcmt_aisays_description', true);
        $has_valid = AIGenerator::is_valid_description($existing_description);

        wp_send_json_success([
            'has_description' => $has_valid,
            'description' => $has_valid ? $existing_description : '',
        ]);
    }

    public function maybe_restore_defaults(): void
    {
        if (!isset($_POST['restore-defaults'])) {
            return;
        }

        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Security check failed', 'comet-ai-says'));
        }

        check_admin_referer('wpcmt_aisays_settings-options');

        $defaults = Config::get_default_options();
        $current = Config::get_settings();

        // Security & User Experience: NEVER wipe user credentials (API keys) on restore defaults
        $defaults[Config::KEY_GEMINI_KEY] = $current[Config::KEY_GEMINI_KEY] ?? '';
        $defaults[Config::KEY_OPENAI_KEY] = $current[Config::KEY_OPENAI_KEY] ?? '';

        // Explicitly enforce designated default models
        $defaults[Config::KEY_GEMINI_MODEL] = 'gemini-3.6-flash';
        $defaults[Config::KEY_OPENAI_MODEL] = 'gpt-4o';

        Config::update_settings($defaults);
        delete_transient(Config::TRANSIENT_SKIP_ONBOARDING);

        wp_safe_redirect(add_query_arg('restored', 'true', admin_url('options-general.php?page=wpcmt-aisays-settings')));
        exit;
    }

    public function do_activation_redirect(): void
    {
        if (!get_transient(Config::TRANSIENT_ACTIVATION_REDIRECT)) {
            return;
        }

        delete_transient(Config::TRANSIENT_ACTIVATION_REDIRECT);

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ((function_exists('wp_doing_ajax') && wp_doing_ajax()) || (defined('REST_REQUEST') && REST_REQUEST) || (defined('WP_CLI') && WP_CLI) || (function_exists('is_network_admin') && is_network_admin()) || isset($_GET['activate-multi'])) {
            return;
        }

        if (!current_user_can('manage_options')) {
            return;
        }

        wp_safe_redirect(admin_url('options-general.php?page=wpcmt-aisays-settings'));
        exit;
    }

    public function maybe_restart_onboarding(): void
    {
        if (!isset($_GET['restart_onboarding'])) {
            return;
        }

        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Security check failed', 'comet-ai-says'));
        }

        check_admin_referer('wpcmt_restart_onboarding');
        delete_transient(Config::TRANSIENT_SKIP_ONBOARDING);

        wp_safe_redirect(admin_url('options-general.php?page=wpcmt-aisays-settings&onboarding=1'));
        exit;
    }

    public function add_admin_menu(): void
    {
        $p1 = add_options_page(
            esc_html__('AI Product Descriptions Settings', 'comet-ai-says'),
            esc_html__('AI Says Descriptions', 'comet-ai-says'),
            'manage_options',
            'wpcmt-aisays-settings',
            [$this, 'admin_page']
        );

        $p2 = add_submenu_page(
            'edit.php?post_type=product',
            esc_html__('AI Product Descriptions', 'comet-ai-says'),
            esc_html__('AI Says Product Descriptions', 'comet-ai-says'),
            'manage_woocommerce',
            'wpcmt-aisays-table',
            [$this, 'products_table_page']
        );

        Plugin::$plugin_pages['settings'] = $p1;
        Plugin::$plugin_pages['product-descriptions'] = $p2;
    }

    public function register_settings(): void
    {
        // Auto-migrate legacy models
        $saved_model = Config::get_option(Config::KEY_GEMINI_MODEL);
        if ($saved_model) {
            $normalized = Config::normalize_gemini_model($saved_model);
            if ($normalized !== $saved_model) {
                Config::update_setting(Config::KEY_GEMINI_MODEL, $normalized);
            }
        }

        register_setting('wpcmt_aisays_settings', Config::OPTION_SETTINGS, [
            'type' => 'array',
            'default' => Config::get_default_options(),
            'sanitize_callback' => [$this, 'sanitize_settings'],
            'show_in_rest' => false,
        ]);
    }

    public function sanitize_settings($input): array
    {
        if (!is_array($input)) {
            return Config::get_settings();
        }

        $current = Config::get_settings();
        $defaults = Config::get_default_options();

        $sanitized = [];

        // Provider
        $provider = sanitize_text_field($input[Config::KEY_PROVIDER] ?? $current[Config::KEY_PROVIDER] ?? 'gemini');
        $sanitized[Config::KEY_PROVIDER] = ('openai' === $provider) ? 'openai' : 'gemini';

        // API Keys (Preserve if omitted, e.g. from partial form, or sanitize if provided)
        $sanitized[Config::KEY_GEMINI_KEY] = isset($input[Config::KEY_GEMINI_KEY])
            ? sanitize_text_field($input[Config::KEY_GEMINI_KEY])
            : ($current[Config::KEY_GEMINI_KEY] ?? '');

        $sanitized[Config::KEY_OPENAI_KEY] = isset($input[Config::KEY_OPENAI_KEY])
            ? sanitize_text_field($input[Config::KEY_OPENAI_KEY])
            : ($current[Config::KEY_OPENAI_KEY] ?? '');

        // Models
        $sanitized[Config::KEY_GEMINI_MODEL] = $this->sanitize_gemini_model(
            $input[Config::KEY_GEMINI_MODEL] ?? $current[Config::KEY_GEMINI_MODEL] ?? 'gemini-3.6-flash'
        );
        $sanitized[Config::KEY_OPENAI_MODEL] = $this->sanitize_openai_model(
            $input[Config::KEY_OPENAI_MODEL] ?? $current[Config::KEY_OPENAI_MODEL] ?? 'gpt-4o'
        );

        // Language
        $sanitized[Config::KEY_LANGUAGE] = sanitize_text_field(
            $input[Config::KEY_LANGUAGE] ?? $current[Config::KEY_LANGUAGE] ?? 'english'
        );
        $sanitized[Config::KEY_CUSTOM_LANGUAGE] = sanitize_text_field(
            $input[Config::KEY_CUSTOM_LANGUAGE] ?? $current[Config::KEY_CUSTOM_LANGUAGE] ?? ''
        );

        // Prompt Template
        $sanitized[Config::KEY_PROMPT_TEMPLATE] = isset($input[Config::KEY_PROMPT_TEMPLATE])
            ? sanitize_textarea_field($input[Config::KEY_PROMPT_TEMPLATE])
            : ($current[Config::KEY_PROMPT_TEMPLATE] ?? Config::get_default_prompt_template());

        // Display mode & position
        $display_mode = $input[Config::KEY_DISPLAY_MODE] ?? $current[Config::KEY_DISPLAY_MODE] ?? 'automatic';
        $sanitized[Config::KEY_DISPLAY_MODE] = in_array($display_mode, ['automatic', 'manual'], true) ? $display_mode : 'automatic';

        $display_pos = $input[Config::KEY_DISPLAY_POSITION] ?? $current[Config::KEY_DISPLAY_POSITION] ?? 'after_description';
        $sanitized[Config::KEY_DISPLAY_POSITION] = sanitize_text_field($display_pos);

        // Shortcode
        $sanitized[Config::KEY_SHORTCODE] = sanitize_text_field(
            $input[Config::KEY_SHORTCODE] ?? $current[Config::KEY_SHORTCODE] ?? '[comet-ai-says-product-description]'
        );

        // Max Tokens
        $max_tokens = absint($input[Config::KEY_MAX_TOKENS] ?? $current[Config::KEY_MAX_TOKENS] ?? 1500);
        $sanitized[Config::KEY_MAX_TOKENS] = ($max_tokens > 0) ? $max_tokens : 1500;

        // Webhook URL
        $sanitized[Config::KEY_WEBHOOK_URL] = isset($input[Config::KEY_WEBHOOK_URL])
            ? esc_url_raw($input[Config::KEY_WEBHOOK_URL])
            : ($current[Config::KEY_WEBHOOK_URL] ?? '');

        // Critical user experience:
        // If both keys are empty (e.g. user deliberately cleared keys), reset provider to default 'gemini' and remove skip transient!
        if (empty($sanitized[Config::KEY_GEMINI_KEY]) && empty($sanitized[Config::KEY_OPENAI_KEY])) {
            $sanitized[Config::KEY_PROVIDER] = 'gemini';
            delete_transient(Config::TRANSIENT_SKIP_ONBOARDING);
        }

        Config::clear_cache();

        return array_merge($defaults, $sanitized);
    }

    public function sanitize_gemini_model($value): string
    {
        $models = Config::get_gemini_models();
        if (empty($value) || !isset($models[$value])) {
            $existing = Config::get_option(Config::KEY_GEMINI_MODEL, 'gemini-3.6-flash');

            return (!empty($existing) && isset($models[$existing])) ? $existing : 'gemini-3.6-flash';
        }

        return sanitize_text_field($value);
    }

    public function sanitize_openai_model($value): string
    {
        $models = Config::get_openai_models();
        if (empty($value) || !isset($models[$value])) {
            $existing = Config::get_option(Config::KEY_OPENAI_MODEL, 'gpt-4o');

            return (!empty($existing) && isset($models[$existing])) ? $existing : 'gpt-4o';
        }

        return sanitize_text_field($value);
    }

    public function admin_page(): void
    {
        if ($this->needs_onboarding()) {
            echo '<div class="wrap comet-aisays" id="comet-aisays-root">';
            $this->render_onboarding_screen();
            echo '</div>';

            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $current_tab = sanitize_text_field(wp_unslash($_GET['tab'] ?? ''));
        if ('status' === $current_tab) {
            $this->render_page_wrapper(function () {
                $status = new Status();
                $status->render();
            });

            return;
        }

        $this->render_page_wrapper([$this, 'render_settings_form']);
    }

    public function render_settings_form(): void
    {
        $settings = Config::get_settings();
        $current_provider = $settings[Config::KEY_PROVIDER] ?? 'gemini';
        $current_language = $settings[Config::KEY_LANGUAGE] ?? 'english';
        $custom_language = $settings[Config::KEY_CUSTOM_LANGUAGE] ?? '';
        $current_gemini_model = $settings[Config::KEY_GEMINI_MODEL] ?? 'gemini-3.6-flash';
        $current_openai_model = $settings[Config::KEY_OPENAI_MODEL] ?? 'gpt-4o';
        $current_prompt_template = $settings[Config::KEY_PROMPT_TEMPLATE] ?? Config::get_default_prompt_template();
        $current_webhook_url = $settings[Config::KEY_WEBHOOK_URL] ?? '';
        $current_display_mode = $settings[Config::KEY_DISPLAY_MODE] ?? 'automatic';
        $current_display_position = $settings[Config::KEY_DISPLAY_POSITION] ?? 'after_description';
        $current_shortcode = $settings[Config::KEY_SHORTCODE] ?? '[comet-ai-says-product-description]';
        $current_max_tokens = $settings[Config::KEY_MAX_TOKENS] ?? 1500;
        $gemini_key = $settings[Config::KEY_GEMINI_KEY] ?? '';
        $openai_key = $settings[Config::KEY_OPENAI_KEY] ?? '';
        ?>

    <form method="post" action="options.php" autocomplete="off" id="comet-aisays-settings-form" data-lpignore="true"
        data-1p-ignore="true" data-bwignore="true" data-protonpass-ignore="true" data-form-type="other">
        <?php settings_fields('wpcmt_aisays_settings'); ?>
        <?php do_settings_sections('wpcmt_aisays_settings'); ?>

        <div class="columns is-variable is-5">
            <!-- Main Settings Column (8 Columns) -->
            <div class="column is-8">
                <!-- Box 1: AI Provider & Engine -->
                <div class="box mb-5">
                    <h2 class="title is-5 mb-4 is-flex is-align-items-center">
                        <span class="dashicons dashicons-superhero mr-2 has-text-primary"></span>
                        <?php esc_html_e('AI Engine & Authentication', 'comet-ai-says'); ?>
                    </h2>

                    <table class="form-table" style="margin-top: 0;">
                        <!-- Provider Selection -->
                        <tr>
                            <th scope="row">
                                <label
                                    for="wpcmt_aisays_provider"><?php esc_html_e('AI Provider', 'comet-ai-says'); ?></label>
                            </th>
                            <td>
                                <div class="select" style="max-width: 400px;">
                                    <select id="wpcmt_aisays_provider"
                                        name="wpcmt_aisays_settings[<?php echo esc_attr(Config::KEY_PROVIDER); ?>]">
                                        <option value="gemini" <?php selected($current_provider, 'gemini'); ?>>
                                            <?php esc_html_e('Google Gemini (Recommended - Free Tier)', 'comet-ai-says'); ?>
                                        </option>
                                        <option value="openai" <?php selected($current_provider, 'openai'); ?>>
                                            <?php esc_html_e('OpenAI GPT (Paid Tier)', 'comet-ai-says'); ?>
                                        </option>
                                    </select>
                                </div>
                                <p class="help mt-2">
                                    <?php esc_html_e('Choose which AI provider generates descriptions for your store catalog.', 'comet-ai-says'); ?>
                                </p>
                            </td>
                        </tr>

                        <!-- Gemini API Key -->
                        <tr id="gemini-api-key-row"
                            style="<?php echo ('gemini' !== $current_provider) ? 'display: none;' : ''; ?>">
                            <th scope="row">
                                <label
                                    for="wpcmt_aisays_gemini_api_key"><?php esc_html_e('Gemini API Key', 'comet-ai-says'); ?></label>
                            </th>
                            <td>
                                <div class="pw-wrap">
                                    <input type="text" id="wpcmt_aisays_gemini_api_key"
                                        name="wpcmt_aisays_settings[<?php echo esc_attr(Config::KEY_GEMINI_KEY); ?>]"
                                        value="<?php echo esc_attr($gemini_key); ?>"
                                        class="input api-key-field masked" autocomplete="off" autocorrect="off"
                                        autocapitalize="off" spellcheck="false" data-lpignore="true"
                                        data-1p-ignore="true" data-bwignore="true" data-protonpass-ignore="true"
                                        data-form-type="other"
                                        placeholder="<?php esc_attr_e('Paste your Gemini API key', 'comet-ai-says'); ?>" />
                                    <button type="button" class="button is-small is-light toggle-key-visibility"
                                        data-target="wpcmt_aisays_gemini_api_key"
                                        title="<?php esc_attr_e('Show API Key', 'comet-ai-says'); ?>">
                                        <span class="dashicons dashicons-visibility"></span>
                                        <span
                                            class="toggle-key-text"><?php esc_html_e('Show', 'comet-ai-says'); ?></span>
                                    </button>
                                </div>
                                <p class="help mt-1">
                                    <?php esc_html_e('Get your free API key from', 'comet-ai-says'); ?>
                                    <a href="https://aistudio.google.com/app/apikey"
                                        target="_blank"><?php esc_html_e('Google AI Studio', 'comet-ai-says'); ?></a>
                                </p>
                            </td>
                        </tr>

                        <!-- OpenAI API Key -->
                        <tr id="openai-api-key-row"
                            style="<?php echo ('openai' !== $current_provider) ? 'display: none;' : ''; ?>">
                            <th scope="row">
                                <label
                                    for="wpcmt_aisays_openai_api_key"><?php esc_html_e('OpenAI API Key', 'comet-ai-says'); ?></label>
                            </th>
                            <td>
                                <div class="pw-wrap">
                                    <input type="text" id="wpcmt_aisays_openai_api_key"
                                        name="wpcmt_aisays_settings[<?php echo esc_attr(Config::KEY_OPENAI_KEY); ?>]"
                                        value="<?php echo esc_attr($openai_key); ?>"
                                        class="input api-key-field masked" autocomplete="off" autocorrect="off"
                                        autocapitalize="off" spellcheck="false" data-lpignore="true"
                                        data-1p-ignore="true" data-bwignore="true" data-protonpass-ignore="true"
                                        data-form-type="other"
                                        placeholder="<?php esc_attr_e('Paste your OpenAI API key', 'comet-ai-says'); ?>" />
                                    <button type="button" class="button is-small is-light toggle-key-visibility"
                                        data-target="wpcmt_aisays_openai_api_key"
                                        title="<?php esc_attr_e('Show API Key', 'comet-ai-says'); ?>">
                                        <span class="dashicons dashicons-visibility"></span>
                                        <span
                                            class="toggle-key-text"><?php esc_html_e('Show', 'comet-ai-says'); ?></span>
                                    </button>
                                </div>
                                <p class="help mt-1">
                                    <?php esc_html_e('Get your API key from', 'comet-ai-says'); ?>
                                    <a href="https://platform.openai.com/api-keys"
                                        target="_blank"><?php esc_html_e('OpenAI Platform', 'comet-ai-says'); ?></a>
                                </p>
                            </td>
                        </tr>

                        <!-- Gemini Model -->
                        <tr id="gemini-model-row"
                            style="<?php echo ('gemini' !== $current_provider) ? 'display: none;' : ''; ?>">
                            <th scope="row">
                                <label
                                    for="wpcmt_aisays_gemini_model"><?php esc_html_e('Gemini Model', 'comet-ai-says'); ?></label>
                            </th>
                            <td>
                                <?php $this->render_gemini_model_select($current_gemini_model); ?>
                            </td>
                        </tr>

                        <!-- OpenAI Model -->
                        <tr id="openai-model-row"
                            style="<?php echo ('openai' !== $current_provider) ? 'display: none;' : ''; ?>">
                            <th scope="row">
                                <label
                                    for="wpcmt_aisays_openai_model"><?php esc_html_e('OpenAI Model', 'comet-ai-says'); ?></label>
                            </th>
                            <td>
                                <?php $this->render_openai_model_select($current_openai_model); ?>
                            </td>
                        </tr>

                        <!-- Max Tokens Slider -->
                        <tr id="max-tokens-row">
                            <th scope="row">
                                <label
                                    for="wpcmt_aisays_max_tokens"><?php esc_html_e('Max Response Tokens', 'comet-ai-says'); ?></label>
                            </th>
                            <td>
                                <div class="is-flex is-align-items-center" style="max-width: 450px;">
                                    <input type="range" id="wpcmt_aisays_max_tokens"
                                        name="wpcmt_aisays_settings[<?php echo esc_attr(Config::KEY_MAX_TOKENS); ?>]"
                                        min="400" max="4000" step="100"
                                        value="<?php echo esc_attr($current_max_tokens); ?>"
                                        style="flex-grow: 1;" />
                                    <span id="max-tokens-value"
                                        class="tag is-primary is-light ml-3 has-text-weight-bold">
                                        <?php echo esc_html($current_max_tokens); ?>
                                        <?php esc_html_e('tokens', 'comet-ai-says'); ?>
                                    </span>
                                </div>
                                <p class="help mt-2">
                                    <span
                                        id="recommended-tokens"><?php esc_html_e('Maximum tokens allowed per description (recommended: 1500 tokens).', 'comet-ai-says'); ?></span>
                                </p>
                            </td>
                        </tr>

                        <!-- Webhook URL -->
                        <tr id="wpcmt_aisays_webhook_url-row">
                            <th scope="row">
                                <label
                                    for="wpcmt_aisays_webhook_url"><?php esc_html_e('AI Webhook URL', 'comet-ai-says'); ?></label>
                            </th>
                            <td>
                                <input type="url" id="wpcmt_aisays_webhook_url"
                                    name="wpcmt_aisays_settings[<?php echo esc_attr(Config::KEY_WEBHOOK_URL); ?>]"
                                    value="<?php echo esc_attr($current_webhook_url); ?>"
                                    class="input regular-text" autocomplete="off"
                                    placeholder="<?php esc_attr_e('https://your-webhook-endpoint.com/api', 'comet-ai-says'); ?>" />
                                <p class="help mt-1">
                                    <?php esc_html_e('Optional: Dispatches description generation payloads to Zapier, Make, or custom webhook endpoints.', 'comet-ai-says'); ?>
                                </p>
                            </td>
                        </tr>
                    </table>

                    <div class="is-flex is-justify-content-space-between is-align-items-end mt-3 pt-3"
                        style="border-top: 1px solid rgba(0, 0, 0, 0.06);">
                        <span
                            class="is-size-7 has-text-grey"><?php esc_html_e('Skipped or need to reconfigure API keys?', 'comet-ai-says'); ?></span>
                        <a href="<?php echo esc_url(wp_nonce_url(admin_url('options-general.php?page=wpcmt-aisays-settings&restart_onboarding=1'), 'wpcmt_restart_onboarding')); ?>"
                            class="button is-small is-ghost has-text-grey"
                            title="<?php esc_attr_e('Restart setup wizard', 'comet-ai-says'); ?>">
                            <span class="dashicons dashicons-controls-repeat mr-1"
                                style="font-size: 14px; line-height: 1.4;"></span>
                            <?php esc_html_e('Restart Onboarding', 'comet-ai-says'); ?>
                        </a>
                    </div>
                </div>

                <!-- Box 2: Display & Shortcode Settings -->
                <div class="box mb-5">
                    <h2 class="title is-5 mb-4 is-flex is-align-items-center">
                        <span class="dashicons dashicons-visibility mr-2 has-text-info"></span>
                        <?php esc_html_e('Store Display & Placement', 'comet-ai-says'); ?>
                    </h2>
                    <table class="form-table" style="margin-top: 0;">
                        <?php $this->render_display_settings($current_display_mode, $current_display_position, $current_shortcode); ?>
                    </table>
                </div>

                <!-- Box 3: Prompt Template -->
                <div class="box mb-5">
                    <h2 class="title is-5 mb-4 is-flex is-align-items-center">
                        <span class="dashicons dashicons-edit mr-2 has-text-primary"></span>
                        <?php esc_html_e('Prompt Template', 'comet-ai-says'); ?>
                    </h2>
                    <div class="p-1">
                        <?php $this->render_prompt_template($current_prompt_template, $current_language, $custom_language); ?>
                    </div>
                </div>

                <!-- Sticky Form Actions Bar -->
                <div class="comet-form-actions is-flex is-justify-content-space-between is-align-items-center">
                    <div class="buttons mb-0">
                        <button type="submit" class="button is-primary">
                            <span class="dashicons dashicons-saved mr-1"></span>
                            <?php esc_html_e('Save Changes', 'comet-ai-says'); ?>
                        </button>
                        <button type="submit" name="restore-defaults" class="button is-light"
                            onclick="return confirm('<?php echo esc_js(__('Are you sure you want to restore all settings to default values?', 'comet-ai-says')); ?>');">
                            <?php esc_html_e('Restore Defaults', 'comet-ai-says'); ?>
                        </button>
                    </div>
                    <div class="field mb-0">
                        <div class="control has-icons-left">
                            <input type="text" id="comet-settings-search"
                                placeholder="<?php esc_attr_e('Filter settings...', 'comet-ai-says'); ?>"
                                class="input is-small" style="width: 220px;" />
                            <span class="icon is-small is-left">
                                <span class="dashicons dashicons-search"></span>
                            </span>
                        </div>
                    </div>
                </div><!-- /.comet-form-actions -->
            </div><!-- /.column is-8 -->

            <!-- Contextual Sidebar Column (4 Columns) -->
            <div class="column is-4">
                <div class="comet-settings-sidebar">
                    <?php $this->render_setup_instructions_card(); ?>
                    <?php $this->render_support_card(); ?>
                </div>
            </div>
        </div><!-- /.columns -->
    </form>
    <?php
    }

    public function add_meta_box(): void
    {
        add_meta_box(
            'wpcmt_aisays_meta_box',
            esc_html__('AI Product Description', 'comet-ai-says'),
            [$this, 'render_meta_box'],
            'product',
            'normal',
            'high'
        );
    }

    public function render_meta_box($post): void
    {
        $ai_description = get_post_meta($post->ID, '_wpcmt_aisays_description', true);
        if (!AIGenerator::is_valid_description($ai_description)) {
            $ai_description = '';
        }
        $product_language = get_post_meta($post->ID, '_wpcmt_aisays_language', true);
        $global_language = get_option(Config::OPTION_LANGUAGE, 'english');
        $provider = get_option(Config::OPTION_PROVIDER, 'gemini');
        $provider_name = 'gemini' === $provider ? 'Gemini' : 'OpenAI';

        wp_nonce_field('wpcmt_aisays_nonce', 'wpcmt_aisays_nonce');
        ?>
    <div id="wpcmt-aisays-container">
        <p><strong><?php esc_html_e('AI Provider:', 'comet-ai-says'); ?></strong>
            <?php echo esc_html($provider_name); ?>
        </p>

        <div style="margin-bottom: 15px;">
            <label
                for="wpcmt-aisays-language"><strong><?php esc_html_e('Language for this product:', 'comet-ai-says'); ?></strong></label>
            <select id="wpcmt-aisays-language" name="wpcmt_aisays_language" style="margin-left: 10px;">
                <option value="global" <?php selected(empty($product_language) || 'global' === $product_language); ?>>
                    <?php esc_html_e('Use Global Setting', 'comet-ai-says'); ?>
                    (<?php echo esc_html(ucfirst($global_language)); ?>)
                </option>
                <?php foreach (Config::LANGUAGE_DATA as $key => $lang): ?>
                <option value="<?php echo esc_attr($key); ?>" <?php selected($product_language, $key); ?>>
                    <?php echo esc_html($lang[0]); ?>
                </option>
                <?php endforeach; ?>
                <option value="custom" <?php selected($product_language, 'custom'); ?>>
                    <?php esc_html_e('Custom Language', 'comet-ai-says'); ?>
                </option>
            </select>
        </div>

        <div style="margin-bottom: 15px;">
            <button type="button" id="generate-wpcmt-aisays" class="button button-primary"
                data-product-id="<?php echo absint($post->ID); ?>"
                data-product-name="<?php echo esc_attr(get_the_title($post->ID)); ?>">
                <?php esc_html_e('Generate AI Description', 'comet-ai-says'); ?>
            </button>
            <span id="wpcmt-aisays-loading" style="display: none; margin-left: 10px;">
                <?php
                    /* translators: %s: AI provider or model name. */
                    printf(esc_html__('Generating with %s...', 'comet-ai-says'), esc_html($provider_name));
                ?>
                <span class="spinner is-active" style="float: none;"></span>
            </span>
        </div>

        <div id="wpcmt-aisays-result"
            style="<?php echo empty($ai_description) ? 'display: none;' : ''; ?>">
            <textarea id="wpcmt-aisays-text" name="wpcmt_aisays_text" rows="10"
                style="width: 100%; margin-bottom: 10px;"><?php echo esc_textarea($ai_description); ?></textarea>
            <div>
                <button type="button" id="save-wpcmt-aisays" class="button button-secondary"
                    data-product-id="<?php echo absint($post->ID); ?>">
                    <?php esc_html_e('Save AI Description', 'comet-ai-says'); ?>
                </button>
                <button type="button" id="delete-wpcmt-aisays" class="button button-link-delete"
                    data-product-id="<?php echo absint($post->ID); ?>"
                    data-product-name="<?php echo esc_attr(get_the_title($post->ID)); ?>">
                    <?php esc_html_e('Delete AI Description', 'comet-ai-says'); ?>
                </button>
                <span id="wpcmt-aisays-save-status" style="margin-left: 10px;"></span>
            </div>
        </div>

        <div id="wpcmt-aisays-confirm-modal"
            style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999;">
            <div
                style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 20px; border-radius: 5px; width: 80%; max-width: 600px; max-height: 80vh; overflow-y: auto;">
                <h3><?php esc_html_e('AI Description Already Exists', 'comet-ai-says'); ?>
                </h3>
                <p><?php esc_html_e('This product already has an AI description. What would you like to do?', 'comet-ai-says'); ?>
                </p>

                <div style="margin: 15px 0;">
                    <strong><?php esc_html_e('New Description:', 'comet-ai-says'); ?></strong>
                    <div id="wpcmt-aisays-new-content"
                        style="margin: 10px 0; padding: 10px; background: #f9f9f9; border-radius: 4px; max-height: 200px; overflow-y: auto;">
                    </div>
                </div>

                <div style="display: flex; gap: 10px; margin: 20px 0;">
                    <button type="button" id="wpcmt-aisays-replace"
                        class="button button-primary"><?php esc_html_e('Replace Existing', 'comet-ai-says'); ?></button>
                    <button type="button" id="wpcmt-aisays-discard"
                        class="button button-secondary"><?php esc_html_e('Discard New', 'comet-ai-says'); ?></button>
                </div>

                <div style="border-top: 1px solid #ddd; padding-top: 15px;">
                    <button type="button" id="wpcmt-aisays-view-existing"
                        class="button button-link"><?php esc_html_e('View AI desc', 'comet-ai-says'); ?></button>
                </div>
            </div>
        </div>

        <div id="wpcmt-aisays-existing-modal"
            style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 10000;">
            <div
                style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 20px; border-radius: 5px; width: 80%; max-width: 600px; max-height: 80vh; overflow-y: auto;">
                <h3><?php esc_html_e('Existing AI Description', 'comet-ai-says'); ?>
                </h3>
                <div id="wpcmt-aisays-existing-content"
                    style="margin: 15px 0; padding: 15px; background: #f9f9f9; border-radius: 4px; min-height: 200px; white-space: pre-wrap;">
                </div>
                <button type="button" class="button"
                    onclick="document.getElementById('wpcmt-aisays-existing-modal').style.display='none';"><?php esc_html_e('Close', 'comet-ai-says'); ?></button>
            </div>
        </div>
    </div>
    <?php
    }

    public function save_product_language($post_id): void
    {
        if (!isset($_POST['wpcmt_aisays_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['wpcmt_aisays_nonce'])), 'wpcmt_aisays_nonce')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id) || 'product' !== get_post_type($post_id)) {
            return;
        }

        if (isset($_POST['wpcmt_aisays_language'])) {
            $language = sanitize_text_field(wp_unslash($_POST['wpcmt_aisays_language']));
            update_post_meta($post_id, '_wpcmt_aisays_language', $language);
        }
    }

    public function enqueue_admin_scripts($hook): void
    {
        if (Plugin::is_plugin_screen()) {
            $this->enqueue_plugin_admin_styles();
        }

        if (Plugin::is_plugin_screen('settings')) {
            $this->enqueue_admin_settings_js();
        }

        if (Plugin::is_plugin_screen('product-descriptions') || $this->is_product_screen($hook)) {
            $this->enqueue_plugin_admin_scripts();
        }
    }

    public function products_table_page(): void
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $current_tab = sanitize_text_field(wp_unslash($_GET['tab'] ?? ''));
        if ('status' === $current_tab) {
            $this->render_page_wrapper(function () {
                $status = new Status();
                $status->render();
            });

            return;
        }

        if (!class_exists('WooCommerce')) {
            echo '<div class="wrap"><div class="error"><p>'.esc_html__('WooCommerce is required for this page to work.', 'comet-ai-says').'</p></div></div>';

            return;
        }

        $products_table = new ProductsTable();
        $products_table->prepare_items();

        $this->render_page_wrapper(function () use ($products_table) {
            $this->render_table_content($products_table);
        });
    }

    public function handle_bulk_generation(): void
    {
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'bulk-products') || !$this->permission_check()) {
            wp_die(esc_html__('Security check failed', 'comet-ai-says'));
        }

        if (isset($_POST['action']) && 'generate_bulk_ai_descriptions' === $_POST['action'] && !empty($_POST['product_ids'])) {
            $product_ids = array_map('intval', $_POST['product_ids']);
            $results = $this->process_bulk_generation($product_ids);
            wp_send_json_success($results);
        }

        if (!isset($_POST['product_ids']) || !is_array($_POST['product_ids'])) {
            wp_die(esc_html__('Invalid product IDs', 'comet-ai-says'));
        }

        if (!empty($_POST['product_ids'])) {
            $product_ids = array_map('intval', $_POST['product_ids']);
            set_transient('wpcmt_aisays_bulk_ids_'.get_current_user_id(), $product_ids, 5 * MINUTE_IN_SECONDS);

            wp_safe_redirect(add_query_arg([
                'page' => 'wpcmt-aisays-table',
                'bulk_action' => 'generate',
                'count' => count($product_ids),
            ], admin_url('edit.php?post_type=product')));
            exit;
        }

        wp_safe_redirect(admin_url('edit.php?post_type=product&page=wpcmt-aisays-table'));
        exit;
    }

    public static function get_default_prompt_template_public(): string
    {
        return Config::get_default_prompt_template();
    }

    public static function get_lang_static(string $language, string $part = 'intro'): string
    {
        return Config::get_language_part($language, $part);
    }

    public function get_lang(string $language, string $part = 'intro'): string
    {
        return Config::get_language_part($language, $part);
    }

    public function skip_onboarding_callback(): void
    {
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'wpcmt_aisays_skip_onboarding')) {
            wp_send_json_error(esc_html__('Security check failed', 'comet-ai-says'), 403);
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error(esc_html__('Permission denied.', 'comet-ai-says'), 403);
        }

        set_transient(Config::TRANSIENT_SKIP_ONBOARDING, true, 30 * DAY_IN_SECONDS);
        wp_send_json_success();
    }
}
?>