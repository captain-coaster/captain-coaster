import { readdirSync, readFileSync } from 'node:fs';
import { join, relative } from 'node:path';

const root = process.cwd();
const sourceRoots = ['assets/styles', 'assets/controllers', 'templates'];
const excludedFiles = new Set([
    'assets/styles/print.css',
    'templates/connect/login_email.html.twig',
]);
const violations = [];
const deprecatedMediaClasses = new Set([
    'media',
    'media-left',
    'media-right',
    'media-body',
    'media-middle',
    'media-heading',
    'media-list',
    'media-list-bordered',
    'media-list-container',
    'media-annotation',
    'stack-media-on-mobile',
]);

function sourceFiles(directory) {
    return readdirSync(join(root, directory), { withFileTypes: true }).flatMap(
        (entry) => {
            const path = join(directory, entry.name);

            return entry.isDirectory() ? sourceFiles(path) : [path];
        }
    );
}

function withoutComments(source, extension) {
    const withoutBlockComments = source.replace(/\/\*[\s\S]*?\*\//g, '');

    return extension === '.js'
        ? withoutBlockComments.replace(/^[\t ]*\/\/.*$/gm, '')
        : withoutBlockComments;
}

function addViolations(path, source) {
    const extension = path.slice(path.lastIndexOf('.'));
    const content = withoutComments(source, extension);
    const checks = [
        {
            contract: 'Bootstrap collapse selector',
            pattern:
                /(?:^|[\n,\s])(?:\.collapse(?![\w-])|\.navbar-collapse\.in(?![\w-])|#navbar-mobile\.collapse(?![\w-]))/g,
        },
        {
            contract: 'navbar .in state',
            pattern:
                /(?:getElementById\('navbar-mobile'\)[\s\S]{0,120}?classList\.(?:add|remove|toggle)\('in'\)|panelTarget\.classList\.(?:add|remove|toggle)\('in'\))/g,
        },
        {
            contract: 'table layout',
            pattern:
                /display:\s*table(?:-row|-cell)?\b|width:\s*10000px\b|@apply[^;]*\btable-cell\b|@apply[^;]*w-\[10000px\]/g,
        },
        {
            contract: 'Bootstrap media selector',
            pattern:
                /(?:^|[^\w-])\.(?:media|media-left|media-right|media-body|media-middle|media-heading|media-list|media-list-bordered|media-list-container|media-annotation|stack-media-on-mobile)(?![\w-])/g,
        },
    ];

    for (const { contract, pattern } of checks) {
        for (const match of content.matchAll(pattern)) {
            const line = content.slice(0, match.index).split('\n').length;
            violations.push(`${path}:${line} ${contract}`);
        }
    }
}

function addTemplateClassViolations(path, source) {
    for (const attribute of source.matchAll(/class="([^"]*)"/g)) {
        const tokens = attribute[1].split(/\s+/);

        const deprecatedClass = tokens.find(
            (token) => token === 'collapse' || deprecatedMediaClasses.has(token)
        );

        if (deprecatedClass) {
            const line = source.slice(0, attribute.index).split('\n').length;
            const contract =
                deprecatedClass === 'collapse'
                    ? 'Bootstrap collapse class'
                    : 'Bootstrap media class';
            violations.push(`${path}:${line} ${contract}`);
        }
    }
}

for (const directory of sourceRoots) {
    for (const path of sourceFiles(directory)) {
        if (!excludedFiles.has(path)) {
            const source = readFileSync(join(root, path), 'utf8');
            addViolations(path, source);

            if (path.endsWith('.twig')) {
                addTemplateClassViolations(path, source);
            }
        }
    }
}

if (violations.length > 0) {
    console.error(violations.join('\n'));
    process.exitCode = 1;
}
