# Release & Update Instructions for Comet AI Says

## Overview

This plugin is distributed via WordPress.org using SVN (Subversion). Releases follow a trunk → tag workflow. All releases require manual verification before deployment.

## Current Release Status

| Item | Version | Status |
|------|---------|--------|
| Plugin version | 1.3.0 | ✅ Ready |
| readme.txt stable tag | 1.3.0 | ✅ Aligned |
| Requires PHP | 7.4 | ✅ Current |
| Tested up to | 7.0 | ✅ Current |

See [TESTS.md](../TESTS.md) for full environment and release checklist.

## Pre-Release Verification

**Before suggesting or making any release changes:**

### Version Consistency Check
All three sources must match exactly. Example for v1.4.0:
```
1. comet-ai-says.php, line 5:   * Version: 1.4.0
2. readme.txt, line 5:          Stable tag: 1.4.0
3. TESTS.md, environment check: Plugin version: 1.4.0
```

### i18n Check
If any translatable strings were added or changed:
```bash
wp i18n make-pot . ./i18n/languages/comet-ai-says.pot
```
This regenerates the POT file. Commit the updated POT before release.

### Code Quality
- No `var_dump()`, `console.log()`, or debug output left uncommented
- No hardcoded API keys (keys retrieved via `get_option()` only)
- All file paths use `plugin_dir_path()` and `plugin_dir_url()`

### Documentation
- Changelog entry exists in readme.txt under `== Changelog ==`
- All features/fixes in the new version are documented
- [INTERNAL.md](../INTERNAL.md) is cleaned up (no pending items relevant to this release)

### Manual Testing
Run through [TESTS.md](../TESTS.md) checklist:
- ✅ Core Functionality (admin generation, bulk actions, edit product)
- ✅ Settings (changes persist, reset works)
- ✅ Frontend (display method works, shortcode/widget)
- ✅ i18n (translations load)
- ✅ Edge Cases (empty input, max length, special chars)
- ✅ Integration (works with caching, compatible plugins)

## Release Workflow

### 1. Prepare Local Repository
```bash
# Backup as ZIP (respects .gitignore and .distignore)
git archive --format=zip --output=comet-ai-says-v1.4.0.zip HEAD

# Ensure working directory is clean
git status
```

### 2. Update Version Numbers
Update in this order:
1. **comet-ai-says.php** (line 5): `* Version: 1.4.0`
2. **readme.txt** (line 5): `Stable tag: 1.4.0`
3. **readme.txt** (line 38+): Add entry under `== Changelog ==`
4. **TESTS.md** (environment section): Update version number

### 3. Verify i18n (if strings changed)
```bash
wp i18n make-pot . ./i18n/languages/comet-ai-says.pot
```
Commit the updated POT file.

### 4. SVN Workflow (TortoiseSVN on Windows)
1. **Update trunk** → right-click plugin folder → TortoiseSVN → Update
2. **Copy updated files** from local repo to SVN working copy
3. **Commit to trunk** → Select all files → right-click → TortoiseSVN → Commit
   - Commit message: `Release v1.4.0: [Brief description]`
4. **Create tag** → right-click → TortoiseSVN → Branch/Tag
   - From: `https://plugins.svn.wordpress.org/comet-ai-says/trunk`
   - To: `https://plugins.svn.wordpress.org/comet-ai-says/tags/1.4.0`
   - Log message: `Tagging version 1.4.0`
5. **Final update** → TortoiseSVN → Update

### 5. Verify Deployment
- Check [WordPress.org plugin page](https://wordpress.org/plugins/comet-ai-says/) for new version
- Wait 5-15 minutes for update to propagate
- Verify changelog is visible
- Test automatic update in a WordPress installation

## Common Issues & Solutions

### Issue: Version mismatch between header and readme.txt
**Solution:** Search both files for the old version number. Update all occurrences.

### Issue: POT file is stale
**Solution:** Run `wp i18n make-pot . ./i18n/languages/comet-ai-says.pot` and commit.

### Issue: Forgot to update changelog
**Solution:** Edit readme.txt, add new version entry under `== Changelog ==`, commit to trunk, re-tag.

### Issue: SVN tag already exists
**Solution:** Delete the tag, create a new one with correct content, or increment version number.

## Release Checklist for AI Agents

When assisting with or reviewing a release, verify:

- [ ] All version numbers match (plugin header, readme.txt, TESTS.md)
- [ ] Changelog entry added to readme.txt
- [ ] i18n POT file regenerated (if strings added)
- [ ] No debug code left in [comet-ai-says.php](../../comet-ai-says.php) and [assets/admin-shared.js](../../assets/admin-shared.js)
- [ ] No hardcoded secrets or API keys
- [ ] .distignore configured correctly (dev files excluded)
- [ ] Manual QA checklist from [TESTS.md](../TESTS.md) is reviewed
- [ ] [INTERNAL.md](../INTERNAL.md) cleaned up (pending items removed or documented)
- [ ] Git backup ZIP created before SVN changes
- [ ] SVN workflow followed (trunk → tag, not direct tag)

## Links & References

- [WordPress Plugin Handbook: Releasing](https://developer.wordpress.org/plugins/deployment/releasing-your-plugin/)
- [SVN Basics for WordPress](https://developer.wordpress.org/plugins/deployment/deploying-to-wordpress-dot-org/)
- [i18n Documentation](https://developer.wordpress.org/cli/commands/i18n/)
- [TESTS.md](../TESTS.md) — Manual QA checklist
- [AGENTS.md](./agents/AGENTS.md) — Developer guidance

---

**Last Updated:** 2026-06-19 | **Current Version:** 1.3.0
