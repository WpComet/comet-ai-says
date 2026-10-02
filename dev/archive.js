const { execSync } = require('child_process');
const fs = require('fs');
const path = require('path');
const os = require('os');
const { syncToWPlatest, shouldInclude } = require('./sync');

const rootDir = path.resolve(__dirname, '..');
const pkgPath = path.join(rootDir, 'package.json');
const pkg = JSON.parse(fs.readFileSync(pkgPath, 'utf8'));
const version = pkg.version;
const pluginSlug = pkg.name || 'comet-ai-says';

const args = process.argv.slice(2);
const skipZip = args.includes('--no-zip') || args.includes('--clean-only');
const skipTest = args.includes('--no-test') || args.includes('--skip-test');
const skipPot = args.includes('--no-pot') || args.includes('--skip-pot');

console.log(`\x1b[1m\x1b[36m=== Comet Archive & Production Distribution ===\x1b[0m\n`);

// 1. Pre-flight Code Integrity & PHPUnit Test Audit
if (!skipTest) {
    console.log('🛡️  Running automated code integrity audit and PHPUnit unit tests...');
    try {
        execSync('node dev/check-integrity.js', { cwd: rootDir, stdio: 'inherit' });
    } catch (err) {
        console.error('\n❌ Archive halted: Code integrity or unit test check failed.');
        process.exit(1);
    }
} else {
    console.log('⏭️  Skipping test audit (--no-test specified).\n');
}

// 2. Synchronize .distignore and .gitattributes from .gitignore
console.log('🔄 Synchronizing .distignore and .gitattributes...');
try {
    const syncIgnore = require('./sync-ignore');
    if (typeof syncIgnore === 'function') syncIgnore();
} catch (e) {
    console.warn(`⚠️  Could not sync ignore files: ${e.message}`);
}

// 3. Regenerate Translation POT Catalog
if (!skipPot) {
    console.log('\n🌐 Regenerating translation POT catalog (makepot)...');
    try {
        execSync('npm run makepot', { cwd: rootDir, stdio: 'inherit' });
    } catch (e) {
        console.warn(`⚠️  Translation catalog update warning: ${e.message}`);
    }
} else {
    console.log('\n⏭️  Skipping translation catalog (--no-pot specified).');
}

// 4. Determine output directory for production zip
let outputDir = process.env.COMET_RELEASE_DIR;
if (!outputDir) {
    const defaultPublicOs = path.resolve(__dirname, '../../../../../public-os');
    if (fs.existsSync(defaultPublicOs) || fs.existsSync(path.dirname(defaultPublicOs))) {
        outputDir = defaultPublicOs;
    } else {
        outputDir = path.resolve(rootDir, 'dist');
    }
}

if (!fs.existsSync(outputDir)) {
    fs.mkdirSync(outputDir, { recursive: true });
}

const outputPath = path.join(outputDir, `${pluginSlug}-v${version}.zip`);

console.log(`\n📦 Packaging clean distribution for ${pluginSlug} v${version}...`);

try {
    // 5. Stage clean plugin into an isolated temporary directory
    const stagingRoot = fs.mkdtempSync(path.join(os.tmpdir(), 'comet-pkg-'));
    const stagingPluginDir = path.join(stagingRoot, pluginSlug);
    fs.mkdirSync(stagingPluginDir, { recursive: true });

    fs.cpSync(rootDir, stagingPluginDir, { recursive: true, filter: shouldInclude });

    // 6. Build release zip if not skipped
    if (!skipZip) {
        if (fs.existsSync(outputPath)) {
            try { fs.unlinkSync(outputPath); } catch (e) {}
        }

        let zipSuccess = false;
        try {
            execSync(`tar -a -cf "${outputPath}" -C "${stagingRoot}" "${pluginSlug}"`, { stdio: 'pipe' });
            zipSuccess = true;
        } catch (tarErr) {
            try {
                const psCmd = `Compress-Archive -Path "${stagingPluginDir}" -DestinationPath "${outputPath}" -Force`;
                execSync(`powershell -NoProfile -Command "${psCmd}"`, { stdio: 'inherit' });
                zipSuccess = true;
            } catch (psErr) {
                console.error(`❌ Compression failed: ${psErr.message}`);
            }
        }

        if (zipSuccess && fs.existsSync(outputPath)) {
            const stats = fs.statSync(outputPath);
            const sizeKb = (stats.size / 1024).toFixed(1);
            console.log(`✅ Production archive created successfully!`);
            console.log(`   Location: ${outputPath} (${sizeKb} KB)\n`);
        }
    }

    // 7. Deploy clean copy to WPlatest for PCP verification
    syncToWPlatest();

    // 8. Clean up temporary staging
    try {
        fs.rmSync(stagingRoot, { recursive: true, force: true });
    } catch (e) {}
} catch (err) {
    console.error(`❌ Packaging/Deployment failed:`, err.message);
    process.exit(1);
}
