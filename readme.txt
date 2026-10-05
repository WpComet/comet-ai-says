=== Comet AI Says: Product Descriptions ===
Contributors: wpcomet  
Tags: woocommerce, ai, product descriptions, gpt, custom fields  
Requires at least: 6.0  
Tested up to: 7.1
Requires PHP: 7.4  
Stable tag: 1.4.2
License: GPLv3  
License URI: https://www.gnu.org/licenses/gpl-3.0.html  

Generate contextual AI product descriptions on-the-fly and store them in custom fields without messing with your existing descriptions.

== Description ==

**Smart AI-powered product descriptions—without compromising your content or control.**

Comet AI Says is a lightweight, privacy-conscious WordPress plugin that generates contextual AI product descriptions on demand. It’s designed for WooCommerce store owners who want to enhance their product pages with AI insights—without replacing or interfering with their original content.

### 🔧 Why Comet AI Says Stands Out

- **No Third-Party Dependencies**  
  Unlike most AI plugins, Comet AI Says doesn’t rely on external middleman services. You connect directly to your chosen AI provider—OpenAI, Gemini, and more.

- **Preserves Your Original Descriptions**  
  Your human-written product descriptions stay untouched. AI-generated content is stored separately in custom fields, giving you full editorial control.

- **Zero Performance Impact**  
  No background processes. No unnecessary API calls. No frontend or admin bloat. The plugin only runs when you trigger it.

