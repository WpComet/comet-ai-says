## Agent guidance for working on Comet AI Says

Purpose: give AI coding agents quick, actionable context to be productive in this repository.

What this repo is
- WordPress plugin (PHP) that generates AI product descriptions for WooCommerce.
- Key entry: [comet-ai-says.php](../../comet-ai-says.php)
- Main code: [includes/](../../includes/) (admin, generator, frontend)
- Assets: [assets/](../../assets/) and translations under [i18n/languages/](../../i18n/languages/)

Quick checks and commands
- Manual QA checklist: follow [TESTS.md](../../TESTS.md)
- Plugin header and release process: follow notes in [TESTS.md](../../TESTS.md) (SVN tagging for wordpress.org)

Release readiness checklist
Use this before suggesting or approving any release changes:
1. **Version consistency** — verify all three match:
   - Plugin header in [comet-ai-says.php](../../comet-ai-says.php#L5) (`* Version: X.Y.Z`)
   - [readme.txt](../../readme.txt#L5) (`Stable tag: X.Y.Z`)
   - [TESTS.md](../../TESTS.md) environment checks section
2. **i18n updated** — run `wp i18n make-pot . ./i18n/languages/comet-ai-says.pot` if any strings were added/changed
3. **No debug code** — check [comet-ai-says.php](../../comet-ai-says.php#L141) and [assets/admin-shared.js](../../assets/admin-shared.js#L67) are clean (comments should be commented)
4. **Changelog entry** — [readme.txt](../../readme.txt#L38) includes new version entry under `== Changelog ==`
5. **Manual QA passed** — all items in [TESTS.md](../../TESTS.md) (Core Functionality, Settings, Frontend, i18n, Edge Cases, Integration) must be checked
6. **No secrets exposed** — verify API keys are only retrieved via `get_option()`, never hardcoded
7. **File structure clean** — .distignore properly excludes dev files; [INTERNAL.md](../../INTERNAL.md) and [TODO.md](../../TODO.md) are updated or cleaned

Agent behaviour and constraints
- Do not add or expose API keys or other secrets in code or commits.
- Prefer linking to existing docs rather than duplicating content. See `Link, don't embed`.
- When suggesting code changes, reference the specific file(s) and lines.
- Run manual tests described in [TESTS.md](../../TESTS.md) before proposing release changes.
- **For release changes**: Do NOT suggest changes to plugin header, readme.txt, or version numbers without user approval. Always verify version consistency.
- **During release**: Only update version in plugin header, readme.txt, and changelog. Do not change other files unless needed for the feature/fix.

Where to look first
- [comet-ai-says.php](../../comet-ai-says.php) — plugin bootstrap and constants
- [includes/class-admin-interface.php](../../includes/class-admin-interface.php) — admin screens and settings
- [includes/class-ai-generator.php](../../includes/class-ai-generator.php) — generation logic and API interactions
- [TESTS.md](../../TESTS.md) — release checklist and environment notes
- [readme.txt](../../readme.txt) — plugin description, changelog, and requirements

Common development notes
- Local dev: this project expects a WordPress environment (WAMP is used in repo layout).
- PHP: target PHP 8.2 (see [TESTS.md](../../TESTS.md) and plugin header).
- i18n: POT file in `i18n/languages/comet-ai-says.pot` — use `wp i18n make-pot` as noted in [TESTS.md](../../TESTS.md).

If you're unsure
- Ask the user for clarification and link to the relevant doc (e.g., TESTS.md, TODO.md, INTERNAL.md).

SVN release workflow (WordPress.org)
The plugin uses TortoiseSVN on Windows for deployment (see [TESTS.md](../../TESTS.md) "Update procedure"). Agent notes:
- Releases happen via SVN tagging at `https://plugins.svn.wordpress.org/comet-ai-says/tags/X.Y.Z`
- Changes are committed to `/trunk` first, then tagged
- WordPress.org automatically detects stable tags and deploys to users
- Do not suggest changes to the release process without understanding SVN workflow
- When assisting with release, ensure user follows: (1) local git zip for backup, (2) SVN update, (3) file changes, (4) SVN commit, (5) tag creation, (6) final SVN update

Next recommended customizations
- Add a `.github/copilot-instructions.md` to document GitHub-specific workflows (if GitHub Actions CI is planned)
- Create a `.github/workflows/release.yml` to automate version checks and i18n generation on release PRs
- Create an automated skill to verify [TESTS.md](../../TESTS.md) checklist completion
