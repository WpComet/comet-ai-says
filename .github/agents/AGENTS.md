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

Agent behaviour and constraints
- Do not add or expose API keys or other secrets in code or commits.
- Prefer linking to existing docs rather than duplicating content. See `Link, don't embed`.
- When suggesting code changes, reference the specific file(s) and lines.
- Run manual tests described in [TESTS.md](../../TESTS.md) before proposing release changes.

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

Next recommended customizations
- Add a `.github/copilot-instructions.md` or expand this file with repository-specific instructions for PRs and CI (if/when CI is added).
- Create an automated skill to run the checklist in `TESTS.md` and report failures.
