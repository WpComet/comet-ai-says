#!/usr/bin/env node
const { execSync } = require('child_process');
const fs = require('fs');
const path = require('path');

const rootDir = path.resolve(__dirname, '..');
const potPath = path.join(rootDir, 'i18n/languages/comet-ai-says.pot');

try {
    execSync('wp i18n make-pot . i18n/languages/comet-ai-says.pot --slug="comet-ai-says" --domain="comet-ai-says" --exclude="node_modules,dev,dev_llm,tests,assets"', {
        cwd: rootDir,
        stdio: 'inherit'
    });

    if (fs.existsSync(potPath)) {
        let potContent = fs.readFileSync(potPath, 'utf8');
        // Normalize POT-Creation-Date to static placeholder to avoid phantom git diffs
        potContent = potContent.replace(
            /"POT-Creation-Date: [^\n]+"/,
            '"POT-Creation-Date: YEAR-MO-DA HO:MI+ZONE\\n"'
        );
        fs.writeFileSync(potPath, potContent, 'utf8');
        console.log('✅ Normalized POT-Creation-Date to static placeholder in comet-ai-says.pot.');
    }
} catch (e) {
    console.error('❌ makepot failed:', e.message);
    process.exit(1);
}
