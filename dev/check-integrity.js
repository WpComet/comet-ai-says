#!/usr/bin/env node
/**
 * Automated Code Integrity, Syntax & Test Checker for Comet AI Says
 *
 * Validates:
 * 1. PHP syntax across all plugin PHP files via php -l.
 * 2. JS syntax across assets and dev scripts via node -c.
 * 3. Unit test suite passes via composer test.
 *
 * @package Comet_AISays
 */

const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');

const ROOT_DIR = path.resolve(__dirname, '..');
const INCLUDES_DIR = path.join(ROOT_DIR, 'includes');
const ASSETS_DIR = path.join(ROOT_DIR, 'assets');
const TESTS_DIR = path.join(ROOT_DIR, 'tests');

let errors = [];
let checkedPhpCount = 0;
let checkedJsCount = 0;

console.log('\x1b[1m\x1b[36m=== Comet AI Says: Integrity & Test Audit ===\x1b[0m\n');

function getFiles(dir, ext, fileList = []) {
    if (!fs.existsSync(dir)) return fileList;
    const items = fs.readdirSync(dir, { withFileTypes: true });
    for (const item of items) {
        const fullPath = path.join(dir, item.name);
        if (item.isDirectory()) {
            if (item.name === 'node_modules' || item.name === 'vendor' || item.name === '.git' || item.name === 'dev_llm') {
                continue;
            }
            getFiles(fullPath, ext, fileList);
        } else if (item.isFile() && item.name.endsWith(ext)) {
            fileList.push(fullPath);
        }
    }
    return fileList;
}

const phpFiles = [
    path.join(ROOT_DIR, 'comet-ai-says.php'),
    ...getFiles(INCLUDES_DIR, '.php'),
    ...getFiles(TESTS_DIR, '.php')
].filter(f => fs.existsSync(f));

const jsFiles = [
    ...getFiles(ASSETS_DIR, '.js'),
    ...getFiles(path.join(ROOT_DIR, 'dev'), '.js')
].filter(f => fs.existsSync(f));

// 1. Syntax Validation: PHP (php -l)
process.stdout.write('Checking PHP syntax (php -l)... ');
for (const file of phpFiles) {
    try {
        execSync(`php -l "${file}"`, { stdio: 'pipe' });
        checkedPhpCount++;
    } catch (err) {
        errors.push({
            file: path.relative(ROOT_DIR, file),
            line: 'Syntax',
            message: `PHP syntax error: ${err.stderr ? err.stderr.toString().trim() : err.message}`
        });
    }
}
console.log(`\x1b[32mOK\x1b[0m (${checkedPhpCount} PHP files verified)`);

// 2. Syntax Validation: JavaScript (node -c)
process.stdout.write('Checking JS syntax (node -c)... ');
for (const file of jsFiles) {
    try {
        execSync(`node -c "${file}"`, { stdio: 'pipe' });
        checkedJsCount++;
    } catch (err) {
        errors.push({
            file: path.relative(ROOT_DIR, file),
            line: 'Syntax',
            message: `JS syntax error: ${err.stderr ? err.stderr.toString().trim() : err.message}`
        });
    }
}
console.log(`\x1b[32mOK\x1b[0m (${checkedJsCount} JS files verified)`);

// 3. Automated Unit Tests (composer test)
process.stdout.write('Running automated PHPUnit unit test suite (composer test)... ');
try {
    const testOutput = execSync('composer test', { cwd: ROOT_DIR, encoding: 'utf8', stdio: 'pipe' });
    const match = testOutput.match(/OK \((\d+ tests, \d+ assertions)\)/);
    const countStr = match ? match[1] : 'Passed';
    console.log(`\x1b[32mOK\x1b[0m (${countStr})`);
} catch (err) {
    errors.push({
        file: 'tests/',
        line: 'Unit Tests',
        message: `PHPUnit failed:\n${err.stdout ? err.stdout.toString() : ''}\n${err.stderr ? err.stderr.toString() : ''}`
    });
    console.log(`\x1b[31mFAILED\x1b[0m`);
}

// 4. Header & Version Alignment
process.stdout.write('Checking version & compatibility header alignment... ');
const readmePath = path.join(ROOT_DIR, 'readme.txt');
const pkgPath = path.join(ROOT_DIR, 'package.json');
const mainPhpPath = path.join(ROOT_DIR, 'comet-ai-says.php');

