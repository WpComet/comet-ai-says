const { execSync } = require('child_process');
const fs = require('fs');
const path = require('path');

const rootDir = path.resolve(__dirname, '..');
const pkgPath = path.join(rootDir, 'package.json');
const pkg = JSON.parse(fs.readFileSync(pkgPath, 'utf8'));
const version = pkg.version;

// Determine output directory
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

const pluginSlug = pkg.name || 'comet-ai-says';
const outputPath = path.join(outputDir, `${pluginSlug}-v${version}.zip`);

console.log(`📦 Packaging production release zip for ${pluginSlug} v${version}...`);

try {
    // If git repository exists with commits, git archive is pristine
    let hasCommits = false;
    try {
        execSync('git rev-parse HEAD', { cwd: rootDir, stdio: 'ignore' });
        hasCommits = true;
    } catch (e) {}

    if (hasCommits) {
        execSync(`git archive --format=zip --prefix=${pluginSlug}/ -o "${outputPath}" HEAD`, {
            cwd: rootDir,
            stdio: 'inherit'
        });
    } else {
        // Fallback using PowerShell Compress-Archive excluding dev artifacts
        const psScript = `
            $tempDir = Join-Path $env:TEMP 'comet-ai-says-pkg';
            if (Test-Path $tempDir) { Remove-Item -Recurse -Force $tempDir }
            New-Item -ItemType Directory -Path (Join-Path $tempDir '${pluginSlug}') -Force | Out-Null
            Copy-Item -Path '${rootDir}/*' -Destination (Join-Path $tempDir '${pluginSlug}') -Recurse -Exclude '.git','.github','tests','vendor','node_modules','dev','dev_llm','.phpunit.result.cache'
            Compress-Archive -Path (Join-Path $tempDir '${pluginSlug}') -DestinationPath '${outputPath}' -Force
            Remove-Item -Recurse -Force $tempDir
        `;
        execSync(`powershell -NoProfile -Command "${psScript.replace(/\n/g, ' ')}"`, { stdio: 'inherit' });
    }

    const stats = fs.statSync(outputPath);
    const sizeKb = (stats.size / 1024).toFixed(1);
    console.log(`✅ Production archive created successfully!`);
    console.log(`   Location: ${outputPath} (${sizeKb} KB)\n`);
} catch (err) {
    console.error(`❌ Packaging failed:`, err.message);
    process.exit(1);
}
