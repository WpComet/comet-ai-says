## Agent Guidance for Working on Comet AI Says

Quick, actionable context and conventions for AI coding agents working in this repository.

---

### 1. What This Repository Is
- **Plugin Name**: Comet AI Says: Product Descriptions
- **WordPress Environment**: Target PHP 7.4 - 8.2+, WordPress 6.0+, WooCommerce 7.0+ (HPOS declared).
- **Core Namespace**: `WpComet\AISays\`
- **Autoloading**: PSR-4 autoloading via [includes/Autoload.php](file:///d:/wamp64/www/alldemos/wp-content/plugins/comet-ai-says/includes/Autoload.php).
- **Primary Entry Point**: [comet-ai-says.php](file:///d:/wamp64/www/alldemos/wp-content/plugins/comet-ai-says/comet-ai-says.php)

---

### 2. Architecture & Key Files

| Component | File Path | Description |
| :--- | :--- | :--- |
| **Bootstrap & Lifecycle** | [comet-ai-says.php](file:///d:/wamp64/www/alldemos/wp-content/plugins/comet-ai-says/comet-ai-says.php) | Constants (`WPCMT_AISAYS_VERSION`), singleton init, HPOS registration, asset registration. |
| **Central Config** | [includes/Config.php](file:///d:/wamp64/www/alldemos/wp-content/plugins/comet-ai-says/includes/Config.php) | Unified option storage (`wpcmt_aisays_settings`), in-memory caching, model definitions, rate limits, supported languages. |
| **Admin Interface** | [includes/AdminInterface.php](file:///d:/wamp64/www/alldemos/wp-content/plugins/comet-ai-says/includes/AdminInterface.php) | Bulma-based settings tabs, onboarding flow, product edit meta box, AJAX handlers, prompt studio. |
| **AI Generation Engine**| [includes/AIGenerator.php](file:///d:/wamp64/www/alldemos/wp-content/plugins/comet-ai-says/includes/AIGenerator.php) | Direct REST calls to Google Gemini & OpenAI API endpoints, vision downsampling, rate-limit retry logic. |
| **Catalog Table** | [includes/ProductsTable.php](file:///d:/wamp64/www/alldemos/wp-content/plugins/comet-ai-says/includes/ProductsTable.php) | `WP_List_Table` implementation for WooCommerce product catalog with concurrent bulk generation. |
| **Frontend Display** | [includes/FrontendDisplay.php](file:///d:/wamp64/www/alldemos/wp-content/plugins/comet-ai-says/includes/FrontendDisplay.php) | Automatic hook placement and shortcode `[comet-ai-says-product-description]` rendering. |
| **Diagnostics Suite** | [includes/Status.php](file:///d:/wamp64/www/alldemos/wp-content/plugins/comet-ai-says/includes/Status.php) & `includes/LiveTests/` | Real-time automated test runner verifying API handshake, rate limits, downsampling, and metadata. |

---

### 3. Core Development & Coding Conventions

1. **Unified Settings Storage**:
   - Always use `Config::get_settings()` and `Config::get_option($key, $default)` to read settings.
   - Always use `Config::update_setting($key, $val)` or `Config::update_settings($array)` to save settings.
   - Stored in a single WordPress option: `wpcmt_aisays_settings` with internal memory caching.
   - Never query or write individual option rows.

2. **Security & Validation**:
   - AJAX actions must verify nonces via `check_ajax_referer()` or `wp_verify_nonce()`.
   - Admin capabilities: check `current_user_can('manage_options')` or `current_user_can('edit_products')`.
   - Sanitize all inputs (`sanitize_text_field`, `absint`, `wp_unslash`) and escape all outputs (`esc_html`, `esc_attr`, `wp_kses_post`).
   - Never hardcode or log raw API keys. Retrieve only via `Config::get_option()`.

3. **Design System & UI**:
   - Admin UI uses scoped Bulma CSS with `.comet-aisays` wrapper.
   - Dark/Light mode theme switching is controlled via `data-theme="dark"` or `data-theme="light"` on `#comet-aisays-root`.
   - Semantic CSS variables (`--comet-primary`, `--comet-surface-card`, `--comet-border`, etc.) in `assets/admin-overrides.css`.
   - Standard page layout wraps content inside `<div class="container is-fluid pt-5">`.

4. **Localization (i18n)**:
   - Text domain: `comet-ai-says`.
   - Run `wp i18n make-pot . ./i18n/languages/comet-ai-says.pot` whenever user-facing strings are modified.

---

### 4. Testing & Verification Workflows

- **Automated Unit Tests**:
  ```bash
  composer test
  ```
  Runs the PHPUnit test suite in `tests/Unit/`.
- **Pre-Release Integrity & Syntax Check**:
  ```bash
  npm test
  ```
  Runs `dev/check-integrity.js` validating PHP syntax (`php -l`), JavaScript syntax (`node -c`), and PHPUnit tests.
- **Live Diagnostics Runner**:
  ```bash
  php dev_llm/run_all_live_tests.php
  ```
  Runs live integration checks against active WordPress and AI endpoints.

---

### 5. Release Workflow

Releases are automated via the built-in release script:
```bash
npm run release          # Patch version bump (e.g. 1.4.0 -> 1.4.1)
npm run release:minor    # Minor version bump (e.g. 1.4.0 -> 1.5.0)
npm run release:major    # Major version bump
npm run release:dry      # Dry run (integrity check only)
```
The release script automatically:
1. Runs PHPUnit & syntax integrity audits.
2. Updates version in `package.json`, `comet-ai-says.php`, and `readme.txt` (`Stable tag:`).
3. Creates a Git commit and annotated tag.
4. Generates a production `.zip` in `d:\wamp64\www\public-os\`.
5. Deploys a clean, isolated release copy (zero dev files) to `D:\wamp64\www\WPlatest\wp-content\plugins\comet-ai-says` for WordPress Plugin Check (PCP) verification.
6. Pushes commits and tags to `origin/main`.

