const fs = require('fs');
const path = require('path');

// A basic regular expression set for identifying potential secrets in code.
// Warning: This is a heuristic and can have false positives.
const secretPatterns = {
    'JWT Secret': /jwt[_]?secret\s*[:=]\s*['"][^'"]+['"]/i,
    'Database Password': /db[_]?password\s*[:=]\s*['"][^'"]+['"]/i,
    'API Key': /api[_]?key\s*[:=]\s*['"][a-zA-Z0-9_\-]+['"]/i,
    'Private Key': /-----BEGIN (RSA |DSA |EC )?PRIVATE KEY-----/
};

// Directories and files to exclude from scanning to avoid noise and false positives
const excludePaths = [
    'vendor',
    'node_modules',
    '.git',
    '.env',
    '.env.example',
    '.env.testing',
    '.env.testing.example',
    'storage',
    'playwright-report',
    'test-results',
    'package-lock.json',
    'composer.lock'
];

function scanDirectory(dir) {
    let findings = [];
    const files = fs.readdirSync(dir);

    for (const file of files) {
        const fullPath = path.join(dir, file);
        
        // Skip explicitly excluded paths
        const relativePath = path.relative(process.cwd(), fullPath);
        if (excludePaths.some(excluded => relativePath.startsWith(excluded))) {
            continue;
        }

        const stat = fs.statSync(fullPath);
        if (stat.isDirectory()) {
            findings = findings.concat(scanDirectory(fullPath));
        } else {
            // Only scan text-like files, ignore images/binaries
            if (fullPath.match(/\.(php|js|html|css|json|md|yml|yaml|xml)$/)) {
                findings = findings.concat(scanFile(fullPath));
            }
        }
    }
    return findings;
}

function scanFile(filePath) {
    const findings = [];
    try {
        const content = fs.readFileSync(filePath, 'utf8');
        const lines = content.split('\n');
        
        lines.forEach((line, index) => {
            for (const [type, regex] of Object.entries(secretPatterns)) {
                if (regex.test(line)) {
                    // Crucial Requirement: Do NOT print the secret value!
                    // Only print File, Line number, and Secret Type.
                    findings.push({
                        file: path.relative(process.cwd(), filePath),
                        line: index + 1,
                        type: type
                    });
                }
            }
        });
    } catch (e) {
        // Skip files that can't be read
    }
    return findings;
}

console.log("===================================================");
console.log("FinTrack Pro - Secret Leak Scanner");
console.log("===================================================");

const results = scanDirectory(process.cwd());

if (results.length > 0) {
    console.error(`\n[!] DANGER: Found ${results.length} suspected secret(s) exposed in application code:\n`);
    results.forEach(res => {
        console.error(`  - [${res.type}] found in ${res.file} at line ${res.line}`);
    });
    console.error("\n[!] Please remove these secrets from source code and use environment variables.");
    process.exit(1);
} else {
    console.log("\n[OK] No exposed secrets detected in application source code.");
    process.exit(0);
}
