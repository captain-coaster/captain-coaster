// Builds /maintenance.html from assets/maintenance/maintenance.html.
// While maintenance is on, nginx answers every URL with that page, so it has
// to be self-contained: the site fonts are inlined as data URIs and the logo
// artwork is copied from the Logo component (templates/components/Logo.html.twig).
import { readFileSync, writeFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';

const root = fileURLToPath(new URL('..', import.meta.url));
const read = (path) => readFileSync(root + path);

let html = read('assets/maintenance/maintenance.html').toString();

html = html.replace(/url\("\.\.\/fonts\/([^"]+\.woff2)"\)/g, (_, file) =>
    `url("data:font/woff2;base64,${read(`assets/fonts/${file}`).toString('base64')}")`,
);

// Every <path> of the full logo, Twig tags and comments dropped.
const logo = read('templates/components/Logo.html.twig')
    .toString()
    .match(/<path [^>]+\/>/g)
    .join('\n        ');
html = html.replace('{{ logo }}', logo);

// The source's editing note is for this repo, not for visitors.
html = html.replace(/<!--[\s\S]*?-->\n/, '');

writeFileSync(root + 'maintenance.html', html);
console.log(`maintenance.html written (${Math.round(html.length / 1024)} kB)`);
