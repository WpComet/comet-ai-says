=== Comet AI Says: Product Descriptions ===
Contributors: wpcomet  
Tags: woocommerce, ai, product descriptions, gpt, custom fields  
Requires at least: 5.8  
Tested up to: 6.8
Requires PHP: 7.4  
Stable tag: 1.0.4 
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

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/comet-ai-says/`
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Go to **Settings → Comet AI Says** and enter your API key and you are good to go !

== Screenshots ==

1. Bulk generation interface for WooCommerce products.
2. Sample bulk generating descriptions.
3. Single product without AI desc
4. Single product with AI desc
5. Admin single edit product individual description generation.
6. Admin single generated description
7. Track usage
8. Plugin settings panel with API configuration and model selection.

== Changelog ==
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

No breaking changes in version 1.0.0.

== Features ==

- Doesn’t rely on additional third-party services
- Doesn’t overwrite your existing product descriptions
- Minimal performance impact
- No unnecessary calls on frontend or admin
- On-demand generation only — no background tasks
- Customizable prompt and language
- Supports multiple AI platforms: OpenAI, Gemini
- Choose from models like GPT-4o, Gemini 2.0 Flash
- Clean, bloat-free interface

== External Services ==

This plugin connects directly to third-party AI APIs (OpenAI, Gemini) to generate product descriptions. You must provide your own API keys.

