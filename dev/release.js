const { execSync } = require('child_process');
const path = require('path');
const fs = require('fs');
const readline = require('readline');

const rootDir = path.resolve(__dirname, '..');

// Parse CLI arguments
const args = process.argv.slice(2);
let type = 'patch';
let customMsg = null;
let noPush = args.includes('--no-push');
const isDryRun = args.includes('--dry-run');

for (const arg of args) {
    const clean = arg.replace(/^-+/, '').toLowerCase();
    if (clean === 'major' || clean === 'minor' || clean === 'patch') {
        type = clean;
    } else if (arg.startsWith('--msg=') || arg.startsWith('--message=')) {
        customMsg = arg.split('=')[1].replace(/^["']|["']$/g, '');
    } else if (arg.startsWith('-m=')) {
        customMsg = arg.split('=')[1].replace(/^["']|["']$/g, '');
    } else if (!arg.startsWith('-') && !['major', 'minor', 'patch'].includes(clean)) {
        customMsg = arg;
    }
}

function promptUser(question) {
    return new Promise(resolve => {
        if (!process.stdin.isTTY) {
            return resolve('');
        }
        const rl = readline.createInterface({
            input: process.stdin,
            output: process.stdout,
        });
        rl.question(question, answer => {
            rl.close();
            resolve(answer.trim());
        });
    });
}

function getWorkingTreeStatus() {
    try {
        return execSync('git status --porcelain', { cwd: rootDir, encoding: 'utf8' }).trim();
    } catch (e) {
        return '';
    }
}

function generateSmartCommitMessage(newVersion, statusOutput) {
    const categories = {
        'AI Generation & Prompts': /^(includes\/AIGenerator|includes\/Config)/i,
        'Admin Interface & Dashboard': /^(includes\/AdminInterface|assets\/admin)/i,
        'Live Diagnostics & Tests': /^(includes\/LiveTests|dev_llm|tests)/i,
        'Catalog Table': /^includes\/ProductsTable/i,
        'Frontend & SEO': /^includes\/FrontendDisplay/i,
        'Build & Release Tooling': /^(dev\/|package\.json|composer\.json)/i,
    };

    const detectedAreas = new Set();
    if (statusOutput) {
        statusOutput.split('\n').forEach(line => {
            const filePath = line.replace(/^[?!\sMADRCU]{1,3}\s+/, '').replace(/\\/g, '/').trim();
            for (const [area, regex] of Object.entries(categories)) {
                if (regex.test(filePath)) {
                    detectedAreas.add(area);
                    break;
                }
            }
        });
    }

    const areasArray = Array.from(detectedAreas);
    let title = '';

    if (areasArray.length > 0) {
        const areaStr = areasArray.slice(0, 3).join(', ') + (areasArray.length > 3 ? ' & more' : '');
        title = `feat: update ${areaStr} (v${newVersion})`;
    } else {
        title = `chore(release): v${newVersion}`;
    }

    const bodyLines = [];
    if (areasArray.length > 0) {
        bodyLines.push('Modified components:');
        areasArray.forEach(area => bodyLines.push(`- ${area}`));
    }

    return bodyLines.length > 0 ? `${title}\n\n${bodyLines.join('\n')}` : title;
}

async function runRelease() {
    let currentBranch = 'main';
    try {
        currentBranch = execSync('git branch --show-current', { cwd: rootDir, encoding: 'utf8' }).trim() || 'main';
    } catch (e) {}

    let hasRemote = false;
    try {
        execSync('git remote get-url origin', { cwd: rootDir, stdio: 'ignore' });
        hasRemote = true;
    } catch (e) {}

    const preStatus = getWorkingTreeStatus();
    const hadUncommittedChanges = preStatus.length > 0;

    const currentPkg = JSON.parse(fs.readFileSync(path.join(rootDir, 'package.json'), 'utf8'));
    const parts = currentPkg.version.split('.').map(Number);
    let newVersion = '';
    if (type === 'major') newVersion = `${parts[0] + 1}.0.0`;
    else if (type === 'minor') newVersion = `${parts[0]}.${parts[1] + 1}.0`;
    else newVersion = `${parts[0]}.${parts[1]}.${parts[2] + 1}`;

    let finalCommitMsg = customMsg;
    if (!finalCommitMsg) {
        if (!hadUncommittedChanges) {
            finalCommitMsg = `chore(release): v${newVersion}`;
        } else {
            if (process.stdin.isTTY && !isDryRun) {
                console.log(`ℹ️  You have uncommitted changes in your working tree.`);
                const inputMsg = await promptUser(`💬 Enter commit message [Enter for auto-summary]: `);
                if (inputMsg) {
                    finalCommitMsg = inputMsg.includes(newVersion) ? inputMsg : `${inputMsg} (v${newVersion})`;
                }
            }
            if (!finalCommitMsg) {
                finalCommitMsg = generateSmartCommitMessage(newVersion, preStatus);
            }
        }
    } else {
        if (!finalCommitMsg.includes(newVersion) && !finalCommitMsg.startsWith('chore(release)')) {
            finalCommitMsg = `${finalCommitMsg} (v${newVersion})`;
        }
    }

    if (isDryRun) {
        console.log(`🔍 [DRY RUN] Simulating release [target: ${type}] on branch '${currentBranch}'...\n`);
        console.log(`Version bump: ${currentPkg.version} -> ${newVersion}`);
        console.log(`Had uncommitted changes: ${hadUncommittedChanges ? 'Yes' : 'No'}`);
        console.log(`Remote configured: ${hasRemote ? 'origin' : 'None'}`);
        console.log(`Will push to remote: ${hasRemote && !noPush ? 'Yes' : 'No'}`);
        console.log('\nGenerated Commit Message:');
        console.log('--------------------------------------------------');
        console.log(finalCommitMsg);
        console.log('--------------------------------------------------');

        // Execute integrity check even in dry run
        console.log('\n🛡️  Running dry-run integrity and test audit...');
        execSync('node dev/check-integrity.js', { cwd: rootDir, stdio: 'inherit' });

        // Deploy clean preview copy to WPlatest for PCP verification
        console.log('\n🔄 Syncing preview copy of what would be a release to WPlatest for PCP verification...');
        try {
            execSync('node dev/sync.js', { cwd: rootDir, stdio: 'inherit' });
        } catch (e) {
            console.warn('⚠️  Could not deploy preview copy to WPlatest:', e.message);
        }

        console.log('\n✅ Dry run complete. No files were committed or pushed.');
        return;
    }

    console.log(`🚀 Starting automated release [target: ${type}] on '${currentBranch}'...\n`);

    // 0. Pre-flight integrity & PHPUnit test audit
    console.log(`🛡️  Running automated code integrity and PHPUnit unit tests...`);
    try {
        execSync('node dev/check-integrity.js', { cwd: rootDir, stdio: 'inherit' });
    } catch (err) {
        console.error('\n❌ Release halted: Code integrity or unit test check failed.');
        process.exit(1);
    }

    // 1. Bump version in package.json
    console.log(`1️⃣  Bumping npm version (${type})...`);
    execSync(`npm version ${type} --no-git-tag-version`, { cwd: rootDir, stdio: 'inherit' });

    const updatedPkg = JSON.parse(fs.readFileSync(path.join(rootDir, 'package.json'), 'utf8'));
    newVersion = updatedPkg.version;
    const mainFileName = updatedPkg.main || 'comet-ai-says.php';

    // 2. Sync to main PHP file and readme.txt
    console.log(`\n2️⃣  Syncing version ${newVersion} to ${mainFileName} and readme.txt...`);
    execSync('node dev/bump-version.js --sync-only', { 
        cwd: rootDir, 
        stdio: 'inherit',
        env: { ...process.env, COMET_IN_RELEASE: '1' }
    });

    // 3. Commit release
    console.log(`\n3️⃣  Committing release v${newVersion}:`);
    console.log('--------------------------------------------------');
    console.log(finalCommitMsg);
    console.log('--------------------------------------------------');

    try {
        execSync(`git add -A`, { cwd: rootDir, stdio: 'inherit' });

        const tempMsgFile = path.join(rootDir, 'dev/.temp_commit_msg');
        fs.writeFileSync(tempMsgFile, finalCommitMsg, 'utf8');
        execSync(`git commit -F "${tempMsgFile}"`, { cwd: rootDir, stdio: 'inherit' });
        if (fs.existsSync(tempMsgFile)) fs.unlinkSync(tempMsgFile);

        // 4. Create annotated Git tag
        const tagName = `v${newVersion}`;
        console.log(`\n4️⃣  Tagging release ${tagName}...`);
        try {
            execSync(`git tag -a "${tagName}" -m "Release ${tagName}"`, { cwd: rootDir, stdio: 'inherit' });
            console.log(`🏷️  Git tag ${tagName} created.`);
        } catch (tagErr) {
            console.warn(`⚠️  Note: Tag ${tagName} could not be created or already exists.`);
        }
    } catch (gitErr) {
        console.warn(`⚠️  Git commit skipped:`, gitErr.message);
    }

    // 5. Archive the newly committed version
    console.log(`\n5️⃣  Building clean production zip archive...`);
    execSync('node dev/archive.js', { cwd: rootDir, stdio: 'inherit' });

    // 6. Push to remote origin if configured
    if (hasRemote && !noPush) {
        console.log(`\n6️⃣  Pushing release and tags to origin/${currentBranch}...`);
        try {
            execSync(`git push origin ${currentBranch} --follow-tags`, { cwd: rootDir, stdio: 'inherit' });
            console.log(`✅ Successfully pushed commit and tags to origin!`);
        } catch (pushErr) {
            console.error(`❌ Push failed: ${pushErr.message}`);
        }
    }

    console.log(`\n🎉 Release v${newVersion} successfully completed!`);
}

runRelease().catch(err => {
    console.error('\n❌ Release failed:', err.message);
    process.exit(1);
});