if (fs.existsSync(readmePath) && fs.existsSync(mainPhpPath)) {
    const readmeContent = fs.readFileSync(readmePath, 'utf8');
    const mainPhpContent = fs.readFileSync(mainPhpPath, 'utf8');
    const pkg = fs.existsSync(pkgPath) ? JSON.parse(fs.readFileSync(pkgPath, 'utf8')) : null;

    const phpVerMatch = mainPhpContent.match(/\*\s*Version:\s+([^\r\n]+)/);
    const readmeStableMatch = readmeContent.match(/Stable tag:\s+([^\r\n]+)/i);
    const pkgVer = pkg ? pkg.version : null;

    const phpVer = phpVerMatch ? phpVerMatch[1].trim() : null;
    const readmeVer = readmeStableMatch ? readmeStableMatch[1].trim() : null;

    if (phpVer && readmeVer && phpVer !== readmeVer) {
        errors.push({
            file: 'readme.txt',
            line: 'Header',
            message: `Version mismatch: comet-ai-says.php is "${phpVer}" while readme.txt Stable tag is "${readmeVer}".`
        });
    }

    if (pkgVer && phpVer && pkgVer !== phpVer) {
        errors.push({
            file: 'package.json',
            line: 'Header',
            message: `Version mismatch: package.json is "${pkgVer}" while comet-ai-says.php is "${phpVer}".`
        });
    }

    // WC tested up to synchronization (if present in both)
    const phpWcTested = mainPhpContent.match(/\*\s*WC tested up to:\s+([^\r\n]+)/);
    const readmeWcTested = readmeContent.match(/WC tested up to:\s+([^\r\n]+)/i);

    const phpWcVal = phpWcTested ? phpWcTested[1].trim() : null;
    const readmeWcVal = readmeWcTested ? readmeWcTested[1].trim() : null;

    if (phpWcVal && readmeWcVal && phpWcVal !== readmeWcVal) {
        errors.push({
            file: 'readme.txt',
            line: 'Header',
            message: `WC tested up to mismatch: comet-ai-says.php is "${phpWcVal}" while readme.txt is "${readmeWcVal}".`
        });
    }

    // WP Tested up to presence
    const readmeWpTested = readmeContent.match(/Tested up to:\s+([^\r\n]+)/i);
    const readmeWpVal = readmeWpTested ? readmeWpTested[1].trim() : null;
    if (!readmeWpVal) {
        errors.push({
            file: 'readme.txt',
            line: 'Header',
            message: 'Missing "Tested up to:" header in readme.txt.'
        });
    }

    // Live Environment Probing against local testbed (alldemos)
    try {
        const alldemosDir = 'd:/wamp64/www/alldemos';
        if (fs.existsSync(alldemosDir)) {
            const liveWpVer = execSync(`wp core version --path="${alldemosDir}"`, { stdio: 'pipe' }).toString().trim();
            let liveWcVer = null;
            try {
                liveWcVer = execSync(`wp plugin get woocommerce --field=version --path="${alldemosDir}"`, { stdio: 'pipe' }).toString().trim();
            } catch (e) {}

            if (liveWpVer && readmeWpVal) {
                const liveWpMajorMinor = parseFloat(liveWpVer.split('.').slice(0, 2).join('.'));
                const headerWpMajorMinor = parseFloat(readmeWpVal.split('.').slice(0, 2).join('.'));
                if (headerWpMajorMinor < liveWpMajorMinor) {
                    console.warn(`\n  ⚠️  Notice: readme.txt "Tested up to: ${readmeWpVal}" is lower than testbed WordPress ${liveWpVer}`);
                }
            }

            const activeWcVal = phpWcVal || readmeWcVal;
            if (liveWcVer && activeWcVal) {
                const liveWcMajorMinor = parseFloat(liveWcVer.split('.').slice(0, 2).join('.'));
                const headerWcMajorMinor = parseFloat(activeWcVal.split('.').slice(0, 2).join('.'));
                if (headerWcMajorMinor < liveWcMajorMinor) {
                    console.warn(`\n  ⚠️  Notice: "WC tested up to: ${activeWcVal}" is lower than testbed WooCommerce ${liveWcVer}`);
                }
            }
        }
    } catch (e) {
        // Non-blocking query
    }
}
console.log(`\x1b[32mOK\x1b[0m\n`);

// 5. Report
console.log('\n--------------------------------------------------');
if (errors.length > 0) {
    console.error(`\x1b[31m✖ Integrity check found ${errors.length} issue(s):\x1b[0m\n`);
    for (const e of errors) {
        console.error(`  - [${e.file}:${e.line}] ${e.message}`);
    }
    console.log('--------------------------------------------------\n');
    process.exit(1);
} else {
    console.log('\x1b[32m✔ All syntax and unit tests passed cleanly. System is release-ready.\x1b[0m');
    console.log('--------------------------------------------------\n');
    process.exit(0);
}
