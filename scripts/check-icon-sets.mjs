// Fails when a locked icon (assets/icons/, written by `ux:icons:lock`) is
// outside the app's icon set. CI's lock check already guarantees every icon
// a template uses is locked, so the locked files are the full inventory.
import { readdirSync } from 'node:fs';
import { join } from 'node:path';

const iconsDir = join(process.cwd(), 'assets/icons');
const primarySet = 'lucide';
const exceptions = new Set([
    'fe/google.svg', // Brand logo on the sign-in button
    'tabler/user.svg', // EasyAdmin's own Button template (vendor)
]);

const violations = readdirSync(iconsDir, { recursive: true, withFileTypes: true })
    .filter((entry) => entry.isFile() && entry.name.endsWith('.svg'))
    .map((entry) => join(entry.parentPath, entry.name).slice(iconsDir.length + 1))
    .filter((path) => !path.startsWith(`${primarySet}/`) && !exceptions.has(path));

if (violations.length > 0) {
    console.error(`Icons outside the ${primarySet} set (DESIGN.md, Iconography):`);
    for (const path of violations) {
        console.error(`  assets/icons/${path}`);
    }
    console.error(`Use a ${primarySet}: icon instead, then delete the file above.`);
    process.exit(1);
}

console.log(`Icon sets OK: every locked icon is ${primarySet} or an allowed exception.`);
