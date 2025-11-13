<?php

namespace WpComet\AISays;

class AdminInterface {
    private const MODEL_LIMITS = [
        '2.5-pro' => ['rpm' => 5, 'tpm' => 125000, 'rpd' => 100],
        '2.5-flash' => ['rpm' => 10, 'tpm' => 250000, 'rpd' => 250],
        '2.5-flash-lite' => ['rpm' => 15, 'tpm' => 250000, 'rpd' => 1000],
        '2.0-flash' => ['rpm' => 15, 'tpm' => 1000000, 'rpd' => 200],
        '2.0-flash-lite' => ['rpm' => 30, 'tpm' => 1000000, 'rpd' => 200],
    ];

    private const LANGUAGE_DATA = [
        'english' => ['English', 'English'],
        'spanish' => ['Spanish', 'Español'],
        'french' => ['French', 'Français'],
        'german' => ['German', 'Deutsch'],
        'italian' => ['Italian', 'Italiano'],
        'portuguese' => ['Portuguese', 'Português'],
        'dutch' => ['Dutch', 'Nederlands'],
        'russian' => ['Russian', 'Русский'],
        'japanese' => ['Japanese', '日本語'],
        'korean' => ['Korean', '한국어'],
        'chinese' => ['Chinese', '中文'],
        'arabic' => ['Arabic', 'العربية'],
        'turkish' => ['Turkish', 'Türkçe'],
        'hindi' => ['Hindi', 'हिन्दी'],
    ];

