<?php

namespace WpComet\AISays;

use WpComet\AISays\LiveTests\TestRunner;

defined('ABSPATH') || exit;

/**
 * Curated Status & Diagnostics Manager for Comet AI Says.
 */
class Status
{
    /**
     * Get system and environment telemetry data.
     */
    public function get_environment_data(): array
    {
        global $wpdb;

        $provider = Config::get_option(Config::KEY_PROVIDER, 'gemini');
        $model    = ('gemini' === $provider)
            ? Config::get_option(Config::KEY_GEMINI_MODEL, 'gemini-3.6-flash')
            : Config::get_option(Config::KEY_OPENAI_MODEL, 'gpt-4o');

        $gemini_key = Config::get_option(Config::KEY_GEMINI_KEY, '');
        $openai_key = Config::get_option(Config::KEY_OPENAI_KEY, '');
        $active_key = ('gemini' === $provider) ? $gemini_key : $openai_key;

        $key_status = empty($active_key)
            ? __('Not configured', 'comet-ai-says')
            : sprintf(
                /* translators: 1: Key prefix (first 4 characters), 2: Key suffix (last 4 characters). */
                __('Configured (%1$s...%2$s)', 'comet-ai-says'),
                substr($active_key, 0, 4),
                substr($active_key, -4)
            );

        // Catalog coverage metrics
        $wc_active        = class_exists('WooCommerce');
        $total_products   = 0;
        $with_ai_desc     = 0;
        $missing_ai_desc  = 0;
        $coverage_percent = 0;

        if ($wc_active) {
            $count_obj      = wp_count_posts('product');
            $total_products = isset($count_obj->publish) ? (int) $count_obj->publish : 0;

            if ($total_products > 0) {
                // Count products having non-empty _wpcmt_aisays_description meta
                $cache_key    = 'wpcmt_aisays_coverage_count';
                $cached_count = wp_cache_get($cache_key);

                if (false !== $cached_count) {
                    $with_ai_desc = (int) $cached_count;
                } else {
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                    $with_ai_desc = (int) $wpdb->get_var(
                        "SELECT COUNT(DISTINCT pm.post_id) FROM {$wpdb->postmeta} pm
                         INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
                         WHERE pm.meta_key = '_wpcmt_aisays_description'
                         AND pm.meta_value != ''
                         AND p.post_type = 'product'
                         AND p.post_status = 'publish'"
                    );
                    wp_cache_set($cache_key, $with_ai_desc, '', 300);
                }
                $missing_ai_desc  = max(0, $total_products - $with_ai_desc);
                $coverage_percent = round(($with_ai_desc / $total_products) * 100, 1);
            }
        }

        // Image pipeline capabilities
        $gd_installed      = extension_loaded('gd') && function_exists('gd_info');
        $imagick_installed = extension_loaded('imagick') && class_exists('Imagick');

        $supported_formats = [];
        if (function_exists('imagetypes')) {
            $types = imagetypes();
            if ($types & IMG_JPEG) $supported_formats[] = 'JPEG';
            if ($types & IMG_PNG)  $supported_formats[] = 'PNG';
            if ($types & IMG_WEBP) $supported_formats[] = 'WebP';
            if (defined('IMG_AVIF') && ($types & IMG_AVIF)) $supported_formats[] = 'AVIF';
        }

        $server_software = isset($_SERVER['SERVER_SOFTWARE']) ? sanitize_text_field(wp_unslash($_SERVER['SERVER_SOFTWARE'])) : 'Unknown';

        return [
            'ai_engine' => [
                'title'  => __('AI Engine & Quotas', 'comet-ai-says'),
                'fields' => [
                    __('Active Provider', 'comet-ai-says')         => ('gemini' === $provider) ? 'Google Gemini' : 'OpenAI GPT',
                    __('Primary Model', 'comet-ai-says')           => $model,
                    __('API Key Status', 'comet-ai-says')          => $key_status,
                    __('Fallback Models', 'comet-ai-says')         => ('gemini' === $provider) ? 'gemini-3.6-flash, gemini-3.5-flash, gemini-2.5-flash' : 'gpt-4o-mini',
                    __('Target Language', 'comet-ai-says')         => ucfirst(Config::get_option(Config::KEY_LANGUAGE, 'english')),
                    __('Max Token Limit', 'comet-ai-says')         => Config::get_option(Config::KEY_MAX_TOKENS, 1500) . ' tokens',
                    __('Prompt Template Size', 'comet-ai-says')    => strlen(Config::get_option(Config::KEY_PROMPT_TEMPLATE, Config::get_default_prompt_template())) . ' chars',
                    __('Lifetime Generations', 'comet-ai-says')    => number_format((int) get_option('wpcmt_aisays_total_generations', 0)),
                ],
            ],
            'catalog_coverage' => [
                'title'  => __('WooCommerce Catalog Coverage', 'comet-ai-says'),
                'fields' => [
                    /* translators: %s: WooCommerce version number. */
                    __('WooCommerce Status', 'comet-ai-says')      => $wc_active ? sprintf(__('Active (v%s)', 'comet-ai-says'), WC_VERSION) : __('Inactive / Not installed', 'comet-ai-says'),
                    __('High-Performance Storage', 'comet-ai-says')=> class_exists('\Automattic\WooCommerce\Utilities\OrderUtil') && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() ? __('HPOS Enabled', 'comet-ai-says') : __('Standard / Legacy Posts', 'comet-ai-says'),
                    __('Published Products', 'comet-ai-says')      => number_format($total_products),
                    __('With AI Descriptions', 'comet-ai-says')    => number_format($with_ai_desc),
                    __('Missing AI Descriptions', 'comet-ai-says') => number_format($missing_ai_desc),
                    __('Catalog AI Coverage', 'comet-ai-says')     => $coverage_percent . '%',
                ],
            ],
            'image_pipeline' => [
                'title'  => __('Server Vision & Image Pipeline', 'comet-ai-says'),
                'fields' => [
                    __('GD Extension', 'comet-ai-says')            => $gd_installed ? __('Installed', 'comet-ai-says') : __('Not installed', 'comet-ai-says'),
                    __('Imagick Extension', 'comet-ai-says')       => $imagick_installed ? __('Installed', 'comet-ai-says') : __('Not installed', 'comet-ai-says'),
                    __('Supported Encoders', 'comet-ai-says')      => !empty($supported_formats) ? implode(', ', $supported_formats) : __('None detected', 'comet-ai-says'),
                    __('AVIF Vision Support', 'comet-ai-says')     => in_array('AVIF', $supported_formats, true) ? __('Supported', 'comet-ai-says') : __('Not supported (fallback to JPEG downsample)', 'comet-ai-says'),
                    __('WebP Vision Support', 'comet-ai-says')     => in_array('WebP', $supported_formats, true) ? __('Supported', 'comet-ai-says') : __('Not supported', 'comet-ai-says'),
                    __('Max Upload Size', 'comet-ai-says')         => size_format(wp_max_upload_size()),
                ],
            ],
            'server_environment' => [
                'title'  => __('WordPress & Server Environment', 'comet-ai-says'),
                'fields' => [
                    __('Plugin Version', 'comet-ai-says')          => 'v' . COMET_AI_SAYS_VERSION,
                    __('WordPress Version', 'comet-ai-says')       => get_bloginfo('version'),
                    __('PHP Version', 'comet-ai-says')             => PHP_VERSION,
                    __('WP Memory Limit', 'comet-ai-says')         => WP_MEMORY_LIMIT,
                    __('PHP Time Limit', 'comet-ai-says')          => ini_get('max_execution_time') . 's',
                    __('WP Debug Mode', 'comet-ai-says')           => (defined('WP_DEBUG') && WP_DEBUG) ? 'Yes' : 'No',
                    __('Server Software', 'comet-ai-says')         => $server_software,
                    __('MySQL Version', 'comet-ai-says')           => $wpdb->db_version(),
                ],
            ],
        ];
    }

