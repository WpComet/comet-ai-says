const fs = require('fs');
const path = require('path');

const rootDir = path.resolve(__dirname, '..');
const pkgPath = path.join(rootDir, 'package.json');
const pkg = JSON.parse(fs.readFileSync(pkgPath, 'utf8'));
const pluginSlug = pkg.name || 'comet-ai-says';

// Target directory in isolated test environment (WPlatest)
const cleanTestPluginsDir = process.env.COMET_TEST_PLUGINS_DIR || path.resolve('D:/wamp64/www/WPlatest/wp-content/plugins');
const targetPluginDir = path.join(cleanTestPluginsDir, pluginSlug);

// Assemble comprehensive exclusion list (matching .distignore and .gitattributes)
const defaultExcludes = [
    '.git', '.github', '.gitignore', '.gitattributes', '.distignore',
    '.vscode', '.idea', 'node_modules', 'vendor',
    'composer.json', 'composer.lock', 'package.json', 'package-lock.json',
    'dev', 'dev_llm', 'dist', 'tests', 'phpunit.xml.dist',
    '.phpunit.result.cache', '.phpunit.cache', 'AGENTS.md', '.agents',
    'agent_memory.md', 'TODO.md', 'todo.php', 'assets/screenshots'
];

const distignorePath = path.join(rootDir, '.distignore');
if (fs.existsSync(distignorePath)) {
    const lines = fs.readFileSync(distignorePath, 'utf8').split(/\r?\n/);
    lines.forEach(line => {
        line = line.trim();
        if (line && !line.startsWith('#')) {
            defaultExcludes.push(line.replace(/^\/+|\/+$/g, ''));
        }
    });
}

const excludeSet = new Set(defaultExcludes);

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

function syncToWPlatest() {
    console.log(`🔄 Syncing working copy of ${pluginSlug} to test environment...`);
    console.log(`   Source: ${rootDir}`);
    console.log(`   Destination: ${targetPluginDir}`);

    if (!fs.existsSync(cleanTestPluginsDir)) {
        console.warn(`⚠️  Test environment directory not found at ${cleanTestPluginsDir} (skipped sync).`);
        return false;
    }

    try {
        if (fs.existsSync(targetPluginDir)) {
            fs.rmSync(targetPluginDir, { recursive: true, force: true });
        }
        fs.mkdirSync(targetPluginDir, { recursive: true });

        fs.cpSync(rootDir, targetPluginDir, { recursive: true, filter: shouldInclude });

        // Count synced files
        let count = 0;
        function countFiles(dir) {
            const entries = fs.readdirSync(dir, { withFileTypes: true });
            for (const entry of entries) {
                if (entry.isDirectory()) {
                    countFiles(path.join(dir, entry.name));
                } else if (entry.isFile()) {
                    count++;
                }
            }
        }
        countFiles(targetPluginDir);

        console.log(`✅ Working copy cleanly synced to WPlatest (${count} runtime files)!`);
        console.log(`   PCP ready at: ${targetPluginDir}`);
        console.log(`   (No zip archive created, no version changed, no git commits made)\n`);
        return true;
    } catch (err) {
        console.error(`❌ Sync failed:`, err.message);
        return false;
    }
}

if (require.main === module) {
    const success = syncToWPlatest();
    process.exit(success ? 0 : 1);
}

module.exports = { syncToWPlatest, shouldInclude };