    public function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('add_meta_boxes', [$this, 'add_meta_box']);
        add_action('admin_notices', [$this, 'control_notices'], 1);
        //    add_action('load-settings_page_wpcmt-aisays-settings', [$this, 'control_notices']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
        add_action('admin_post_generate_bulk_ai_descriptions', [$this, 'handle_bulk_generation']);
        add_action('admin_init', [$this, 'maybe_restore_defaults']);
        add_action('wp_ajax_check_existing_description', [$this, 'check_existing_description_callback']);
        add_action('wp_ajax_generate_single_ai_description', [$this, 'generate_single_ai_description_callback']);
        add_action('save_post_product', [$this, 'save_product_language']);
    }

    /**
     * Get plugin asset URL.
     */
    private function get_asset_url(string $path): string {
        return plugin_dir_url(__FILE__).'../assets/'.ltrim($path, '/');
    }

    /**
     * Get plugin path.
     */
    private function get_plugin_path(string $path = ''): string {
        return plugin_dir_path(__FILE__).'../'.ltrim($path, '/');
    }

    /**
     * Get plugin version.
     */
    private function get_plugin_version(): string {
        if (!function_exists('get_plugin_data')) {
            require_once ABSPATH.'wp-admin/includes/plugin.php';
        }

        $plugin_data = get_plugin_data($this->get_plugin_path('comet-ai-says.php'));

        return $plugin_data['Version'] ?? '1.0.0';
    }

    /**
     * Enqueue AI scripts with localization.
     */
    private function enqueue_ai_scripts(): void {
        wp_enqueue_script(
            'wpcmt-aisays-admin',
            $this->get_asset_url('admin.js'),
            ['jquery'],
            $this->get_plugin_version(),
            true
        );

        wp_localize_script('wpcmt-aisays-admin', 'wpcmtAISays', $this->get_script_localization_data());
    }

    /**
     * Get script localization data.
     */
    private function get_script_localization_data(): array {
        return [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wpcmt_aisays_nonce'),
            'bulk_nonce' => wp_create_nonce('wpcmt_aisays_bulk_nonce'),
            'i18n' => [
                'generate_error' => esc_html__('Error: ', 'comet-ai-says'),
                'generate_error_generic' => esc_html__('An error occurred while generating the description.', 'comet-ai-says'),
                'saving' => esc_html__('Saving...', 'comet-ai-says'),
                'saved' => esc_html__('Saved!', 'comet-ai-says'),
                'save_error' => esc_html__('Error saving', 'comet-ai-says'),
                'generating' => esc_html__('Generating...', 'comet-ai-says'),
                'generate_ai_description' => esc_html__('Generate AI Description', 'comet-ai-says'),
                // translators: %s: Product name
                'generated_success' => esc_html__('AI description generated and saved for: %s', 'comet-ai-says'),
                // translators: %s: Product name
                'save_error_specific' => esc_html__('Error saving description for: %s', 'comet-ai-says'),
                // translators: %s: Product name
                'generate_error_specific' => esc_html__('Error generating description for: %s', 'comet-ai-says'),
                // translators: %s: Product name
                'generate_error_generic_specific' => esc_html__('An error occurred while generating description for: %s', 'comet-ai-says'),
                'view_error' => esc_html__('Error loading AI description', 'comet-ai-says'),
                'no_products_selected' => esc_html__('Please select at least one product.', 'comet-ai-says'),
                // translators: %d: Number of products
                'bulk_confirm' => esc_html__('Generate AI descriptions for %d selected products?', 'comet-ai-says'),
                'completed' => esc_html__('Completed!', 'comet-ai-says'),
                // translators: %d: Number of products
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
                'regenerate' => esc_html__('Regenerate', 'comet-ai-says'),
            ],
        ];
    }

    /**
     * Get default prompt template.
     */
    private function get_default_prompt_template(): string {
        return '{introduction} 
for: {product_name}
Existing information: {short_description}

Categories: {categories}

Product specifications:
{attributes}

Visual context: {image_analysis}

{instructions}';
    }

    /**
     * Display tab navigation.
     */
    private function display_tab_navigation(): void {
        // phpcs:disable WordPress.Security.NonceVerification.Recommended
        $current_page = sanitize_text_field(wp_unslash($_GET['page'] ?? ''));
        $settings_url = admin_url('options-general.php?page=wpcmt-aisays-settings');
        $products_url = admin_url('edit.php?post_type=product&page=wpcmt-aisays-table');
        ?>
<div class="branding">
	<h1>
		<img src="<?php echo esc_url($this->get_asset_url('solo-color.svg')); ?>"
			width="32" height="32" alt="WpComet Icon" />
		Wpcomet - 🤖 Comet AI Says: Product Descriptions
		<span id="wpcmt-aisays-bulk-loading" style="display: none; margin-left: 10px;">
			<span class="spinner is-active"></span>
			<?php esc_html_e('Generating descriptions...', 'comet-ai-says'); ?>
		</span>
	</h1>
</div>
<div class="wpcmt-aisays-tabs" style="margin: 15px 0 20px 0;">
	<h2 class="nav-tab-wrapper">
		<a href="<?php echo esc_url($settings_url); ?>"
			class="nav-tab <?php echo ('wpcmt-aisays-settings' === $current_page) ? 'nav-tab-active' : ''; ?>">
			<?php esc_html_e('Settings', 'comet-ai-says'); ?>
		</a>
		<a href="<?php echo esc_url($products_url); ?>"
			class="nav-tab <?php echo ('wpcmt-aisays-table' === $current_page) ? 'nav-tab-active' : ''; ?>">
			<?php esc_html_e('Product Descriptions', 'comet-ai-says'); ?>
		</a>
	</h2>
</div>
<?php
    }

    /**
     * Enqueue shared admin styles.
     */
    private function enqueue_shared_admin_styles(): void {
        wp_enqueue_style(
            'wpcmt-aisays-admin',
            $this->get_asset_url('plugin-admin.css'),
            [],
            $this->get_plugin_version()
        );
    }

    /**
     * Initialize usage stats.
     */
    private static function initialize_usage_stats(string $model): array {
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
            'limits' => self::get_model_limits($model),
            'last_updated' => time(),
        ];
    }

    /**
     * Get model limits.
     */
    private static function get_model_limits(string $model): array {
        foreach (self::MODEL_LIMITS as $key => $limits) {
            if (str_contains($model, $key)) {
                return $limits;
            }
        }

        return ['rpm' => 15, 'tpm' => 1000000, 'rpd' => 200];
    }

    /**
     * Process bulk generation.
     */
    private function process_bulk_generation(array $product_ids): array {
        $results = [
            'success' => 0,
            'errors' => 0,
            'details' => [],
        ];

        foreach ($product_ids as $product_id) {
            try {
                $product = wc_get_product($product_id);
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

                if ($description) {
                    update_post_meta($product_id, '_wpcmt_aisays_description', $description);
                    $results['success']++;
                    $results['details'][] = [
                        'product_id' => $product_id,
                        'status' => 'success',
                        // translators: %s: Product name
                        'message' => sprintf(__('Generated for: %s', 'comet-ai-says'), $product->get_name()),
                    ];
                    self::track_usage('generation');
                } else {
                    $results['errors']++;
                    $results['details'][] = [
                        'product_id' => $product_id,
                        'status' => 'error',
                        // translators: %s: Product name
                        'message' => sprintf(__('Generation failed for: %s', 'comet-ai-says'), $product->get_name()),
                    ];
                }
            } catch (\Exception $e) {
                $results['errors']++;
                $results['details'][] = [
                    'product_id' => $product_id,
                    'status' => 'error',
                    'message' => $e->getMessage(),
                ];
            }

            usleep(500000); // 0.5 second delay between requests
        }

        return $results;
    }

    /**
     * Render Gemini model select.
     */
    private function render_gemini_model_select(string $current_gemini_model): void {
        ?>
<div style="display:flex">
	<select id="wpcmt_aisays_gemini_model" name="wpcmt_aisays_gemini_model">
		<optgroup
			label="<?php esc_attr_e('Free Models', 'comet-ai-says'); ?>">
			<option value="gemini-2.5-pro" <?php selected($current_gemini_model, 'gemini-2.5-pro'); ?>>
				Gemini 2.5 Pro (Free – 5 RPM, 125K TPM)</option>
			<option value="gemini-2.5-flash" <?php selected($current_gemini_model, 'gemini-2.5-flash'); ?>>
				Gemini 2.5 Flash (Free – 10 RPM, 250K TPM)</option>
			<option value="gemini-2.5-flash-lite" <?php selected($current_gemini_model, 'gemini-2.5-flash-lite'); ?>>
				Gemini 2.5 Flash-Lite (Free – 15 RPM, 250K TPM)</option>
			<option value="gemini-2.0-flash" <?php selected($current_gemini_model, 'gemini-2.0-flash'); ?>>
				Gemini 2.0 Flash (Free – 15 RPM, 1M TPM)</option>
			<option value="gemini-2.0-flash-lite" <?php selected($current_gemini_model, 'gemini-2.0-flash-lite'); ?>>
				Gemini 2.0 Flash-Lite (Free – 30 RPM, 1M TPM)</option>
		</optgroup>
		<optgroup
			label="<?php esc_attr_e('Preview & Experimental Models', 'comet-ai-says'); ?>">
			<option value="gemini-2.5-flash-preview-04-17" <?php selected($current_gemini_model, 'gemini-2.5-flash-preview-04-17'); ?>>
				Gemini 2.5 Flash Preview (Limited Free)</option>
			<option value="gemini-2.5-pro-preview-05-06" <?php selected($current_gemini_model, 'gemini-2.5-pro-preview-05-06'); ?>>
				Gemini 2.5 Pro Preview (Limited Free)</option>
			<option value="gemini-2.5-pro-exp-03-25" <?php selected($current_gemini_model, 'gemini-2.5-pro-exp-03-25'); ?>>
				Gemini 2.5 Pro Exp (Limited Free)</option>
		</optgroup>
		<optgroup
			label="<?php esc_attr_e('Other Models', 'comet-ai-says'); ?>">
			<option value="gemma-3" <?php selected($current_gemini_model, 'gemma-3'); ?>>
				Gemma 3 (30 RPM, 15K TPM)</option>
		</optgroup>
	</select>
	<legend style="display:inline-block; margin-top:.4rem;margin-left:.5rem" class="abbr-badges">
		<abbr
			title="<?php esc_attr_e('Requests per minute', 'comet-ai-says'); ?>">RPM</abbr>
		<abbr
			title="<?php esc_attr_e('Tokens per minute', 'comet-ai-says'); ?>">TPM</abbr>
		<abbr
			title="<?php esc_attr_e('Requests per day', 'comet-ai-says'); ?>">RPD</abbr>
	</legend>
</div>
<p class="description">
	<strong><?php esc_html_e('Note:', 'comet-ai-says'); ?></strong>
	<?php esc_html_e('Gemini 2.5 models use internal reasoning tokens.', 'comet-ai-says'); ?>
	<?php esc_html_e('For free tiers, we strongly recommend', 'comet-ai-says'); ?>
	<code>gemini-2.0-flash</code>
	<?php esc_html_e('which provides better token efficiency.', 'comet-ai-says'); ?>
	<hr>
	<strong><?php esc_html_e('Free models recommended for most users.', 'comet-ai-says'); ?></strong>
	<?php esc_html_e('Preview models have limited free usage during testing.', 'comet-ai-says'); ?>
</p>
<?php
    }

    /**
     * Render OpenAI model select.
     */
    private function render_openai_model_select(string $current_openai_model): void {
        ?>
<select id="wpcmt_aisays_openai_model" name="wpcmt_aisays_openai_model">
	<optgroup
		label="<?php esc_attr_e('Latest Models (Recommended)', 'comet-ai-says'); ?>">
		<option value="gpt-4o" <?php selected($current_openai_model, 'gpt-4o'); ?>>
			GPT-4o
			(<?php esc_html_e('Latest, Fastest, Most Capable', 'comet-ai-says'); ?>)
		</option>
		<option value="gpt-4o-mini" <?php selected($current_openai_model, 'gpt-4o-mini'); ?>>
			GPT-4o Mini
			(<?php esc_html_e('Fast, Cost-effective', 'comet-ai-says'); ?>)
		</option>
	</optgroup>
	<optgroup
		label="<?php esc_attr_e('GPT-4 Models', 'comet-ai-says'); ?>">
		<option value="gpt-4-turbo" <?php selected($current_openai_model, 'gpt-4-turbo'); ?>>GPT-4
			Turbo</option>
		<option value="gpt-4" <?php selected($current_openai_model, 'gpt-4'); ?>>GPT-4
		</option>
	</optgroup>
	<optgroup
		label="<?php esc_attr_e('GPT-3.5 Models', 'comet-ai-says'); ?>">
		<option value="gpt-3.5-turbo" <?php selected($current_openai_model, 'gpt-3.5-turbo'); ?>>
			GPT-3.5 Turbo
			(<?php esc_html_e('Fast, Economical', 'comet-ai-says'); ?>)
		</option>
	</optgroup>
</select>
<p class="description">
	<strong><?php esc_html_e('GPT-4o recommended for most users.', 'comet-ai-says'); ?></strong>
	<?php esc_html_e('GPT-4o includes vision capabilities. GPT-3.5 is faster and cheaper but less capable.', 'comet-ai-says'); ?>
</p>
<?php
    }

    /**
     * Render display settings.
     */
    private function render_display_settings(string $current_display_mode, string $current_display_position, string $current_shortcode): void {
        ?>
<tr>
	<th scope="row">
		<label
			for="wpcmt_aisays_display_mode"><?php esc_html_e('Display Mode', 'comet-ai-says'); ?></label>
	</th>
	<td>
		<select id="wpcmt_aisays_display_mode" name="wpcmt_aisays_display_mode">
			<option value="automatic" <?php selected($current_display_mode, 'automatic'); ?>>
				<?php esc_html_e('Automatic - Show immediately', 'comet-ai-says'); ?>
			</option>
			<option value="manual" <?php selected($current_display_mode, 'manual'); ?>>
				<?php esc_html_e('Manual - Shortcode only', 'comet-ai-says'); ?>
			</option>
		</select>
		<p class="description">
			<?php esc_html_e('Choose how AI descriptions are displayed on product pages.', 'comet-ai-says'); ?>
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
		<select id="wpcmt_aisays_display_position" name="wpcmt_aisays_display_position">
			<option value="after_short_description" <?php selected($current_display_position, 'after_short_description'); ?>>
				<?php esc_html_e('After short description', 'comet-ai-says'); ?>
			</option>
			<option value="after_description" <?php selected($current_display_position, 'after_description'); ?>>
				<?php esc_html_e('After product description', 'comet-ai-says'); ?>
			</option>
			<option value="after_tabs" <?php selected($current_display_position, 'after_tabs'); ?>>
				<?php esc_html_e('After product tabs', 'comet-ai-says'); ?>
			</option>
			<option value="product_bottom" <?php selected($current_display_position, 'product_bottom'); ?>>
				<?php esc_html_e('Bottom of product page', 'comet-ai-says'); ?>
			</option>
		</select>
		<p class="description">
			<?php esc_html_e('Choose where to display the AI description when using automatic mode.', 'comet-ai-says'); ?>
		</p>
	</td>
</tr>

<tr id="shortcode-row"
	style="<?php echo ('manual' !== $current_display_mode) ? 'display: none;' : ''; ?>">
	<th scope="row">
		<label
			for="wpcmt_aisays_shortcode"><?php esc_html_e('Shortcode', 'comet-ai-says'); ?></label>
	</th>
	<td>
		<input type="text" id="wpcmt_aisays_shortcode" name="wpcmt_aisays_shortcode"
			value="<?php echo esc_attr($current_shortcode); ?>"
			class="regular-text" readonly />
		<p class="description">
			<?php esc_html_e('Use this shortcode to display the AI description anywhere on your site.', 'comet-ai-says'); ?><br>
			<?php esc_html_e('Copy and paste it into any post, page, or product description.', 'comet-ai-says'); ?>
		</p>
	</td>
</tr>
<?php
    }

    /**
     * Render language settings.
     */
    private function render_language_settings(string $current_language, string $custom_language): void {
        ?>
<tr>
	<th scope="row">
		<label
			for="wpcmt_aisays_language"><?php esc_html_e('Description Language', 'comet-ai-says'); ?></label>
	</th>
	<td>
		<select id="wpcmt_aisays_language" name="wpcmt_aisays_language">
			<?php foreach (self::LANGUAGE_DATA as $key => $lang): ?>
			<option value="<?php echo esc_attr($key); ?>" <?php selected($current_language, $key); ?>>
				<?php echo esc_html($lang[0]); ?>
			</option>
			<?php endforeach; ?>
			<option value="custom" <?php selected($current_language, 'custom'); ?>>
				<?php esc_html_e('Custom Language', 'comet-ai-says'); ?>
			</option>
		</select>
		<p class="description">
			<?php esc_html_e('Select the language for generated product descriptions', 'comet-ai-says'); ?>
		</p>
	</td>
</tr>

<tr id="custom-language-row"
	style="<?php echo ('custom' !== $current_language) ? 'display: none;' : ''; ?>">
	<th scope="row">
		<label
			for="wpcmt_aisays_custom_language"><?php esc_html_e('Custom Language', 'comet-ai-says'); ?></label>
	</th>
	<td>
		<input type="text" id="wpcmt_aisays_custom_language" name="wpcmt_aisays_custom_language"
			value="<?php echo esc_attr($custom_language); ?>"
			class="regular-text"
			placeholder="<?php esc_attr_e('e.g., Swedish, Thai, Greek, etc.', 'comet-ai-says'); ?>" />
		<p class="description">
			<?php esc_html_e('Enter any language not listed above', 'comet-ai-says'); ?>
		</p>
	</td>
</tr>
<?php
    }

    /**
     * Render prompt template.
     */
    private function render_prompt_template(string $current_prompt_template): void {
        ?>
<!-- Prompt Template Preview -->
<div id="prompt-preview"
	style="margin-top: 15px; padding: 15px; background: #f5f5f5; border-radius: 4px; display: none;">
	<strong><?php esc_html_e('Prompt Preview:', 'comet-ai-says'); ?></strong>
	<small style="display: block; margin-bottom: 8px; color: #666;">
		<?php esc_html_e('This shows exactly how your template will be processed with the current language selection.', 'comet-ai-says'); ?>
	</small>
	<pre id="preview-content"
		style="white-space: pre-wrap; margin: 5px 0 0 0; background: #fff; padding: 10px; border-radius: 3px; border: 1px solid #ddd; max-height: 300px; overflow-y: auto;"></pre>
</div>
<textarea id="wpcmt_aisays_prompt_template" name="wpcmt_aisays_prompt_template" rows="10"
	style="width: 100%; font-family: monospace;"
	placeholder="<?php echo esc_attr($this->get_default_prompt_template()); ?>"><?php echo esc_textarea($current_prompt_template); ?></textarea>
<p class="description">
	<?php esc_html_e('Customize the prompt template. Available variables:', 'comet-ai-says'); ?><br>
	<code>{language}</code> -
	<?php esc_html_e('Selected language instruction', 'comet-ai-says'); ?><br>
	<code>{product_name}</code> -
	<?php esc_html_e('Product name', 'comet-ai-says'); ?><br>
	<code>{short_description}</code> -
	<?php esc_html_e('Existing short description', 'comet-ai-says'); ?><br>
	<code>{categories}</code> -
	<?php esc_html_e('Product categories', 'comet-ai-says'); ?><br>
	<code>{attributes}</code> -
	<?php esc_html_e('Product attributes/specifications', 'comet-ai-says'); ?><br>
	<code>{image_analysis}</code> -
	<?php esc_html_e('Featured image analysis and context', 'comet-ai-says'); ?><br>
	<button type="button" class="button button-small"
		onclick="document.getElementById('wpcmt_aisays_prompt_template').value = '<?php echo esc_js($this->get_default_prompt_template()); ?>'">
		<?php esc_html_e('Reset to Default', 'comet-ai-says'); ?>
	</button>
	<hr>
	<?php esc_html_e('You can write-in additional rules like:', 'comet-ai-says'); ?><br>
	-
	<?php esc_html_e('Do NOT use markdown formatting, asterisks, or special characters', 'comet-ai-says'); ?><br>
	-
	<?php esc_html_e('Write in a continuous paragraph format without section headers', 'comet-ai-says'); ?>
</p>
<?php
    }

    /**
     * Render prompt guide card.
     */
    private function render_prompt_guide_card(): void {
        ?>
<div class="card">
	<h2><?php esc_html_e('Prompt Template Guide', 'comet-ai-says'); ?>
	</h2>
	<p><strong><?php esc_html_e('Available Variables:', 'comet-ai-says'); ?></strong>
	</p>
	<ul>
		<li><code>{language}</code> -
			<?php esc_html_e('The selected language instruction', 'comet-ai-says'); ?>
		</li>
		<li><code>{product_name}</code> -
			<?php esc_html_e('Product name/title', 'comet-ai-says'); ?>
		</li>
		<li><code>{short_description}</code> -
			<?php esc_html_e('Existing product short description', 'comet-ai-says'); ?>
		</li>
		<li><code>{categories}</code> -
			<?php esc_html_e('Product categories', 'comet-ai-says'); ?>
		</li>
		<li><code>{attributes}</code> -
			<?php esc_html_e('Product attributes and specifications', 'comet-ai-says'); ?>
		</li>
		<li><code>{image_analysis}</code> -
			<?php esc_html_e('Featured image analysis and visual context', 'comet-ai-says'); ?>
		</li>
	</ul>
	<p><strong><?php esc_html_e('Example Custom Templates:', 'comet-ai-says'); ?></strong>
	</p>
	<pre
		style="background: #f5f5f5; padding: 10px; border-radius: 4px;"><?php esc_html_e('Write a creative product description in {language} for {product_name}.

Product details:
- Categories: {categories}
- Specifications: {attributes}

Create an engaging, SEO-friendly description that highlights unique selling points.', 'comet-ai-says'); ?></pre>

	<pre
		style="background: #f5f5f5; padding: 10px; border-radius: 4px;"><?php esc_html_e('Create a professional e-commerce product description in {language}.

Product: {product_name}
About: {short_description}
Category: {categories}
Features: {attributes}

Write a compelling description that converts visitors into buyers.', 'comet-ai-says'); ?></pre>
</div>
<?php
    }

    /**
     * Render setup instructions card.
     */
    private function render_setup_instructions_card(): void {
        ?>
<div class="card">
	<h2><?php esc_html_e('Setup Instructions', 'comet-ai-says'); ?>
	</h2>
	<h3><?php esc_html_e('For Gemini (Recommended - Free):', 'comet-ai-says'); ?>
	</h3>
	<ol>
		<li><?php esc_html_e('Go to', 'comet-ai-says'); ?>
			<a href="https://aistudio.google.com/app/apikey" target="_blank">Google AI Studio</a>
		</li>
		<li><?php esc_html_e('Sign in with your Google account', 'comet-ai-says'); ?>
		</li>
		<li><?php esc_html_e('Click "Create API Key"', 'comet-ai-says'); ?>
		</li>
		<li><?php esc_html_e('Copy the API key and paste it above', 'comet-ai-says'); ?>
		</li>
		<li><strong><?php esc_html_e('Recommended:', 'comet-ai-says'); ?></strong>
			<?php esc_html_e('Start with "Gemini 2.0 Flash" - it\'s completely free with high limits', 'comet-ai-says'); ?>
		</li>
		<li><?php esc_html_e('Gemini offers free usage with generous limits', 'comet-ai-says'); ?>
		</li>
	</ol>

	<h3><?php esc_html_e('For OpenAI (Limited - Paid):', 'comet-ai-says'); ?>
	</h3>
	<ol>
		<li><?php esc_html_e('Go to', 'comet-ai-says'); ?>
			<a href="https://platform.openai.com/api-keys" target="_blank">OpenAI Platform</a>
		</li>
		<li><?php esc_html_e('Create an account and set up billing', 'comet-ai-says'); ?>
		</li>
		<li><?php esc_html_e('Create an API key', 'comet-ai-says'); ?>
		</li>
		<li><?php esc_html_e('Copy the API key and paste it above', 'comet-ai-says'); ?>
		</li>
	</ol>
</div>
<?php
    }

    /**
     * Render support card.
     */
    private function render_support_card(): void {
        ?>
<div class="card">
	<h2><?php esc_html_e('Support', 'comet-ai-says'); ?>
	</h2>
	<p><?php esc_html_e('For more information, please visit our', 'comet-ai-says'); ?>
		<a href="https://wpcomet.com/ai-says/" target="_blank">Plugin page</a>
	</p>
	<p><?php esc_html_e('If you have any questions or need help, please visit our', 'comet-ai-says'); ?>
		<a href="https://wpcomet.com/support/" target="_blank">Support page</a>
	</p>
</div>
<?php
    }

    /**
     * Output admin JavaScript.
     */
    private function output_admin_javascript(): void {
        ?>
<script>
	// Language data for JavaScript
	var languageData =
		<?php echo wp_json_encode([
		    'intro' => [
		        'english' => self::get_language_part('english', 'intro'),
		        'spanish' => self::get_language_part('spanish', 'intro'),
		        'french' => self::get_language_part('french', 'intro'),
		        'german' => self::get_language_part('german', 'intro'),
		        'italian' => self::get_language_part('italian', 'intro'),
		        'portuguese' => self::get_language_part('portuguese', 'intro'),
		        'dutch' => self::get_language_part('dutch', 'intro'),
		        'russian' => self::get_language_part('russian', 'intro'),
		        'japanese' => self::get_language_part('japanese', 'intro'),
		        'korean' => self::get_language_part('korean', 'intro'),
		        'chinese' => self::get_language_part('chinese', 'intro'),
		        'arabic' => self::get_language_part('arabic', 'intro'),
		        'turkish' => self::get_language_part('turkish', 'intro'),
		        'hindi' => self::get_language_part('hindi', 'intro'),
		        'custom' => self::get_language_part('custom', 'intro'),
		    ],
		    'instructions' => [
		        'english' => self::get_language_part('english', 'instructions'),
		        'spanish' => self::get_language_part('spanish', 'instructions'),
		        'french' => self::get_language_part('french', 'instructions'),
		        'german' => self::get_language_part('german', 'instructions'),
		        'italian' => self::get_language_part('italian', 'instructions'),
		        'portuguese' => self::get_language_part('portuguese', 'instructions'),
		        'dutch' => self::get_language_part('dutch', 'instructions'),
		        'russian' => self::get_language_part('russian', 'instructions'),
		        'japanese' => self::get_language_part('japanese', 'instructions'),
		        'korean' => self::get_language_part('korean', 'instructions'),
		        'chinese' => self::get_language_part('chinese', 'instructions'),
		        'arabic' => self::get_language_part('arabic', 'instructions'),
		        'turkish' => self::get_language_part('turkish', 'instructions'),
		        'hindi' => self::get_language_part('hindi', 'instructions'),
		        'custom' => self::get_language_part('custom', 'instructions'),
		    ],
		]); ?>
	;

	function toggleVisibility(fieldId) {
		var field = document.getElementById(fieldId);
		var button = field.nextElementSibling;

		if (field.classList.contains('masked')) {
			// Show the actual text
			field.classList.remove('masked');
			field.style.webkitTextSecurity = 'none';
			field.style.textSecurity = 'none';
			button.textContent =
				'<?php echo esc_js(__('Hide', 'comet-ai-says')); ?>';
		} else {
			// Mask the text
			field.classList.add('masked');
			field.style.webkitTextSecurity = 'disc';
			field.style.textSecurity = 'disc';
			button.textContent =
				'<?php echo esc_js(__('Show', 'comet-ai-says')); ?>';
		}
	}

	jQuery(document).ready(function($) {
		// Provider change handler
		$('#wpcmt_aisays_provider').on('change', function() {
			var provider = $(this).val();
			$('#gemini-model-row, #gemini-api-key-row').toggle(provider === 'gemini');
			$('#openai-model-row, #openai-api-key-row').toggle(provider === 'openai');
		});

		// Language change handler
		$('#wpcmt_aisays_language').on('change', function() {
			$('#custom-language-row').toggle($(this).val() === 'custom');
			updatePromptPreview();
		});

		// Custom language and prompt template handlers
		$('#wpcmt_aisays_custom_language, #wpcmt_aisays_prompt_template').on('input', updatePromptPreview);

		// Display mode change handler
		$('#wpcmt_aisays_display_mode').on('change', function() {
			var isAutomatic = $(this).val() === 'automatic';
			$('#display-position-row').toggle(isAutomatic);
			$('#shortcode-row').toggle(!isAutomatic);
		});

		// Model change handler for token ranges
		$('#wpcmt_aisays_gemini_model').on('change', function() {
			updateTokenRange();
			updateCapacityInfo();
		});

		// Token slider handler
		$('#wpcmt_aisays_max_tokens').on('input', function() {
			$('#max-tokens-value').text($(this).val() +
				' <?php echo esc_js(__('tokens', 'comet-ai-says')); ?>'
			);
		});

		// Settings search
		$('#comet-settings-search').on('keyup', function() {
			var searchText = $(this).val().toLowerCase();
			if (searchText.length >= 2) {
				$('.form-table tr').each(function() {
					$(this).toggle($(this).text().toLowerCase().indexOf(searchText) > -1);
				});
			} else {
				$('.form-table tr').show();
			}
		});

		// Initialize
		updateTokenRange();
		updatePromptPreview();
		var apifieldid = document.querySelector('.api-key-field').id;

		toggleVisibility(apifieldid);
	});

	function updatePromptPreview() {
		var template = jQuery('#wpcmt_aisays_prompt_template').val();
		var language = jQuery('#wpcmt_aisays_language').val();
		var customLanguage = jQuery('#wpcmt_aisays_custom_language').val();

		var introduction = getLanguageInstruction(language, 'intro', customLanguage);
		var instructions = getLanguageInstruction(language, 'instructions', customLanguage);

		var preview = template
			.replace(/{introduction}/g, introduction)
			.replace(/{instructions}/g, instructions)
			.replace(/{product_name}/g, 'Sample Product Name')
			.replace(/{short_description}/g, 'Sample short description')
			.replace(/{categories}/g, 'Sample Category')
			.replace(/{attributes}/g, '- Color: Red\n- Size: Large')
			.replace(/{image_analysis}/g, 'Sample image analysis');

		if (template.trim() !== '') {
			jQuery('#preview-content').text(preview);
			jQuery('#prompt-preview').show();
		} else {
			jQuery('#prompt-preview').hide();
		}
	}

	function getLanguageInstruction(language, part, customLanguage) {
		var instruction = languageData[part][language] || languageData[part]['english'];
		if (language === 'custom' && part === 'intro' && customLanguage) {
			instruction = instruction.replace('CUSTOM_LANGUAGE', customLanguage);
		} else if (language === 'custom' && part === 'intro') {
			instruction = instruction.replace('CUSTOM_LANGUAGE', 'Custom Language');
		}
		return instruction;
	}

	function updateTokenRange() {
		var geminiModel = jQuery('#wpcmt_aisays_gemini_model').val();
		var maxTokensInput = jQuery('#wpcmt_aisays_max_tokens');
		var tokensValue = jQuery('#max-tokens-value');
		var recommended = jQuery('#recommended-tokens');
		var capacityInfo = jQuery('#token-capacity-info');

		var configs = {
			'gemini-2.5-pro': {
				min: 4000,
				max: 10000,
				default: 5000,
				rec: '<?php echo esc_js(__('4000-10000 tokens for complex analysis', 'comet-ai-says')); ?>',
				cap: '<?php echo esc_js(__('125K TPM, 5 RPM', 'comet-ai-says')); ?>'
			},
			'gemini-2.5-flash': {
				min: 1500,
				max: 5000,
				default: 3000,
				rec: '<?php echo esc_js(__('1500-5000 tokens for detailed descriptions', 'comet-ai-says')); ?>',
				cap: '<?php echo esc_js(__('250K TPM, 10 RPM', 'comet-ai-says')); ?>'
			},
			'gemini-2.5-flash-lite': {
				min: 800,
				max: 2500,
				default: 1500,
				rec: '<?php echo esc_js(__('800-2500 tokens for efficient descriptions', 'comet-ai-says')); ?>',
				cap: '<?php echo esc_js(__('250K TPM, 15 RPM', 'comet-ai-says')); ?>'
			},
			'gemini-2.0-flash': {
				min: 1000,
				max: 4000,
				default: 2500,
				rec: '<?php echo esc_js(__('1000-4000 tokens for balanced performance', 'comet-ai-says')); ?>',
				cap: '<?php echo esc_js(__('1M TPM, 15 RPM', 'comet-ai-says')); ?>'
			},
			'gemini-2.0-flash-lite': {
				min: 800,
				max: 2000,
				default: 1200,
				rec: '<?php echo esc_js(__('800-2000 tokens for lightweight tasks', 'comet-ai-says')); ?>',
				cap: '<?php echo esc_js(__('1M TPM, 30 RPM', 'comet-ai-says')); ?>'
			},
			'gemini-2.5-flash-preview-04-17': {
				min: 1500,
				max: 4000,
				default: 2500,
				rec: '<?php echo esc_js(__('1500-4000 tokens for preview testing', 'comet-ai-says')); ?>',
				cap: '<?php echo esc_js(__('Limited Free Tier', 'comet-ai-says')); ?>'
			},
			'gemini-2.5-pro-preview-05-06': {
				min: 3000,
				max: 8000,
				default: 4000,
				rec: '<?php echo esc_js(__('3000-8000 tokens for pro preview', 'comet-ai-says')); ?>',
				cap: '<?php echo esc_js(__('Limited Free Tier', 'comet-ai-says')); ?>'
			},
			'gemini-2.5-pro-exp-03-25': {
				min: 3000,
				max: 8000,
				default: 4000,
				rec: '<?php echo esc_js(__('3000-8000 tokens for experimental features', 'comet-ai-says')); ?>',
				cap: '<?php echo esc_js(__('Limited Free Tier', 'comet-ai-says')); ?>'
			},
			'gemma-3': {
				min: 1000,
				max: 3000,
				default: 1800,
				rec: '<?php echo esc_js(__('1000-3000 tokens for Gemma model', 'comet-ai-says')); ?>',
				cap: '<?php echo esc_js(__('15K TPM, 30 RPM', 'comet-ai-says')); ?>'
			},
			'default': {
				min: 1000,
				max: 5000,
				default: 2500,
				rec: '<?php echo esc_js(__('1000-5000 tokens for comprehensive descriptions', 'comet-ai-says')); ?>',
				cap: '<?php echo esc_js(__('Standard configuration', 'comet-ai-says')); ?>'
			}
		};

		var config = configs.default;
		for (var key in configs) {
			if (key !== 'default' && geminiModel.includes(key)) {
				config = configs[key];
				break;
			}
		}

		maxTokensInput.attr('min', config.min).attr('max', config.max);
		recommended.text(config.rec);
		capacityInfo.text(config.cap);

		maxTokensInput.val(config.default);
		/*
        var currentVal = parseInt(maxTokensInput.val());
        if (currentVal < config.min || currentVal > config.max) {
			maxTokensInput.val(config.default);
		}*/

		tokensValue.text(maxTokensInput.val() +
			' <?php echo esc_js(__('tokens', 'comet-ai-says')); ?>'
		);
	}

	function updateCapacityInfo() {
		var geminiModel = jQuery('#wpcmt_aisays_gemini_model').val();
		var capacityInfo = jQuery('#token-capacity-info');
		capacityInfo.text(geminiModel.includes('2.5') ?
			'<?php echo esc_js(__('Up to 375,000 tokens daily with Gemini 2.5 Flash', 'comet-ai-says')); ?>' :
			'<?php echo esc_js(__('Up to 150,000 tokens daily with Gemini 2.0 Flash', 'comet-ai-says')); ?>'
		);
	}
</script>
<?php
    }

    public function control_notices() {
        if (!Plugin::is_plugin_screen()) {
            return;
        }

        remove_all_actions('admin_notices');
        remove_all_actions('all_admin_notices');

        $this->show_notices();
    }

    /**
     * Get language instruction parts.
     */
    public static function get_language_part(string $language, string $part = 'intro'): string {
        $introductions = [
            'english' => 'Write a compelling product description in English. Use a professional, engaging tone suitable for e-commerce. Highlight key features and benefits.',
            'spanish' => 'Escribe una descripción de producto convincente en español. Utiliza un tono profesional y atractivo adecuado para el comercio electrónico. Destaca las características clave y los beneficios.',
            'french' => 'Rédigez une description de produit convaincante en français. Utilisez un ton professionnel et engageant adapté au commerce électronique. Metez en avant les caractéristiques clés et les avantages.',
            'german' => 'Verfassen Sie eine überzeugende Produktbeschreibung auf Deutsch. Verwenden Sie einen professionellen, ansprechenden Ton, der für den E-Commerce geeignet ist. Heben Sie die wichtigsten Funktionen und Vorteile hervor.',
            'italian' => 'Scrivi una descrizione del producto convincente in italiano. Usa un tono professionale e coinvolgente adatto per l\'e-commerce. Evidenzia le caratteristiche principali e i benefici.',
            'portuguese' => 'Escreva una descrição convincente do produto em português. Use un tom profesional e atraente adequado para o comércio eletrônico. Destaque os principais recursos e benefícios.',
            'dutch' => 'Schrijf een overtuigende productbeschrijving in het Nederlands. Gebruik een professionele, boeiende toon die geschikt is voor e-commerce. Benadruk de belangrijkste kenmerken en voordelen.',
            'russian' => 'Напишите убедительное описание товара на русском языке. Используйте профессиональный, привлекательный тон, подходящий для электронной коммерции. Выделите ключевые особенности и преимущества.',
            'japanese' => '日本語で説得力のある商品説明を書いてください。Eコマースに適したプロフェッショナルで魅力的なトーンを使用してください。主な機能と利点を強調してください。',
            'korean' => '한국어로 매력적인 제품 설명을 작성해 주세요. 전자상거래에 적합한 전문적이고 매력적인 어조를 사용하세요. 주요 기능과 이점을 강조하세요。',
            'chinese' => '用中文撰写有说服力的产品描述。使用适合电子商务的专业、引人入胜的语气。突出关键特性和优势。',
            'arabic' => 'اكتب وصفًا مقنعًا للمنتج باللغة العربية. استخدم نبرة احترافية وجذابة مناسبة للتجارة الإلكترونية. سلط الضوء على الميزات والفوائد الرئيسية.',
            'turkish' => 'Türkçe olarak etkileyici bir ürün açıklaması yazın. E-ticaret için uygun, profesyonel ve ilgi çekici bir ton kullanın. Temel özellikleri ve faydaları vurgulayın.',
            'hindi' => 'हिंदी में एक आकर्षक उत्पाद विवरण लिखें। ई-कॉमर्स के लिए उपयुक्त एक पेशेवर, आकर्षक स्वर का उपयोग करें। मुख्य विशेषताओं और लाभों पर प्रकाश डालें。',
            'custom' => 'Write a compelling product description in CUSTOM_LANGUAGE. Use a professional, engaging tone suitable for e-commerce. Highlight key features and benefits.',
        ];

        $instructions = [
            'english' => "- Keep it concise but persuasive (about 150-200 words).\n- Do NOT add any introductory phrases like \"Here is...\", \"I present...\", \"This product...\", etc.",
            'spanish' => "- Manténgalo conciso pero persuasivo (aproximadamente 150-200 palabras).\n- NO agregue frases introductorias como \"Aquí está...\", \"Presento...\", \"Este producto...\", etc.",
            'french' => "- Soyez concis mais persuasif (environ 150-200 mots).\n- N'ajoutez PAS de phrases introductives comme \"Voici...\", \"Je présente...\", \"Ce produit...\", etc.",
            'german' => "- Fassen Sie sich kurz, aber überzeugend (etwa 150-200 Wörter).\n- Fügen Sie KEINE einleitenden Sätze wie \"Hier ist...\", \"Ich präsentiere...\", \"Dieses Produkt...\" usw. hinzu.",
            'italian' => "- Sii conciso ma persuasivo (circa 150-200 parole).\n- NON aggiungere frasi introduttive como \"Ecco...\", \"Presento...\", \"Questo prodotto...\", ecc.",
            'portuguese' => "- Mantenha conciso, mas persuasivo (cerca de 150-200 palavras).\n- NÃO adicione frases introdutórias como \"Aqui está...\", \"Apresento...\", \"Este produto...\", etc.",
            'dutch' => "- Houd het beknopt maar overtuigend (ongeveer 150-200 woorden).\n- Voeg GEEN inleidende zinnen toe zoals \"Hier is...\", \"Ik presenteer...\", \"Dit product...\", etc.",
            'russian' => "- Будьте лаконичны, но убедительны (около 150-200 слов).\n- НЕ добавляйте вводные фразы, такие как \"Вот...\", \"Представляю...\", \"Этот продукт...\" и т.д.",
            'japanese' => "- 簡潔かつ説得力のある文章にしてください（約150〜200語）。\n- 「こちらが...」「ご紹介します...」「この商品は...」などの導入句を追加しないでください",
            'korean' => "- 간결하지만 설득력 있게 작성하세요 (약 150-200단어).\n- \"여기...\", \"소개합니다...\", \"이 제품은...\" 등의 도입 문구를 추가하지 마세요",
            'chinese' => "- 保持简洁但有说服力（约150-200字）。\n- 不要添加任何介绍性短语，如\"这是...\"、\"我介绍...\"、\"本产品...\"等。",
            'arabic' => "- اجعلها موجزة ولكن مقنعة (حوالي 150-200 كلمة).\n- لا تضيف أي عبارات تمهيدية مثل \"ها هو...\"، \"أقدم...\"، \"هذا المنتج...\"، إلخ.",
            'turkish' => "- Kısa ama ikna edici olun (yaklaşık 150-200 kelime).\n- işte buyur ürün açıklaman burada gibi cevaben gereksiz giriş ifadeleri EKLEME.",
            'hindi' => "- संक्षिप्त लेकिन प्रेरक रखें (लगभग 150-200 शब्द).\n- \"यहाँ है...\", \"मैं प्रस्तुत करता हूँ...\", \"यह उत्पाद...\" आदि जैसे किसी भी परिचयात्मक वाक्यांश को न जोड़ें।",
            'custom' => "- Keep it concise but persuasive (about 150-200 words).\n- Do NOT add any introductory phrases like \"Here is...\", \"I present...\", \"This product...\", etc.",
        ];

        $data = ('intro' === $part) ? $introductions : $instructions;
        $text = $data[$language] ?? $data['english'];

        if ('custom' === $language && 'intro' === $part) {
            $custom_language = get_option('wpcmt_aisays_custom_language', 'English');
            $text = str_replace('CUSTOM_LANGUAGE', $custom_language, $text);
        }

        return $text;
    }

    /**
     * AJAX callback for single description generation.
     */
    public function generate_single_ai_description_callback(): void {
        AIGenerator::generate_single_ajax();
    }

    /**
     * AJAX callback for checking existing description.
     */
    public function check_existing_description_callback(): void {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'wpcmt_aisays_nonce')) {
            wp_send_json_error('Security check failed');
        }

        // Verify product_id
        if (!isset($_POST['product_id'])) {
            wp_send_json_error('Product ID is required');
        }

        $product_id = intval(wp_unslash($_POST['product_id']));

        // Validate product exists
        if (!$product_id || !get_post($product_id)) {
            wp_send_json_error('Invalid product ID');
        }

        $existing_description = get_post_meta($product_id, '_wpcmt_aisays_description', true);

        wp_send_json_success([
            'has_description' => !empty($existing_description),
            'existing_description' => $existing_description,
        ]);
    }

    /**
     * Restore defaults handler.
     */
    public function maybe_restore_defaults(): void {
        if (!isset($_POST['restore-defaults']) || !current_user_can('manage_options')) {
            return;
        }

        // Verify nonce
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'wpcmt_aisays_settings-options')) {
            wp_die(esc_html__('Security check failed', 'comet-ai-says'));
        }

        $defaults = [
            'wpcmt_aisays_provider' => 'gemini',
            'wpcmt_aisays_language' => 'english',
            'wpcmt_aisays_custom_language' => '',
            'wpcmt_aisays_gemini_model' => 'gemini-2.0-flash',
            'wpcmt_aisays_openai_model' => 'gpt-4o',
            'wpcmt_aisays_display_mode' => 'automatic',
            'wpcmt_aisays_display_position' => 'after_description',
            'wpcmt_aisays_shortcode' => '[ai_says_product_description]',
            'wpcmt_aisays_prompt_template' => $this->get_default_prompt_template(),
            'wpcmt_aisays_max_tokens' => 1500,
        ];

        foreach ($defaults as $option => $value) {
            update_option($option, $value);
        }

        wp_safe_redirect(add_query_arg('restored', 'true', wp_get_referer()));
        exit;
    }

    /**
     * Display usage statistics.
     */
    public static function display_usage_stats(): void {
        $usage_stats = self::get_usage_stats();
        $limits = $usage_stats['limits'];
        $current_provider = get_option('wpcmt_aisays_provider', 'gemini');

        // Calculate percentages
        $rpm_percent = min(100, ($usage_stats['requests_this_minute'] / $limits['rpm']) * 100);
        $tpm_percent = min(100, ($usage_stats['tokens_this_minute'] / $limits['tpm']) * 100);
        $rpd_percent = min(100, ($usage_stats['requests_today'] / $limits['rpd']) * 100);

        // Determine colors
        $rpm_color = $rpm_percent > 80 ? '#dc3232' : ($rpm_percent > 60 ? '#ffb900' : '#46b450');
        $tpm_color = $tpm_percent > 80 ? '#dc3232' : ($tpm_percent > 60 ? '#ffb900' : '#46b450');
        $rpd_color = $rpd_percent > 80 ? '#dc3232' : ($rpd_percent > 60 ? '#ffb900' : '#46b450');
        ?>
<details class="accordion">
	<summary>
		<?php esc_html_e('Usage Stats', 'comet-ai-says'); ?>
	</summary>
	<div class="card card-lg">
		<div class="col">
			<table class="widefat" style="margin-top: 15px;">
				<thead>
					<tr>
						<th><?php esc_html_e('Limit Type', 'comet-ai-says'); ?>
						</th>
						<th><?php esc_html_e('Used', 'comet-ai-says'); ?>
						</th>
						<th><?php esc_html_e('Limit', 'comet-ai-says'); ?>
						</th>
						<th><?php esc_html_e('Progress', 'comet-ai-says'); ?>
						</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ([
					    ['rpm', __('Requests/Minute', 'comet-ai-says'), __('Resets every 60s', 'comet-ai-says'), $usage_stats['requests_this_minute'], $limits['rpm'], $rpm_percent, $rpm_color],
					    ['tpm', __('Tokens/Minute', 'comet-ai-says'), __('Resets every 60s', 'comet-ai-says'), $usage_stats['tokens_this_minute'], $limits['tpm'], $tpm_percent, $tpm_color],
					    ['rpd', __('Requests/Day', 'comet-ai-says'), __('Resets every 24h', 'comet-ai-says'), $usage_stats['requests_today'], $limits['rpd'], $rpd_percent, $rpd_color],
					] as $row): ?>
					<tr>
						<td><strong><?php echo esc_html($row[1]); ?></strong><br><small><?php echo esc_html($row[2]); ?></small>
						</td>
						<td><?php echo esc_html(number_format($row[3])); ?>
						</td>
						<td><?php echo esc_html(number_format($row[4])); ?>
						</td>
						<td style="width: 200px;">
							<div style="background: #f0f0f1; border-radius: 10px; height: 20px; position: relative;">
								<div
									style="background: <?php echo esc_attr($row[6]); ?>; border-radius: 10px; height: 100%; width: <?php echo esc_attr($row[5]); ?>%; transition: width 0.3s;">
								</div>
								<div
									style="position: absolute; top: 0; left: 0; right: 0; text-align: center; font-size: 11px; font-weight: bold; color: <?php echo esc_attr($row[5] > 50 ? '#fff' : '#000'); ?>; line-height: 20px;">
									<?php echo esc_html(number_format($row[5], 1)); ?>%
								</div>
							</div>
						</td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<div class="col">
			<h2><?php esc_html_e('API Usage Statistics', 'comet-ai-says'); ?>
			</h2>
			<p class="description">
				<strong><?php esc_html_e('Current Model:', 'comet-ai-says'); ?></strong>
				<?php echo esc_html($usage_stats['model']); ?><br>
				<small><?php esc_html_e('Minute limits reset every 60 seconds. Daily limits reset every 24 hours.', 'comet-ai-says'); ?><br>
					<?php esc_html_e('For real-time tracking, check your provider dashboard.', 'comet-ai-says'); ?></small>
			</p>
			<div style="margin-top: 15px; padding: 10px; background: #f8f9fa; border-radius: 4px;">
				<strong><?php esc_html_e('Total Generations:', 'comet-ai-says'); ?></strong>
				<?php echo esc_html(number_format(get_option('wpcmt_aisays_total_generations', 0))); ?><br>
				<strong><?php esc_html_e('Provider Dashboard:', 'comet-ai-says'); ?></strong>
				<?php if ('gemini' === $current_provider): ?>
				<a href="https://aistudio.google.com/app/apikey"
					target="_blank"><?php esc_html_e('Google AI Studio', 'comet-ai-says'); ?></a>
				<?php else: ?>
				<a href="https://platform.openai.com/usage"
					target="_blank"><?php esc_html_e('OpenAI Platform', 'comet-ai-says'); ?></a>
				<?php endif; ?>
			</div>
			<div style="margin-top: 10px; font-size: 12px; color: #666;">
				<strong><?php esc_html_e('Note:', 'comet-ai-says'); ?></strong>
				<?php esc_html_e('Token usage is estimated. Actual usage may vary.', 'comet-ai-says'); ?><br>
				<strong><?php esc_html_e('Current minute:', 'comet-ai-says'); ?></strong>
				<?php echo esc_html(gmdate('H:i:s')); ?>
				|
				<strong><?php esc_html_e('Last updated:', 'comet-ai-says'); ?></strong>
				<?php echo esc_html(gmdate('H:i:s', $usage_stats['last_updated'])); ?>
			</div>
		</div>
	</div>
</details>
<?php
    }

    /**
     * Track API usage.
     */
    public static function track_usage(string $request_type = 'generation'): void {
        $current_provider = get_option('wpcmt_aisays_provider', 'gemini');
        if ('gemini' !== $current_provider) {
            return;
        }

        $current_model = get_option('wpcmt_aisays_gemini_model', 'gemini-2.0-flash');
        $usage_stats = get_transient('wpcmt_aisays_daily_usage') ?: self::initialize_usage_stats($current_model);

        $current_minute = floor(time() / 60);
        $current_day = gmdate('Y-m-d');

        // Reset counters if needed
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

        // Increment counters
        if ('generation' === $request_type) {
            $usage_stats['requests_this_minute']++;
            $usage_stats['requests_today']++;
            $tokens_used = 650; // Estimated tokens
            $usage_stats['tokens_this_minute'] += $tokens_used;
            $usage_stats['tokens_today'] += $tokens_used;
        }

        set_transient('wpcmt_aisays_daily_usage', $usage_stats, DAY_IN_SECONDS);
        update_option('wpcmt_aisays_total_generations', get_option('wpcmt_aisays_total_generations', 0) + 1, false);
    }

    /**
     * Get usage statistics.
     */
    public static function get_usage_stats(): array {
        $current_model = get_option('wpcmt_aisays_gemini_model', 'gemini-2.0-flash');
        $usage_stats = get_transient('wpcmt_aisays_daily_usage') ?: self::initialize_usage_stats($current_model);

        // Update model limits if model changed
        if ($usage_stats['model'] !== $current_model) {
            $usage_stats['limits'] = self::get_model_limits($current_model);
            $usage_stats['model'] = $current_model;
            set_transient('wpcmt_aisays_daily_usage', $usage_stats, DAY_IN_SECONDS);
        }

        return $usage_stats;
    }

    /**
     * Add admin menu pages.
     */
    public function add_admin_menu(): void {
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
        Plugin::$plugin_pages[] = $p1;
        Plugin::$plugin_pages[] = $p2;
    }

    /**
     * Register settings.
     */
    public function register_settings(): void {
        $settings = [
            'wpcmt_aisays_provider' => ['string', 'gemini'],
            'wpcmt_aisays_gemini_api_key' => ['string', ''],
            'wpcmt_aisays_openai_api_key' => ['string', ''],
            'wpcmt_aisays_language' => ['string', 'english'],
            'wpcmt_aisays_custom_language' => ['string', ''],
            'wpcmt_aisays_gemini_model' => ['string', 'gemini-2.0-flash'],
            'wpcmt_aisays_openai_model' => ['string', 'gpt-4o'],
            'wpcmt_aisays_display_mode' => ['string', 'automatic'],
            'wpcmt_aisays_display_position' => ['string', 'after_description'],
            'wpcmt_aisays_shortcode' => ['string', '[ai_says_product_description]'],
            'wpcmt_aisays_prompt_template' => ['string', $this->get_default_prompt_template(), 'sanitize_textarea_field'],
            'wpcmt_aisays_max_tokens' => ['integer', 1500, 'absint'],
        ];

        foreach ($settings as $option => $config) {
            register_setting('wpcmt_aisays_settings', $option, [
                'type' => $config[0],
                'default' => $config[1],
                'sanitize_callback' => $config[2] ?? 'sanitize_text_field',
                'show_in_rest' => false,
            ]);
        }
    }

    public function show_notices() {
        // phpcs:disable WordPress.Security.NonceVerification.Recommended
        if (isset($_GET['restored'])) {
            echo '<div class="notice notice-success is-dismissible"><p>'.esc_html__('Settings restored to defaults!', 'comet-ai-says').'</p></div>';
        }

        // Output settings errors
        settings_errors('wpcmt_aisays_settings');
    }

    /**
     * Admin settings page.
     *
     * @note Do not use 'wrap' wrapper  WP core JS relocates notices
     *
     * @see wp-admin/js/common.min.js - Automatically moves notices into .wrap containers
     */
    public function admin_page(): void {
        echo '<div class="wpcomet-wrap">';

        // Display tab navigation
        $this->display_tab_navigation();

        // Display usage stats and main content
        self::display_usage_stats();

        // Rest of your existing admin page content
        $current_provider = get_option('wpcmt_aisays_provider', 'gemini');
        $current_language = get_option('wpcmt_aisays_language', 'english');
        $custom_language = get_option('wpcmt_aisays_custom_language', '');
        $current_gemini_model = get_option('wpcmt_aisays_gemini_model', 'gemini-2.0-flash');
        $current_openai_model = get_option('wpcmt_aisays_openai_model', 'gpt-4o');
        $current_prompt_template = get_option('wpcmt_aisays_prompt_template', $this->get_default_prompt_template());
        $current_display_mode = get_option('wpcmt_aisays_display_mode', 'automatic');
        $current_display_position = get_option('wpcmt_aisays_display_position', 'after_description');
        $current_shortcode = get_option('wpcmt_aisays_shortcode', '[ai_says_product_description]');
        $current_max_tokens = get_option('wpcmt_aisays_max_tokens', 1500);
        ?>

<form method="post" action="options.php" autocomplete="off">
	<?php settings_fields('wpcmt_aisays_settings'); ?>
	<?php do_settings_sections('wpcmt_aisays_settings'); ?>

	<table class="form-table">
		<!-- Provider Selection -->
		<tr>
			<th scope="row">
				<label
					for="wpcmt_aisays_provider"><?php esc_html_e('AI Provider', 'comet-ai-says'); ?></label>
			</th>
			<td>
				<select id="wpcmt_aisays_provider" name="wpcmt_aisays_provider">
					<option value="gemini" <?php selected($current_provider, 'gemini'); ?>>
						<?php esc_html_e('Google Gemini (Recommended - Free tier available)', 'comet-ai-says'); ?>
					</option>
					<option value="openai" <?php selected($current_provider, 'openai'); ?>>
						<?php esc_html_e('OpenAI GPT (Paid)', 'comet-ai-says'); ?>
					</option>
				</select>
				<p class="description">
					<?php esc_html_e('Choose which AI provider to use for generating descriptions', 'comet-ai-says'); ?>
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
					<input type="text" id="wpcmt_aisays_gemini_api_key" name="wpcmt_aisays_gemini_api_key"
						value="<?php echo esc_attr(get_option('wpcmt_aisays_gemini_api_key')); ?>"
						class="regular-text api-key-field" autocomplete="off" />
					<button type="button" class="button" onclick="toggleVisibility('wpcmt_aisays_gemini_api_key')"
						style="position: absolute; right: 0; top: 0;"><?php esc_html_e('Show', 'comet-ai-says'); ?></button>
				</div>
				<p class="description">
					<?php esc_html_e('Get your free API key from', 'comet-ai-says'); ?>
					<a href="https://aistudio.google.com/app/apikey" target="_blank">Google AI Studio</a>
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
				<input type="text" id="wpcmt_aisays_openai_api_key" name="wpcmt_aisays_openai_api_key"
					value="<?php echo esc_attr(get_option('wpcmt_aisays_openai_api_key')); ?>"
					class="regular-text api-key-field" autocomplete="off" />
				<p class="description">
					<?php esc_html_e('Get your API key from', 'comet-ai-says'); ?>
					<a href="https://platform.openai.com/api-keys" target="_blank">OpenAI Platform</a>
				</p>
			</td>
		</tr>

		<!-- Gemini Model Selection -->
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

		<!-- OpenAI Model Selection -->
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

		<!-- Max Tokens -->
		<tr id="max-tokens-row">
			<th scope="row">
				<label
					for="wpcmt_aisays_max_tokens"><?php esc_html_e('Max Response Tokens', 'comet-ai-says'); ?></label>
			</th>
			<td>
				<input type="range" id="wpcmt_aisays_max_tokens" name="wpcmt_aisays_max_tokens" min="400" max="4000"
					step="100"
					value="<?php echo esc_attr($current_max_tokens); ?>"
					class="regular-text" />
				<span id="max-tokens-value" style="margin-left: 10px; font-weight: bold;">
					<?php echo esc_html($current_max_tokens); ?>
					<?php esc_html_e('tokens', 'comet-ai-says'); ?>
				</span>
				<p class="description">
					<?php esc_html_e('Maximum number of tokens for AI responses. Higher values = longer, more detailed descriptions.', 'comet-ai-says'); ?>
					<br>
					<strong><?php esc_html_e('Free tier allows:', 'comet-ai-says'); ?></strong>
					<span id="token-capacity-info">
						<?php
                                $current_model = get_option('wpcmt_aisays_gemini_model', 'gemini-2.0-flash');
        if (str_contains($current_model, '2.5')) {
            echo esc_html__('Up to 375,000 tokens daily with Gemini 2.5 Flash', 'comet-ai-says');
        } else {
            echo esc_html__('Up to 150,000 tokens daily with Gemini 2.0 Flash', 'comet-ai-says');
        }
        ?>
					</span>
					<br>
					<strong><?php esc_html_e('Recommended:', 'comet-ai-says'); ?></strong>
					<span
						id="recommended-tokens"><?php esc_html_e('1500-2500 tokens for comprehensive product descriptions', 'comet-ai-says'); ?></span>
				</p>
			</td>
		</tr>

		<!-- Display Settings -->
		<?php $this->render_display_settings($current_display_mode, $current_display_position, $current_shortcode); ?>

		<!-- Language Settings -->
		<?php $this->render_language_settings($current_language, $custom_language); ?>

		<!-- Prompt Template -->
		<tr>
			<th scope="row">
				<label
					for="wpcmt_aisays_prompt_template"><?php esc_html_e('Prompt Template', 'comet-ai-says'); ?></label>
			</th>
			<td>
				<?php $this->render_prompt_template($current_prompt_template); ?>
			</td>
		</tr>
	</table>

	<div class="form-actions">
		<?php submit_button(); ?>
		<?php submit_button(esc_html__('Restore Defaults', 'comet-ai-says'), 'secondary', 'restore-defaults', false); ?>
		<span>
			<input type="text" id="comet-settings-search"
				placeholder="<?php esc_attr_e('Search settings...', 'comet-ai-says'); ?>"
				class="regular-text">
		</span>
	</div>
</form>

<!-- Additional Information Cards -->
<div class="more-cards" style="display:flex;gap:2rem">
	<?php $this->render_prompt_guide_card(); ?>
	<?php $this->render_setup_instructions_card(); ?>
	<?php $this->render_support_card(); ?>
</div>

<?php
        echo '</div>'; // Close the single wrap div

        // Output the JavaScript
        $this->output_admin_javascript();
    }

    /**
     * Add meta box to product editor.
     */
    public function add_meta_box(): void {
        add_meta_box(
            'wpcmt_aisays_meta_box',
            esc_html__('AI Product Description', 'comet-ai-says'),
            [$this, 'render_meta_box'],
            'product',
            'normal',
            'high'
        );
    }

    /**
     * Render meta box content.
     *
     * @param mixed $post
     */
    public function render_meta_box($post): void {
        $ai_description = get_post_meta($post->ID, '_wpcmt_aisays_description', true);
        $product_language = get_post_meta($post->ID, '_wpcmt_aisays_language', true);
        $global_language = get_option('wpcmt_aisays_language', 'english');
        $provider = get_option('wpcmt_aisays_provider', 'gemini');
        $provider_name = 'gemini' === $provider ? 'Gemini' : 'GPT';

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
			<option value="english" <?php selected($product_language, 'english'); ?>><?php esc_html_e('English', 'comet-ai-says'); ?>
			</option>
			<option value="spanish" <?php selected($product_language, 'spanish'); ?>><?php esc_html_e('Spanish', 'comet-ai-says'); ?>
			</option>
			<option value="french" <?php selected($product_language, 'french'); ?>><?php esc_html_e('French', 'comet-ai-says'); ?>
			</option>
			<option value="german" <?php selected($product_language, 'german'); ?>><?php esc_html_e('German', 'comet-ai-says'); ?>
			</option>
			<option value="italian" <?php selected($product_language, 'italian'); ?>><?php esc_html_e('Italian', 'comet-ai-says'); ?>
			</option>
			<option value="portuguese" <?php selected($product_language, 'portuguese'); ?>><?php esc_html_e('Portuguese', 'comet-ai-says'); ?>
			</option>
			<option value="dutch" <?php selected($product_language, 'dutch'); ?>><?php esc_html_e('Dutch', 'comet-ai-says'); ?>
			</option>
			<option value="russian" <?php selected($product_language, 'russian'); ?>><?php esc_html_e('Russian', 'comet-ai-says'); ?>
			</option>
			<option value="japanese" <?php selected($product_language, 'japanese'); ?>><?php esc_html_e('Japanese', 'comet-ai-says'); ?>
			</option>
			<option value="korean" <?php selected($product_language, 'korean'); ?>><?php esc_html_e('Korean', 'comet-ai-says'); ?>
			</option>
			<option value="chinese" <?php selected($product_language, 'chinese'); ?>><?php esc_html_e('Chinese', 'comet-ai-says'); ?>
			</option>
			<option value="arabic" <?php selected($product_language, 'arabic'); ?>><?php esc_html_e('Arabic', 'comet-ai-says'); ?>
			</option>
			<option value="turkish" <?php selected($product_language, 'turkish'); ?>><?php esc_html_e('Turkish', 'comet-ai-says'); ?>
			</option>
			<option value="hindi" <?php selected($product_language, 'hindi'); ?>><?php esc_html_e('Hindi', 'comet-ai-says'); ?>
			</option>
			<option value="custom" <?php selected($product_language, 'custom'); ?>><?php esc_html_e('Custom Language', 'comet-ai-says'); ?>
			</option>
		</select>
	</div>

	<div style="margin-bottom: 15px;">
		<button type="button" id="generate-wpcmt-aisays" class="button button-primary"
			data-product-id="<?php echo absint($post->ID); ?>">
			<?php esc_html_e('Generate AI Description', 'comet-ai-says'); ?>
		</button>
		<span id="wpcmt-aisays-loading" style="display: none; margin-left: 10px;">
			<?php
                    // translators: %s: AI platform name
                    printf(esc_html__('Generating with %s...', 'comet-ai-says'), esc_html($provider_name)); ?>
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
			<span id="wpcmt-aisays-save-status" style="margin-left: 10px;"></span>
		</div>
	</div>

	<!-- ADDED BACK THE MISSING MODAL MARKUP -->
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

    /**
     * Save product language.
     *
     * @param mixed $post_id
     */
    public function save_product_language($post_id): void {
        // Verify nonce
        if (!isset($_POST['wpcmt_aisays_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['wpcmt_aisays_nonce'])), 'wpcmt_aisays_nonce')) {
            return;
        }

        // Check autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // Check permissions
        if (!current_user_can('edit_post', $post_id) || 'product' !== get_post_type($post_id)) {
            return;
        }

        // Save product language
        if (isset($_POST['wpcmt_aisays_language'])) {
            $language = sanitize_text_field(wp_unslash($_POST['wpcmt_aisays_language']));
            update_post_meta($post_id, '_wpcmt_aisays_language', $language);
        }
    }

    /**
     * Enqueue admin scripts.
     *
     * @param mixed $hook
     */
    public function enqueue_admin_scripts($hook): void {
        $allowed_pages = [
            'settings_page_wpcmt-aisays-settings',
            'product_page_wpcmt-aisays-table',
        ];

        if (in_array($hook, $allowed_pages)) {
            $this->enqueue_shared_admin_styles();
        }

        if ('post.php' === $hook || 'post-new.php' === $hook) {
            global $post_type;
            if ('product' === $post_type) {
                $this->enqueue_ai_scripts();
            }

            return;
        }

        if ('product_page_wpcmt-aisays-table' === $hook) {
            $this->enqueue_ai_scripts();
        }
    }

    /**
     * Products table page.
     */
    public function products_table_page(): void {
        if (!class_exists('WooCommerce')) {
            echo '<div class="wrap"><div class="error"><p>'.esc_html__('WooCommerce is required for this page to work.', 'comet-ai-says').'</p></div></div>';

            return;
        }

        // Use plugin_dir_path to get the correct path
        $plugin_path = plugin_dir_path(__FILE__).'../includes/class-ai-products-table.php';
        if (file_exists($plugin_path)) {
            require_once $plugin_path;
        } else {
            echo '<div class="wrap"><div class="error"><p>'.esc_html__('Products table class not found.', 'comet-ai-says').'</p></div></div>';

            return;
        }

        $products_table = new ProductsTable();
        $products_table->prepare_items();
        ?>
<div class="wpcomet-wrap">
	<?php $this->display_tab_navigation(); ?>
	<div id="wpcmt-aisays-bulk-progress"
		style="display: none; margin: 10px 0; padding: 10px; background: #f5f5f5; border-radius: 4px;">
		<div style="display: flex; justify-content: between; align-items: center; margin-bottom: 5px;">
			<div>
				<strong><?php echo esc_html__('Progress:', 'comet-ai-says'); ?></strong>
				<span id="wpcmt-aisays-progress-text">0/0</span>
			</div>
			<button type="button" id="wpcmt-aisays-stop-bulk" class="button button-secondary">
				<?php echo esc_html__('Stop', 'comet-ai-says'); ?>
			</button>
		</div>
		<div style="width: 100%; background: #ddd; border-radius: 3px;">
			<div id="wpcmt-aisays-progress-bar"
				style="height: 20px; background: #2271b1; border-radius: 3px; width: 0%; transition: width 0.3s;"></div>
		</div>
	</div>

	<div id="wpcmt-aisays-bulk-results" style="display: none; margin: 10px 0; padding: 10px; border-radius: 4px;"></div>

	<form method="get">
		<input type="hidden" name="post_type" value="product" />
		<input type="hidden" name="page" value="wpcmt-aisays-table" />
		<?php
                $products_table->search_box(esc_html__('Search Products', 'comet-ai-says'), 'search');
        $products_table->display();
        ?>
	</form>
</div>

<div id="wpcmt-aisays-modal"
	style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999;">
	<div
		style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 20px; border-radius: 5px; width: 80%; max-width: 600px; max-height: 80vh; overflow-y: auto;">
		<h3><?php esc_html_e('AI Generated Description', 'comet-ai-says'); ?>
		</h3>
		<div id="wpcmt-aisays-content"
			style="margin: 15px 0; padding: 15px; background: #f9f9f9; border-radius: 4px; min-height: 200px; white-space: pre-wrap;">
		</div>
		<button type="button" class="button"
			onclick="document.getElementById('wpcmt-aisays-modal').style.display='none';"><?php esc_html_e('Close', 'comet-ai-says'); ?></button>
	</div>
</div>
<?php
    }

    /**
     * Handle bulk generation.
     */
    public function handle_bulk_generation(): void {
        // Verify nonce and permissions
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'bulk-products') || !current_user_can('manage_products')) {
            wp_die(esc_html__('Security check failed', 'comet-ai-says'));
        }

        // Handle AJAX request for actual generation
        if (isset($_POST['action']) && 'generate_bulk_ai_descriptions' === $_POST['action'] && !empty($_POST['product_ids'])) {
            $product_ids = array_map('intval', $_POST['product_ids']);
            $results = $this->process_bulk_generation($product_ids);
            wp_send_json_success($results);
        }

        // Handle initial bulk action
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

    /**
     * Get default prompt template (public static).
     */
    public static function get_default_prompt_template_public(): string {
        $instance = new self();

        return $instance->get_default_prompt_template();
    }

    /**
     * Get language part (public static).
     */
    public static function get_lang_static(string $language, string $part = 'intro'): string {
        return self::get_language_part($language, $part);
    }

    /**
     * Get language part (instance method).
     */
    public function get_lang(string $language, string $part = 'intro'): string {
        return self::get_language_part($language, $part);
    }
}
?>