---
maintained_by: plugin-updater agent
description: Comprehensive testing procedures for comet-ai-says plugin updates and releases
last_updated: 2026-05-27
---

# Agent-Maintained Test Procedures

> **Note**: This file is maintained by the plugin-updater agent and supplements the human-written TESTS.md. Use this for comprehensive testing workflows, edge cases, and release validation.

## Test Categories

### 1. Core Functionality Tests

#### Plugin Installation & Activation
- [ ] Install via WordPress Dashboard → Plugins → Add New (upload)
- [ ] Activate without errors
- [ ] Default settings created: `wpcmt_aisays_*` options exist
- [ ] Admin menu item appears under Tools → Comet AI Says
- [ ] No database errors in WordPress logs

#### Admin Settings Page
- [ ] Settings page loads without errors
- [ ] API Provider selection (Gemini vs OpenAI) saves correctly
- [ ] API key fields accept and store credentials securely
- [ ] Model selection updates per provider (Gemini models vs OpenAI models)
- [ ] Default language selection (14 languages) saves correctly
- [ ] Display mode toggle (Automatic vs Manual) persists
- [ ] Display position selection works (4 position options)
- [ ] Custom prompt template field accepts and stores text
- [ ] Max tokens slider updates and saves
- [ ] "Reset to Defaults" button restores all settings

#### Products Table
- [ ] Products table loads with WP_List_Table formatting
- [ ] Product thumbnail displays correctly
- [ ] SKU column shows correct values
- [ ] Edit/View links navigate to correct pages
- [ ] **Row Actions**: Generate single, delete single, regenerate
- [ ] **Bulk Actions**: Select multiple products → Generate / Delete
- [ ] Pagination works with large product lists

#### Product Edit / Creation Screens
- [ ] Meta box appears on Edit Product page
- [ ] Language override dropdown shows all 14 languages
- [ ] Per-product language selection saves correctly
- [ ] "Generate Description" button triggers AJAX call
- [ ] Generated description appears in correct meta field
- [ ] Description persists after saving product

#### AI Generation - Gemini Provider
- [ ] Gemini API key validates correctly
- [ ] Product data sent to Gemini (name, short desc, tags, categories, attributes)
- [ ] Response parsed and stored in `_wpcmt_aisays_description` meta
- [ ] Rate limiting respected per configured limit
- [ ] Error: missing API key shows user-friendly message
- [ ] Error: API quota exceeded displays appropriate message
- [ ] Prompt template variables replaced correctly in request
- [ ] Max tokens limit respected in responses

#### AI Generation - OpenAI Provider
- [ ] OpenAI API key validates correctly
- [ ] Product data sent to OpenAI (name, short desc, tags, categories, attributes)
- [ ] Response parsed and stored in `_wpcmt_aisays_description` meta
- [ ] Rate limiting respected per configured limit
- [ ] Error: missing API key shows user-friendly message
- [ ] Error: insufficient credits/quota displays appropriate message
- [ ] Prompt template variables replaced correctly in request
- [ ] Max tokens limit respected in responses
- [ ] Switching from Gemini to OpenAI doesn't lose existing descriptions

#### Frontend Display - Automatic Mode
- [ ] Single product page displays AI description automatically
- [ ] Description appears at all 4 configured positions:
  - [ ] After short description
  - [ ] After description
  - [ ] After tabs
  - [ ] Bottom of product page
- [ ] Description styling matches theme
- [ ] Description only shows if generated (empty for products without AI desc)
- [ ] Original product description preserved (not replaced)

#### Frontend Display - Manual Mode (Shortcode)
- [ ] `[comet-ai-says-product-description]` shortcode works
- [ ] Shortcode displays correct AI description for current product
- [ ] Shortcode works when placed in custom page templates
- [ ] Shortcode respects per-product language override
- [ ] Proper fallback message when no AI description exists