    /**
     * Generate raw text report for support.
     */
    public function get_raw_report(array $data): string
    {
        $report = "### COMET AI SAYS: SYSTEM STATUS & DIAGNOSTICS REPORT ###\n";
        $report .= "Generated: " . gmdate('Y-m-d H:i:s') . " UTC\n\n";

        foreach ($data as $section) {
            $report .= "--- " . strtoupper($section['title']) . " ---\n";
            foreach ($section['fields'] as $key => $value) {
                $clean_val = wp_strip_all_tags((string) $value);
                $report .= str_pad($key . ':', 30) . $clean_val . "\n";
            }
            $report .= "\n";
        }

        return $report;
    }

    /**
     * Render the Status & Diagnostics tab UI.
     */
    public function render(): void
    {
        $data       = $this->get_environment_data();
        $raw_report = $this->get_raw_report($data);
        $test_runner = TestRunner::get_instance();
        $tests      = $test_runner->get_test_list();

        $catalog = $data['catalog_coverage']['fields'];
        $coverage_pct = (float) rtrim($catalog[__('Catalog AI Coverage', 'comet-ai-says')], '%');
        ?>
        <div id="tab-content-status" class="comet-status-tab">
            <!-- Header Level Bar -->
            <div class="level mb-5">
                <div class="level-left">
                    <div>
                        <h2 class="title is-4 mb-1 has-text-weight-bold is-flex is-align-items-center">
                            <span class="dashicons dashicons-heart mr-2 has-text-primary"></span>
                            <?php esc_html_e('System Status & Live Diagnostics', 'comet-ai-says'); ?>
                        </h2>
                        <p class="subtitle is-6 has-text-grey">
                            <?php esc_html_e('Real-time API connectivity, rate limit checks, catalog coverage, and image downsampling health.', 'comet-ai-says'); ?>
                        </p>
                    </div>
                </div>
                <div class="level-right">
                    <button type="button" id="comet-run-all-tests-btn" class="button is-primary mr-2">
                        <span class="dashicons dashicons-controls-play mr-1"></span>
                        <span><?php esc_html_e('Run All Live Tests', 'comet-ai-says'); ?></span>
                    </button>
                    <button type="button" id="comet-copy-report-btn" class="button is-light">
                        <span class="dashicons dashicons-clipboard mr-1"></span>
                        <span><?php esc_html_e('Copy for Support', 'comet-ai-says'); ?></span>
                    </button>
                </div>
            </div>

            <!-- Hidden Raw Report Textarea -->
            <textarea id="comet-status-report-raw" style="display:none;"><?php echo esc_textarea($raw_report); ?></textarea>

            <!-- Interactive Live Diagnostic Suite Box -->
            <div class="box mb-5 p-5" id="comet-live-tests-box" style="border: 1px solid var(--comet-border, #e2e8f0); border-radius: 8px;">
                <div class="level mb-4 is-mobile">
                    <div class="level-left">
                        <div class="is-flex is-align-items-center">
                            <span class="dashicons dashicons-shield mr-2 has-text-primary" style="font-size: 22px; width: 22px; height: 22px;"></span>
                            <h3 class="title is-5 mb-0 has-text-weight-bold"><?php esc_html_e('Live Environment & Diagnostic Suite', 'comet-ai-says'); ?></h3>
                        </div>
                    </div>
                    <div class="level-right">
                        <div class="tags are-medium mb-0">
                            <span class="tag is-light"><?php
                                /* translators: %d: Total number of diagnostic tests. */
                                printf(esc_html__('Total: %d', 'comet-ai-says'), count($tests));
                            ?></span>
                            <span class="tag is-success is-light" id="comet-diag-passed-badge"><?php esc_html_e('Passed: 0', 'comet-ai-says'); ?></span>
                            <span class="tag is-warning is-light" id="comet-diag-warned-badge"><?php esc_html_e('Warnings: 0', 'comet-ai-says'); ?></span>
                            <span class="tag is-danger is-light" id="comet-diag-failed-badge"><?php esc_html_e('Failed: 0', 'comet-ai-says'); ?></span>
                        </div>
                    </div>
                </div>

                <!-- Progress Bar -->
                <progress id="comet-diag-progress-bar" class="progress is-primary is-small mb-4" value="0" max="<?php echo esc_attr(count($tests)); ?>" style="display:none;"></progress>

                <!-- Test Cards List -->
                <div class="comet-diag-tests-list">
                    <?php foreach ($tests as $index => $test): ?>
                    <div class="box mb-3 p-4 comet-diag-test-item" id="diag-item-<?php echo esc_attr($test['key']); ?>" data-test-key="<?php echo esc_attr($test['key']); ?>" style="background: var(--comet-surface-subtle, #f8fafc); border: 1px solid var(--comet-border, #edf2f7);">
                        <div class="level mb-2 is-mobile">
                            <div class="level-left">
                                <div class="is-flex is-align-items-center">
                                    <span class="dashicons <?php echo esc_attr($test['icon']); ?> mr-3 has-text-grey" style="font-size: 20px; width: 20px; height: 20px;"></span>
                                    <div>
                                        <h4 class="has-text-weight-bold is-size-6 mb-0"><?php echo esc_html($test['name']); ?></h4>
                                        <p class="is-size-7 has-text-grey mb-0"><?php echo esc_html($test['description']); ?></p>
                                    </div>
                                </div>
                            </div>
                            <div class="level-right">
                                <span class="tag is-light is-rounded mr-2 comet-test-badge" id="badge-<?php echo esc_attr($test['key']); ?>">
                                    <?php esc_html_e('READY', 'comet-ai-says'); ?>
                                </span>
                                <span class="is-size-7 has-text-grey mr-3 comet-test-latency" id="latency-<?php echo esc_attr($test['key']); ?>" style="min-width: 55px; text-align: right; display: none;"></span>
                                <button type="button" class="button is-small is-outlined is-primary comet-run-single-test-btn" data-key="<?php echo esc_attr($test['key']); ?>">
                                    <span class="dashicons dashicons-update" style="font-size: 14px; width: 14px; height: 14px;"></span>
                                </button>
                            </div>
                        </div>
                        <div class="comet-diag-steps mt-3 p-3 is-size-7" id="steps-<?php echo esc_attr($test['key']); ?>" style="display:none; background: rgba(0,0,0,0.03); border-radius: 4px; font-family: monospace; line-height: 1.6;"></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Telemetry Grid -->
            <div class="columns is-multiline is-variable is-4">
                <!-- Box 1: AI Engine & Quota Status -->
                <div class="column is-6">
                    <div class="box p-5 h-100" style="border: 1px solid var(--comet-border, #e2e8f0); border-radius: 8px;">
                        <h3 class="title is-5 mb-4 is-flex is-align-items-center has-text-weight-bold">
                            <span class="dashicons dashicons-superhero mr-2 has-text-primary"></span>
                            <?php echo esc_html($data['ai_engine']['title']); ?>
                        </h3>
                        <table class="table is-fullwidth is-striped" style="background: transparent;">
                            <tbody>
                                <?php foreach ($data['ai_engine']['fields'] as $key => $val): ?>
                                <tr>
                                    <td style="width: 40%; font-weight: 600;"><?php echo esc_html($key); ?></td>
                                    <td>
                                        <?php if (strpos($key, 'API Key') !== false && strpos($val, 'Not configured') !== false): ?>
                                            <span class="tag is-danger is-light"><?php echo esc_html($val); ?></span>
                                        <?php elseif (strpos($key, 'API Key') !== false): ?>
                                            <span class="tag is-success is-light"><?php echo esc_html($val); ?></span>
                                        <?php elseif (strpos($key, 'Primary Model') !== false): ?>
                                            <code><?php echo esc_html($val); ?></code>
                                        <?php else: ?>
                                            <?php echo esc_html($val); ?>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Box 2: Catalog Coverage -->
                <div class="column is-6">
                    <div class="box p-5 h-100" style="border: 1px solid var(--comet-border, #e2e8f0); border-radius: 8px;">
                        <div class="level mb-3 is-mobile">
                            <div class="level-left">
                                <h3 class="title is-5 mb-0 is-flex is-align-items-center has-text-weight-bold">
                                    <span class="dashicons dashicons-products mr-2 has-text-primary"></span>
                                    <?php echo esc_html($data['catalog_coverage']['title']); ?>
                                </h3>
                            </div>
                            <div class="level-right">
                                <a href="<?php echo esc_url(admin_url('edit.php?post_type=product&page=wpcmt-aisays-table')); ?>" class="button is-small is-primary is-outlined">
                                    <span class="dashicons dashicons-external mr-1" style="font-size: 13px; width: 13px; height: 13px;"></span>
                                    <?php esc_html_e('Manage Catalog Table', 'comet-ai-says'); ?>
                                </a>
                            </div>
                        </div>

                        <!-- Visual Coverage Meter -->
                        <div class="p-3 mb-4" style="background: var(--comet-surface-subtle, #f8fafc); border-radius: 6px;">
                            <div class="is-flex is-justify-content-space-between is-align-items-center mb-1">
                                <span class="has-text-weight-bold is-size-7"><?php esc_html_e('AI Catalog Coverage Progress:', 'comet-ai-says'); ?></span>
                                <span class="tag is-primary has-text-weight-bold"><?php echo esc_html($coverage_pct); ?>%</span>
                            </div>
                            <progress class="progress is-primary is-small mb-2" value="<?php echo esc_attr($coverage_pct); ?>" max="100"></progress>
                            <p class="is-size-7 has-text-grey mb-0">
                                <?php
                                    echo wp_kses_post(sprintf(
                                        /* translators: 1: Number of products with AI descriptions (HTML strong tag), 2: Total number of published products (HTML strong tag). */
                                        __('%1$s of %2$s published store products have an AI-enhanced description.', 'comet-ai-says'),
                                        '<strong>' . esc_html($catalog[__('With AI Descriptions', 'comet-ai-says')]) . '</strong>',
                                        '<strong>' . esc_html($catalog[__('Published Products', 'comet-ai-says')]) . '</strong>'
                                    ));
                                ?>
                            </p>
                        </div>

                        <table class="table is-fullwidth is-striped" style="background: transparent;">
                            <tbody>
                                <?php foreach ($data['catalog_coverage']['fields'] as $key => $val): ?>
                                <tr>
                                    <td style="width: 40%; font-weight: 600;"><?php echo esc_html($key); ?></td>
                                    <td><?php echo esc_html($val); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Box 3: Image & Vision Processing Pipeline -->
                <div class="column is-6">
                    <div class="box p-5 h-100" style="border: 1px solid var(--comet-border, #e2e8f0); border-radius: 8px;">
                        <h3 class="title is-5 mb-4 is-flex is-align-items-center has-text-weight-bold">
                            <span class="dashicons dashicons-format-image mr-2 has-text-primary"></span>
                            <?php echo esc_html($data['image_pipeline']['title']); ?>
                        </h3>
                        <table class="table is-fullwidth is-striped" style="background: transparent;">
                            <tbody>
                                <?php foreach ($data['image_pipeline']['fields'] as $key => $val): ?>
                                <tr>
                                    <td style="width: 40%; font-weight: 600;"><?php echo esc_html($key); ?></td>
                                    <td>
                                        <?php if (strpos($val, 'Supported') !== false && strpos($val, 'Not') === false): ?>
                                            <span class="tag is-success is-light"><?php echo esc_html($val); ?></span>
                                        <?php elseif (strpos($val, 'Installed') !== false && strpos($val, 'Not') === false): ?>
                                            <span class="tag is-success is-light"><?php echo esc_html($val); ?></span>
                                        <?php elseif (strpos($val, 'Not') !== false): ?>
                                            <span class="tag is-warning is-light"><?php echo esc_html($val); ?></span>
                                        <?php else: ?>
                                            <?php echo esc_html($val); ?>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Box 4: Server & WordPress Environment -->
                <div class="column is-6">
                    <div class="box p-5 h-100" style="border: 1px solid var(--comet-border, #e2e8f0); border-radius: 8px;">
                        <h3 class="title is-5 mb-4 is-flex is-align-items-center has-text-weight-bold">
                            <span class="dashicons dashicons-admin-settings mr-2 has-text-primary"></span>
                            <?php echo esc_html($data['server_environment']['title']); ?>
                        </h3>
                        <table class="table is-fullwidth is-striped" style="background: transparent;">
                            <tbody>
                                <?php foreach ($data['server_environment']['fields'] as $key => $val): ?>
                                <tr>
                                    <td style="width: 40%; font-weight: 600;"><?php echo esc_html($key); ?></td>
                                    <td>
                                        <?php if ('WP Debug Mode' === $key && 'Yes' === $val): ?>
                                            <span class="tag is-warning is-light"><?php echo esc_html($val); ?></span>
                                        <?php elseif ('PHP Version' === $key && version_compare($val, '7.4', '<')): ?>
                                            <span class="tag is-danger is-light"><?php echo esc_html($val); ?> (Outdated)</span>
                                        <?php else: ?>
                                            <?php echo esc_html($val); ?>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <script>
        document.addEventListener('DOMContentLoaded', function() {
            var copyBtn = document.getElementById('comet-copy-report-btn');
            if (copyBtn) {
                copyBtn.addEventListener('click', function() {
                    var textarea = document.getElementById('comet-status-report-raw');
                    if (!textarea) return;
                    textarea.style.display = 'block';
                    textarea.select();
                    try {
                        var success = document.execCommand('copy');
                        if (success) {
                            var originalHtml = copyBtn.innerHTML;
                            copyBtn.innerHTML = '<span class="dashicons dashicons-saved mr-1"></span><span><?php echo esc_js(__('Copied!', 'comet-ai-says')); ?></span>';
                            copyBtn.classList.replace('is-light', 'is-success');
                            setTimeout(function() {
                                copyBtn.innerHTML = originalHtml;
                                copyBtn.classList.replace('is-success', 'is-light');
                            }, 2500);
                        }
                    } catch(err) {}
                    textarea.style.display = 'none';
                });
            }

            // Live Diagnostics Execution Engine
            var runAllBtn = document.getElementById('comet-run-all-tests-btn');
            var progressBar = document.getElementById('comet-diag-progress-bar');
            var passedBadge = document.getElementById('comet-diag-passed-badge');
            var warnedBadge = document.getElementById('comet-diag-warned-badge');
            var failedBadge = document.getElementById('comet-diag-failed-badge');

            var testKeys = <?php echo wp_json_encode(array_column($tests, 'key')); ?>;
            var isRunning = false;
            var passCount = 0;
            var warnCount = 0;
            var failCount = 0;

            function updateSummaryBadges() {
                if (passedBadge) passedBadge.textContent = '<?php echo esc_js(__('Passed: ', 'comet-ai-says')); ?>' + passCount;
                if (warnedBadge) warnedBadge.textContent = '<?php echo esc_js(__('Warnings: ', 'comet-ai-says')); ?>' + warnCount;
                if (failedBadge) failedBadge.textContent = '<?php echo esc_js(__('Failed: ', 'comet-ai-says')); ?>' + failCount;
            }

            function setTestStatus(key, status, latency, steps, resultText) {
                var badge = document.getElementById('badge-' + key);
                var latencyEl = document.getElementById('latency-' + key);
                var stepsEl = document.getElementById('steps-' + key);

                if (!badge) return;

                badge.className = 'tag is-rounded mr-2 comet-test-badge';
                if (status === 'running') {
                    badge.classList.add('is-info');
                    badge.textContent = '<?php echo esc_js(__('RUNNING...', 'comet-ai-says')); ?>';
                } else if (status === 'pass') {
                    badge.classList.add('is-success');
                    badge.textContent = 'PASS';
                    passCount++;
                } else if (status === 'warn') {
                    badge.classList.add('is-warning');
                    badge.textContent = 'WARN';
                    warnCount++;
                } else {
                    badge.classList.add('is-danger');
                    badge.textContent = 'FAIL';
                    failCount++;
                }

                if (latencyEl && latency !== null && latency !== undefined) {
                    latencyEl.textContent = latency + 'ms';
                    latencyEl.style.display = 'inline-block';
                }

                if (stepsEl && steps && steps.length > 0) {
                    var html = '';
                    steps.forEach(function(s) {
                        html += '<div>&bull; ' + s + '</div>';
                    });
                    if (resultText) {
                        html += '<div style="margin-top:4px; font-weight:bold;">&rarr; ' + resultText + '</div>';
                    }
                    stepsEl.innerHTML = html;
                    stepsEl.style.display = 'block';
                }
                updateSummaryBadges();
            }

            function runSingleTest(key) {
                setTestStatus(key, 'running', null, [], null);

                var params = new URLSearchParams();
                params.append('action', 'wpcmt_aisays_run_live_test');
                params.append('test_key', key);
                params.append('nonce', typeof wpcmt_aisays !== 'undefined' ? wpcmt_aisays.nonce : '');

                return fetch(ajaxurl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                    body: params.toString()
                })
                .then(function(r) { return r.json(); })
                .then(function(res) {
                    if (res && res.success && res.data) {
                        var d = res.data;
                        setTestStatus(key, d.status || 'pass', d.latency, d.steps || [], d.result || '');
                    } else {
                        var msg = (res && res.data && res.data.message) ? res.data.message : 'Execution failed';
                        setTestStatus(key, 'error', 0, [msg], msg);
                    }
                })
                .catch(function(err) {
                    setTestStatus(key, 'error', 0, [err.message], err.message);
                });
            }

            // Single test rerun buttons
            document.querySelectorAll('.comet-run-single-test-btn').forEach(function(btn) {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    var key = this.getAttribute('data-key');
                    if (key) {
                        runSingleTest(key);
                    }
                });
            });

            // Run all live tests sequentially
            if (runAllBtn) {
                runAllBtn.addEventListener('click', function() {
                    if (isRunning) return;
                    isRunning = true;
                    runAllBtn.classList.add('is-loading');
                    runAllBtn.disabled = true;

                    passCount = 0;
                    warnCount = 0;
                    failCount = 0;
                    updateSummaryBadges();

                    if (progressBar) {
                        progressBar.style.display = 'block';
                        progressBar.value = 0;
                    }

                    var chain = Promise.resolve();
                    var completed = 0;

                    testKeys.forEach(function(k) {
                        chain = chain.then(function() {
                            return runSingleTest(k).then(function() {
                                completed++;
                                if (progressBar) progressBar.value = completed;
                            });
                        });
                    });

                    chain.then(function() {
                        isRunning = false;
                        runAllBtn.classList.remove('is-loading');
                        runAllBtn.disabled = false;
                    });
                });
            }
        });
        </script>
        <?php
    }
}
