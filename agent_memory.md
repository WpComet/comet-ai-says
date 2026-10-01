# Agent Memory: Comet AI Says

This file stores persistent contextual memory, verified conventions, user constraints, and architectural standards for AI agents working in this repository.

---

## 1. Golden Rules & User Constraints

- **Strict Git Rule**: **NEVER make automatic Git commits or git pushes without explicit user confirmation or command.** Always leave generated or refactored code staged or unstaged in the working tree for user review.
- **Single Source of Truth**: Unified settings storage in `wpcmt_aisays_settings` via `Config::get_option()` and `Config::update_setting()`. Never create individual `wp_options` rows.
- **Frontend & Admin UI**:
  - Scoped Bulma CSS inside `.comet-aisays`.
  - Dark mode default (`data-theme="dark"` on `#comet-aisays-root`).
  - No jQuery. Pure vanilla ES6+ JavaScript.
  - Page wrapper pattern: `<div class="container is-fluid pt-5">`.

---

## 2. Environment Paths & Ecosystem Topology

| Role | Absolute Path / URL | Purpose |
| :--- | :--- | :--- |
| **Active Development Workspace** | `D:\wamp64\www\alldemos\wp-content\plugins\comet-ai-says` | Live Git repo, development working tree. |
| **Local WordPress Test Site** | `http://localhost/alldemos/wp-admin/` | Test environment with WooCommerce and sample catalog. |
| **Clean Isolated PCP Test Site** | `D:\wamp64\www\WPlatest\wp-content\plugins\comet-ai-says` | Isolated WordPress instance with WordPress Plugin Check (PCP). Zero dev files. |
| **Production Distribution Store** | `D:\wamp64\www\public-os\` | Destination for packaged production release `.zip` files. |
| **GitHub Remote** | `https://github.com/WpComet/comet-ai-says/` | Upstream Git repository (branch `main`). |
| **Sibling WpComet Plugins** | `D:\wamp64\www\alldemos\wp-content\plugins\comet-launchpad` | Reference architecture for modules, ignore-syncing, and stubs. |
| **Boilerplate Generator** | `D:\wamp64\www\comet-plugin-compiler` | Standalone generator producing new WpComet plugins with standardized tooling. |

---

## 3. Tooling Matrix

| Command | Script | Target & Behavior |
| :--- | :--- | :--- |
| `npm run sync` | `dev/sync.js` | Fast copy from working tree to `WPlatest` (excludes dev files). **No zip, no bump, no commit.** |
| `npm run sync:ignore` | `dev/sync-ignore.js` | Re-generates `.distignore` from `.gitignore` + distribution exclusions and updates `.gitattributes`. |
| `npm run makepot` | `wp i18n make-pot` | Generates `i18n/languages/comet-ai-says.pot` with proper exclusions. |
| `npm run bump` | `dev/bump-version.js` | Increments patch version across `package.json`, main PHP header, and `readme.txt` Stable tag; syncs to `WPlatest`. |
| `npm run archive` | `dev/archive.js` | Builds production `.zip` in `public-os` and syncs clean copy to `WPlatest`. |
| `npm run release` | `dev/release.js` | Full interactive/automated release: runs tests, bumps version, commits, tags, archives, deploys to `WPlatest`, and pushes. |
| `npm run release:dry` | `dev/release.js --dry-run` | Audits code integrity, simulates version bump and commit message, and syncs preview copy to `WPlatest` for PCP testing. |
| `npm test` | `dev/check-integrity.js` | Validates PHP syntax (`php -l`), JS syntax (`node -c`), and PHPUnit suite (`composer test`). |