#### Internationalization (i18n) - 14 Languages
- [ ] Generate translation POT file: `wp i18n make-pot . ./i18n/languages/comet-ai-says.pot`
- [ ] Test descriptions generated in each language:
  - [ ] English
  - [ ] Spanish
  - [ ] French
  - [ ] German
  - [ ] Italian
  - [ ] Portuguese
  - [ ] Dutch
  - [ ] Russian
  - [ ] Japanese
  - [ ] Korean
  - [ ] Chinese (Simplified)
  - [ ] Arabic
  - [ ] Turkish
  - [ ] Hindi
- [ ] Language switcher on edit product page shows all options
- [ ] Per-product language selection works across all languages

---

### 2. Edge Cases & Error Handling

#### AI Provider Input/Output Edge Cases
- [ ] **Empty Product Data**: Product with no name, description, or attributes
- [ ] **Maximum Product Length**: Very long name + description respects max tokens
- [ ] **Special Characters**: Emoji, accents, HTML in product names/descriptions
- [ ] **Very Short Product Data**: Single word product name generates properly
- [ ] **Duplicate Products**: Each gets unique AI description
- [ ] **Bulk Generation Mixed States**: Some with existing descriptions, some without
- [ ] **Rate Limit Exceeded**: Displays graceful error message
- [ ] **API Timeout**: Network issue handled cleanly
- [ ] **Invalid API Key**: Clear error message shown, settings indicate issue

#### Database & Storage Edge Cases
- [ ] **Deleted Product**: AI description meta data handled properly
- [ ] **Malformed Meta Value**: Plugin doesn't break on corrupted data
- [ ] **Large Bulk Generation**: 100+ products generated without timeouts
- [ ] **Concurrent Requests**: Same product generated simultaneously (race condition)
- [ ] **Database Corruption**: Recovery graceful, no data loss

#### UI/UX Edge Cases
- [ ] **Very Long AI Description**: Displays/truncates properly in frontend
- [ ] **Special Characters in Display**: HTML entities, Unicode rendered correctly
- [ ] **Missing Translation**: Falls back to English gracefully

---

### 3. Integration Testing

#### Caching Compatibility
- [ ] **Page Cache** (WP Super Cache): New descriptions display on cached pages
- [ ] **Object Cache** (Redis/Memcached): Settings persist through cache flushes
- [ ] **Fragment Cache**: Updates visible even with page caching
- [ ] **CDN Cache** (CloudFlare): New descriptions visible after invalidation

#### WordPress & WooCommerce Hooks
- [ ] Product data hooks called correctly
- [ ] WordPress filter hooks fire properly
- [ ] No conflicts with WooCommerce product hooks
- [ ] Settings saved via `update_option()` correctly
- [ ] Custom meta saved via `update_post_meta()` correctly

#### Third-Party Plugin Compatibility
- [ ] **Yoast SEO**: Product descriptions don't conflict
- [ ] **Rank Math**: AI descriptions coexist with SEO optimizations
- [ ] **WooCommerce PDF Invoice**: Generated descriptions handled appropriately
- [ ] **Inventory Management**: Stock data not affected by generation
- [ ] **Product Sync**: Data syncs correctly with AI descriptions present

#### Theme Compatibility
- [ ] Default WooCommerce theme compatible
- [ ] Popular themes compatible (Storefront, Neve, etc.)
- [ ] CSS doesn't break theme layout
- [ ] Description displays correctly in theme product templates
- [ ] Shortcode renders properly in theme page builders

#### Security Testing
- [ ] **Nonce Verification**: All AJAX actions have valid nonce checks
- [ ] **Capability Checks**: Only users with `manage_options` can generate/delete
- [ ] **Input Sanitization**: User input sanitized before API requests
- [ ] **Output Escaping**: AI response escaped before display
- [ ] **API Key Security**: Keys not logged or exposed in debug
- [ ] **SQL Injection**: Meta queries safe
- [ ] **CSRF Protection**: Forms protected with nonces

#### Performance Testing
- [ ] **Admin Load Time**: Products table loads in <2 seconds with 500+ products
- [ ] **Frontend Load Time**: Product page impact minimal with AI description
- [ ] **AJAX Response Time**: Single generation request <5 seconds
- [ ] **Bulk Generation**: 50 products generated within reasonable time
- [ ] **Memory Usage**: No memory exhaustion with large bulk operations
- [ ] **Database Queries**: Minimal additional queries in debug log