- **Tone Presets Library**  
  Select from curated copywriting presets designed for different store niches—Luxury & Elegant, Short & Punchy for Social, Technical & Specs-Focused, SEO & Benefit-Driven, and Artisan Storyteller—or customize freely with live template variables.

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/comet-ai-says/`
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Go to **Settings → Comet AI Says** and enter your API key and you are good to go !

== Screenshots ==

1. **AI Engine & Settings Studio**: Modern Dark Mode dashboard featuring Google AI Studio model selection cards, capability badges, quota meters, API key mask toggle, and token controls.
2. **Product Descriptions Catalog Table**: Full WooCommerce product management table with instant AI status indicators, single-click generation, and bulk operations.
3. **AI Description Preview Modal**: Fast preview modal showing generated descriptions with one-click clipboard copying.
4. **Status & Live Diagnostics Suite**: Automated live environment tests verifying API handshake, rate limits, catalog metadata, and image downsampling pipeline.
5. **On-Demand API Usage & Rate Limits**: Asynchronous slide drawer showing live request limits, token consumption, and daily quotas.
6. **Prompt Engineering Studio & Placement**: Customizable prompt template editor with preset tone library, clickable variable insertion pills, and automatic display placement settings.

== Changelog ==

= 1.4.1 =
* Feature: Preset Prompts Library: Instant tone presets (Luxury & Elegant, Short & Punchy for Social, Technical & Specs-Focused, SEO & Benefit-Driven, Storyteller & Artisan) with real-time prompt preview updates.
* Feature: Seamless activation redirect: First-time plugin activation automatically routes store administrators to the settings and onboarding wizard.
* UI/UX: Interactive tone archetype selector with contextual descriptions, one-click template application, and visual feedback flash animation.
* Testing: Expanded automated PHPUnit unit test suite with comprehensive prompt preset catalog and activation guard tests.

= 1.4.0 =
* Certified: Official WordPress Plugin Check (PCP) compliance with zero errors and zero warnings.
* Compatibility: Fully verified with WordPress 7.1 and WooCommerce 9.6+ (High-Performance Order Storage compatible).
* Security: Hardened output escaping using `wp_kses_post` and input sanitization across all table queries.
* Core: Standardized filesystem operations with core WordPress helper functions (`wp_delete_file`, `wp_strip_all_tags`).
* Performance: Database query caching implemented for catalog coverage statistics via `wp_cache_get` and `wp_cache_set`.
* UI/UX: Refined Bulma admin theme controls, enhanced dark/light mode toggle transitions, and centered catalog table selectors.
* i18n: Complete translators comment annotations and standardized positional argument placeholders across all translatable strings.

= 1.3.9 =
* Feature: Google AI Studio style interactive model selection tiles with capability badges, quota meters, and direct keyboard accessibility.
* Core: Gemini 3.6 Flash configured as the recommended stable default model.
* Reliability: Multi-tier fallback cascade (`gemini-3.6-flash` -> `gemini-3.5-flash` -> `gemini-2.5-flash`) providing zero-downtime resilience against Google AI rate-limits and temporary surges.
* Feature: Dedicated "Status & Diagnostics" tab featuring automated live tests (API handshake, rate limits, WooCommerce catalog metadata, image downsampling pipeline).
* UX: Rebuilt "Usage" panel into an on-demand slide drawer loaded asynchronously via REST API with skeleton loaders.
* Performance: Rebuilt bulk generator with concurrent worker pool (2 workers for generation, 4 for deletion) cutting batch processing time in half.
* Performance: Server-side automatic image downsampling (max 800x800) and AVIF conversion, reducing multimodal payload size by ~80% and speeding up vision requests.
* Reliability: Client-side AJAX timeout extended to 120s for intensive multimodal and reasoning tasks.
* Fixed: Progress bar HTML5 fill values and vibrant gradient styling across WebKit and Mozilla browsers.
* Fixed: Bulk progress card now remains visible upon completion with clear summary metrics, dismiss control, and expandable error details accordion.
* Fixed: Strict validation preventing temporary API overload or error messages from replacing existing descriptions.
* Fixed: Table row hover contrast in dark mode across striped tables.
* Fixed: Eliminated window horizontal scrollbar using container-level `overflow-x: clip` while preserving sticky elements.
* Testing: Comprehensive PHPUnit test suite (48 automated unit tests, 143 assertions) and pre-release integrity check.
* i18n: Added `load_plugin_textdomain` support for custom localization bundles.

= 1.3.1 =
* Added: Support for Google Gemini 3.8 Flash as the primary recommended flagship model.
* Added: Support for Gemini 3.7 Flash and Gemini 3.5 Flash-Lite.
* Added: OpenAI GPT-4o-mini support.
* Added: Scoped Bulma-Admin design system with adaptive `--comet-*` semantic tokens and dark/light theme switching.
* Added: Clickable variable pill insertion in the Prompt Engineering Studio.
* Refactored: Modernized plugin architecture with PSR-4 autoloader (`WpComet\AISays\`) matching WpComet standards (`comet-launchpad`, `comet-levelup`).
* Fixed: Fatal error in FrontendDisplay where null check was evaluating the wrong variable.
* Fixed: Products table filters (`no_ai`, `has_ai`, `no_full`, `both_missing`) now correctly query WooCommerce product metadata.
* Security: Strengthened capability checks (`edit_products`) and nonce verifications across all AJAX endpoints.
* Compatibility: Full WooCommerce HPOS (High-Performance Order Storage) declaration.

= 1.3.0 =
* Added: Support for Gemini 3.5 Flash model
* Updated: Default model is now Gemini 3.5 Flash
* Updated: Gemini 3.1 models promoted out of Preview to General Availability (GA)
* Updated: WordPress 7.0 "Armstrong" compatibility
* Removed: Sunset Gemini 2.x models (2.0 Flash, 2.5 series) support

= 1.2.0 =
* Added: Support latest 3.1 model
* Added: Onboarding screen for new installs.
* Updated: Info sections and model comparisons
* Bugfix: Added missing $categories param for generation

= 1.1.7 =
* Added: Support for Gemini 3 Flash and Gemini 3 Pro (Preview).
* Added: Native "Deep Think" reasoning support for Gemini 3 models.
* Added: Support for Gemini 2.5 Flash-Lite for high-speed, cost-efficient processing.
* Updated: Model selection UI with categorized groups (Next-Gen, Stable, Legacy).
* Updated: Rate limit indicators and model descriptions for the late 2025 API landscape.
* Deprecated: Gemini 2.0 Flash models moved to the Legacy section.

= 1.1.5 =
- Simplified and solidified Gemini 3.0 models for mid-long term
- Small update to prompt method for more contextual and accurate descriptions
- Minor bug fixes

= 1.1.3 =
- Added proper Gemini 3.0 models w/ necessary adjustments 
- Up to date information on models comparison

= 1.1.1 =
- More granular precise scripts loading

= 1.1.0 =
- Added delete functionality across the board
- Single source of truth for ajax actions
- Introduced a detailed list of tests.md for hand checking internally 

= 1.0.5 =
* bugfix:  admin_notices interfering , change php removal of notices to css method.

= 1.0.4 =
* More granular init
* Admin refactor;  admin notices, better max token ranges and language templates handling, api key visibility toggle,
* shortcode bugfix

= 1.0.2 =
* max_token adjustments

= 1.0.1 =
* Removed unnecessary models

= 1.0.0 =
* Initial release
* Supports Gemini and OpenAI
* Customizable prompt, language, and model
* Bulk generation and shortcode display

== Upgrade Notice ==

= 1.4.0 =
Recommended update. Fully verified with WordPress 7.1 and WooCommerce 9.6+. Includes official Plugin Check (PCP) certification, UI refinements, query caching, and hardened security.

== Features ==

- Doesn’t rely on additional third-party services
- Doesn’t overwrite your existing product descriptions
- Minimal performance impact
- No unnecessary calls on frontend or admin
- On-demand generation only — no background tasks
- Customizable prompt and language
- Supports multiple AI platforms: OpenAI, Gemini
- Choose from models like GPT-4o, Gemini 3.5 Flash
- Clean, bloat-free interface

== Limitations ==

Google Gemini does not support AVIF files as of late 2025

== External Services ==

This plugin connects to third-party AI services directly to generate product descriptions, no middleware or extra services between. You must provide your own API keys for these services.

= Google Gemini AI =

* **Service**: Google's Gemini AI API for generating product descriptions
* **What data is sent**: Product information (name, description, categories, attributes, featured image) and your custom prompt template
* **When data is sent**: When you manually generate descriptions via the admin interface or bulk operations
* **Terms of Service**: https://policies.google.com/terms
* **Privacy Policy**: https://policies.google.com/privacy

= OpenAI GPT =

* **Service**: OpenAI's GPT API for generating product descriptions  
* **What data is sent**: Product information (name, description, categories, attributes, featured image) and your custom prompt template
* **When data is sent**: When you manually generate descriptions via the admin interface or bulk operations
* **Terms of Service**: https://openai.com/terms/
* **Privacy Policy**: https://openai.com/privacy/

= Data Processing Notes =

* Product data is sent securely via HTTPS to the respective AI service APIs
* No data is stored by the AI services beyond the immediate request processing
* You must obtain and configure your own API keys for these services
* The plugin does not send any personally identifiable information (PII) unless included in your product data