const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');

const rootDir = path.resolve(__dirname, '..');
const pkgPath = path.join(rootDir, 'package.json');
if (!fs.existsSync(pkgPath)) {
    console.error('❌ Error: package.json not found!');
    process.exit(1);
}

const args = process.argv.slice(2);
const isSyncOnly = args.includes('--sync-only');
let bumpType = args.map(a => a.replace(/^-+/, '').toLowerCase()).find(a => ['major', 'minor', 'patch'].includes(a));

if (!isSyncOnly && !bumpType && args.length === 0) {
    bumpType = 'patch'; // Default to patch if no arguments provided
}

if (bumpType) {
    console.log(`⬆️  Bumping npm version (${bumpType})...`);
    execSync(`npm version ${bumpType} --no-git-tag-version`, { cwd: rootDir, stdio: 'inherit' });
}

const pkg = JSON.parse(fs.readFileSync(pkgPath, 'utf8'));
const version = pkg.version;

if (!version) {
    console.error('❌ Error: No version field found in package.json!');
    process.exit(1);
}

const mainFileName = pkg.main || 'comet-ai-says.php';
const mainFile = path.join(rootDir, mainFileName);
if (!fs.existsSync(mainFile)) {
    console.error(`❌ Error: Main file not found at ${mainFile}`);
    process.exit(1);
}

let content = fs.readFileSync(mainFile, 'utf8');

const headerRegex = /(\*\s*Version:\s+)(.+)/;
const constRegex = /(define\s*\(\s*['"]COMET_AISAYS_VERSION['"]\s*,\s*['"])(.+?)(['"]\s*\)\s*;)/i;

if (!headerRegex.test(content)) {
    console.error(`❌ Error: Could not locate "* Version:" header line in ${mainFileName}!`);
    process.exit(1);
}

if (!constRegex.test(content)) {
    console.error(`❌ Error: Could not locate COMET_AISAYS_VERSION define constant in ${mainFileName}!`);
    process.exit(1);
}

// Perform safe replacements
content = content.replace(headerRegex, `$1${version}`);
content = content.replace(constRegex, `$1${version}$3`);

fs.writeFileSync(mainFile, content, 'utf8');
console.log(`✅ Successfully synced ${mainFileName} to version ${version} from package.json!`);

// Sync Stable tag in README.md / readme.txt
const readmeFiles = ['README.md', 'readme.txt'];
for (const rFile of readmeFiles) {
    const rPath = path.join(rootDir, rFile);
    if (fs.existsSync(rPath)) {
        let rContent = fs.readFileSync(rPath, 'utf8');
        const stableTagRegex = /(Stable tag:\s+)(.+)/i;
        if (stableTagRegex.test(rContent)) {
            rContent = rContent.replace(stableTagRegex, `$1${version}`);
            fs.writeFileSync(rPath, rContent, 'utf8');
            console.log(`✅ Successfully synced ${rFile} "Stable tag:" to version ${version}!`);
        }
    }
}