---

### 4. Browser & Device Testing

#### Browser Compatibility
- [ ] Chrome/Chromium (latest)
- [ ] Firefox (latest)
- [ ] Safari (latest)
- [ ] Edge (latest)
- [ ] Mobile browsers (iOS Safari, Chrome Mobile)

#### Device Testing
- [ ] Desktop (Windows, Mac, Linux)
- [ ] Tablet (iPad, Android tablet)
- [ ] Mobile (iPhone, Android phone)
- [ ] Responsive design: Product description readable on all screen sizes

---

### 5. Deactivation & Uninstall Tests

#### Deactivation
- [ ] Plugin deactivates without errors
- [ ] Admin menu item disappears
- [ ] Frontend displays original descriptions only
- [ ] Settings remain in database

#### Uninstall (with "Delete plugin data" option)
- [ ] Plugin deletes cleanly from WordPress
- [ ] All `wpcmt_aisays_*` settings removed
- [ ] All `_wpcmt_aisays_description` post meta removed
- [ ] No orphaned data in database
- [ ] Plugin reinstall works cleanly after uninstall

---

## AI Provider Testing Matrix

Test each scenario against BOTH Gemini and OpenAI:

| Scenario | Gemini | OpenAI |
|----------|--------|--------|
| Single product generation | [ ] | [ ] |
| Bulk generation (10 products) | [ ] | [ ] |
| All 14 languages | [ ] | [ ] |
| All 4 display positions | [ ] | [ ] |
| Rate limit handling | [ ] | [ ] |
| Invalid API key | [ ] | [ ] |
| Long product description | [ ] | [ ] |
| Special characters | [ ] | [ ] |
| Provider switching | [ ] | [ ] |

---

## Environment Verification

Before running tests, verify:

```
PHP Version:       7.4+ (tested on 8.2)
WordPress:         5.8+ (tested on 6.6)
Plugin Version:    X.Y.Z (in comet-ai-says.php)
Namespace:         WpComet\AISays
Settings Prefix:   wpcmt_aisays_
Meta Key:          _wpcmt_aisays_description
AJAX Prefix:       wpcmt_aisays_
```

---

## Test Execution Workflow

### Pre-Test Preparation
1. [ ] Fresh WordPress installation or clean database
2. [ ] WooCommerce activated and configured
3. [ ] Both Gemini and OpenAI API keys ready
4. [ ] Debug mode enabled in wp-config.php
5. [ ] Log file cleared: `wp-content/debug.log`

### Test Execution
1. [ ] Run Core Functionality Tests (Section 1)
2. [ ] Run Edge Cases Tests (Section 2)
3. [ ] Run Integration Tests (Section 3)
4. [ ] Run Browser/Device Tests (Section 4)
5. [ ] Run Deactivation Tests (Section 5)
6. [ ] Run AI Provider Testing Matrix (both providers)

### Post-Test Validation
- [ ] No PHP warnings/errors in debug.log
- [ ] No console errors in browser dev tools
- [ ] All AJAX requests completed successfully
- [ ] Database integrity verified (no orphaned records)
- [ ] Settings persisted correctly across sessions

---

## Release Testing Checklist

Run before creating a new release:

- [ ] All test categories above completed and passing
- [ ] Both Gemini and OpenAI providers fully tested
- [ ] All 4 display modes tested with all 4 positions
- [ ] All 14 languages tested for description generation
- [ ] Settings page loads without errors
- [ ] Products table loads without errors
- [ ] No PHP warnings in debug.log
- [ ] No JavaScript console errors
- [ ] AJAX nonce verification working
- [ ] User capability checks enforced
- [ ] Database cleanup on uninstall verified

---

## Notes for Agent

- When making changes to AI provider logic, test BOTH Gemini and OpenAI paths
- When modifying settings, verify they persist and affect behavior correctly
- When updating display logic, test all 4 position options
- When changing language support, test with all 14 languages
- Always verify AJAX handlers have proper nonce verification
- Always verify user capability checks (`manage_options`)
- Run security tests for new AJAX endpoints or user input processing
