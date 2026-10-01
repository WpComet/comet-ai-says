const { execSync } = require('child_process');
const fs = require('fs');
const path = require('path');
const os = require('os');

const rootDir = path.resolve(__dirname, '..');
const pkgPath = path.join(rootDir, 'package.json');
const pkg = JSON.parse(fs.readFileSync(pkgPath, 'utf8'));
const version = pkg.version;
const pluginSlug = pkg.name || 'comet-ai-says';

const args = process.argv.slice(2);
const skipZip = args.includes('--no-zip') || args.includes('--clean-only');

// Determine output directory for production zip
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

console.log(`📦 Preparing clean distribution for ${pluginSlug} v${version}...`);

// Assemble comprehensive exclusion list (matching .distignore and .gitattributes)
const excludeList = [
    '.git', '.github', '.gitignore', '.gitattributes', '.distignore',
    '.vscode', '.idea', 'node_modules', 'vendor',
    'composer.json', 'composer.lock', 'package.json', 'package-lock.json',
    'dev', 'dev_llm', 'dist', 'tests', 'phpunit.xml.dist',
    '.phpunit.result.cache', '.phpunit.cache', 'AGENTS.md', '.agents',
    'todo.php'
];

if (fs.existsSync(path.join(rootDir, '.distignore'))) {
    const lines = fs.readFileSync(path.join(rootDir, '.distignore'), 'utf8').split(/\r?\n/);
    lines.forEach(line => {
        line = line.trim();
        if (line && !line.startsWith('#')) {
            excludeList.push(line.replace(/^\/+|\/+$/g, ''));
        }
    });
}

const excludeSet = new Set(excludeList);

function shouldInclude(srcPath) {
    const rel = path.relative(rootDir, srcPath).replace(/\\/g, '/');
    if (!rel) return true; // root directory itself
    const topPart = rel.split('/')[0];
    const baseName = path.basename(srcPath);

    if (excludeSet.has(topPart) || excludeSet.has(baseName) || excludeSet.has(rel)) {
        return false;
    }
    if (baseName.startsWith('.temp') || baseName.endsWith('.cache')) {
        return false;
    }
    return true;
}

try {
    // 1. Stage clean plugin into an isolated temporary directory
    const stagingRoot = fs.mkdtempSync(path.join(os.tmpdir(), 'comet-pkg-'));
    const stagingPluginDir = path.join(stagingRoot, pluginSlug);
    fs.mkdirSync(stagingPluginDir, { recursive: true });

    fs.cpSync(rootDir, stagingPluginDir, { recursive: true, filter: shouldInclude });

    // 2. Build release zip if not skipped
    if (!skipZip) {
        if (fs.existsSync(outputPath)) {
            try { fs.unlinkSync(outputPath); } catch (e) {}
        }

        let zipSuccess = false;
        try {
            execSync(`tar -a -cf "${outputPath}" -C "${stagingRoot}" "${pluginSlug}"`, { stdio: 'pipe' });
            zipSuccess = true;
        } catch (tarErr) {
            // Fallback to PowerShell Compress-Archive
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

    // 3. Deploy clean pre-zip folder copy to isolated test environment (WPlatest) for PCP checks
    const cleanTestPluginsDir = process.env.COMET_TEST_PLUGINS_DIR || path.resolve('D:/wamp64/www/WPlatest/wp-content/plugins');
    if (fs.existsSync(cleanTestPluginsDir)) {
        const targetPluginDir = path.join(cleanTestPluginsDir, pluginSlug);
        console.log(`🔄 Deploying clean release copy to test environment: ${targetPluginDir}`);

        if (fs.existsSync(targetPluginDir)) {
            fs.rmSync(targetPluginDir, { recursive: true, force: true });
        }
        fs.mkdirSync(targetPluginDir, { recursive: true });

        fs.cpSync(stagingPluginDir, targetPluginDir, { recursive: true });
        console.log(`✅ Clean release copy successfully deployed to WPlatest for PCP verification!`);
        console.log(`   Clean directory: ${targetPluginDir}\n`);
    } else {
        console.log(`ℹ️  Test environment directory not found at ${cleanTestPluginsDir} (skipped deployment).`);
    }

    // Clean up temporary staging
    try {
        fs.rmSync(stagingRoot, { recursive: true, force: true });
    } catch (e) {}
} catch (err) {
    console.error(`❌ Packaging/Deployment failed:`, err.message);
    process.exit(1);
}

