import { readdirSync, readFileSync } from 'node:fs';
import { join, relative } from 'node:path';

const root = process.cwd();
const sourceRoots = ['assets/js', 'assets/styles', 'assets/controllers', 'templates'];
const excludedFiles = new Set([
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
const deprecatedCollectionClasses = new Set([
    'thumbnail',
    'caption',
    'thumb',
    'caption-overflow',
    'list-group',
    'list-group-item',
]);
const deprecatedPanelClasses = new Set([
    'panel',
    'panel-body',
    'panel-heading',
    'panel-footer',
    'panel-title',
    'panel-flat',
    'panel-white',
]);
const deprecatedFieldClasses = new Set([
    'form-group',
    'form-group-xs',
    'form-control',
    'form-control-lg',
    'form-control-feedback',
    'control-label',
]);
const deprecatedButtonClasses = new Set([
    'btn',
    'btn-default',
    'btn-primary',
    'btn-info',
    'btn-danger',
    'btn-link',
    'btn-lg',
    'btn-sm',
    'btn-xs',
    'btn-block',
    'btn-rounded',
    'btn-flat',
    'btn-icon',
    'btn-float',
    'btn-float-lg',
    'btn-labeled',
    'btn-labeled-right',
    'btn-block-group',
]);
const deprecatedLabelClasses = new Set([
    'label',
    'label-default',
    'label-primary',
    'label-success',
    'label-info',
    'label-warning',
    'label-danger',
    'label-rounded',
    'label-flat',
    'label-icon',
    'badge',
    'badge-primary',
    'badge-flat',
]);
const deprecatedAlertClasses = new Set([
    'alert',
    'alert-success',
    'alert-info',
    'alert-warning',
    'alert-danger',
    'alert-heading',
    'alert-dismissible',
    'alert-dismissable',
    'alert-component',
    'alert-styled-left',
    'alert-arrow-left',
    'alert-bordered',
    'close',
]);
const deprecatedPaginationClasses = new Set(['pagination', 'pagination-sm']);
const deprecatedNavigationClasses = new Set([
    'nav', 'navbar', 'navbar-inverse', 'navbar-fixed-top', 'navbar-top',
    'navbar-header', 'navbar-brand', 'navbar-nav', 'navbar-right', 'navbar-toggle-icon',
    'dropdown', 'dropdown-user', 'dropdown-toggle', 'dropdown-menu',
    'dropdown-menu-right', 'dropdown-menu-left', 'dropdown-submenu',
    'dropdown-submenu-hover', 'dropdown-submenu-left', 'dropdown-divider', 'dropdown-header',
]);
const deprecatedVisibilityClasses = new Set(['hidden-xs', 'visible-xs', 'visible-xs-block']);

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
        {
            contract: 'Bootstrap collection selector',
            pattern:
                /(?:^|[^\w-])\.(?:thumbnail|caption|thumb|caption-overflow|list-group|list-group-item)(?![\w-])/g,
        },
        {
            contract: 'Bootstrap panel selector',
            pattern:
                /(?:^|[^\w-])\.(?:panel|panel-body|panel-heading|panel-footer|panel-title|panel-flat|panel-white)(?![\w-])/g,
        },
        {
            contract: 'Bootstrap field selector',
            pattern:
                /(?:^|[^\w-])\.(?:form-group|form-group-xs|form-control|form-control-lg|form-control-feedback|control-label)(?![\w-])/g,
        },
        {
            contract: 'Bootstrap button selector',
            pattern: /(?:^|[^\w-])\.btn(?:-[\w-]+)?(?![\w-])/g,
        },
        {
            contract: 'Bootstrap label or badge selector',
            pattern: /(?:^|[^\w-])\.(?:label|badge)(?:-[\w-]+)?(?![\w-])/g,
        },
        {
            contract: 'Bootstrap alert or close selector',
            pattern: /(?:^|[^\w-])\.alert(?:-[\w-]+)?(?![\w-])|(?:^|[^\w-])\.close(?![\w-]|\s*\()/g,
        },
        {
            contract: 'Bootstrap pagination selector',
            pattern: /(?:^|[^\w-])\.pagination(?:-[\w-]+)?(?![\w-])/g,
        },
        {
            contract: 'Bootstrap navigation selector',
            pattern: /(?:^|[^\w-])\.(?:nav|navbar|dropdown)(?:-[\w-]+)?(?![\w-])/g,
        },
        {
            contract: 'Bootstrap dropdown data attribute',
            pattern: /data-toggle="dropdown"/g,
        },
        {
            // Hand-written rules must not shadow the utilities generated
            // from the design tokens (tokens.css `@theme inline`), e.g. the
            // legacy `.text-muted`/`.bg-success` retired by the reskin (#413).
            contract: 'selector shadowing a design-token utility',
            pattern:
                /(?:^|[^\w-])\.(?:bg|text|border(?:-[trblxy])?|outline|ring|fill|stroke|decoration|divide|accent|caret)-(?:canvas|surface|ink|muted|line|control-line|action|action-hover|on-action|selected|highlight|on-highlight|success|success-bg|warning|warning-bg|danger|danger-bg|focus|logo-ink)(?![\w-])/g,
        },
        {
            // Custom breakpoints replaced by Tailwind's defaults (#414):
            // tablet -> md, desktop -> lg, wide -> xl. They'd generate nothing.
            contract: 'retired breakpoint (use md:/lg:/xl:)',
            pattern: /(?<=[\s"'`])(?:max-)?(?:tablet|desktop|wide):[\w[-]|--breakpoint-(?:tablet|desktop|wide)\b/g,
        },
    ];

    for (const { contract, pattern } of checks) {
        for (const match of content.matchAll(pattern)) {
            const line = content.slice(0, match.index).split('\n').length;
            violations.push(`${path}:${line} ${contract}`);
        }
    }
}

const deprecatedClassFamilies = [
    { set: deprecatedMediaClasses, contract: 'Bootstrap media class' },
    { set: deprecatedCollectionClasses, contract: 'Bootstrap collection class' },
    { set: deprecatedPanelClasses, contract: 'Bootstrap panel class' },
    { set: deprecatedFieldClasses, contract: 'Bootstrap field class' },
    { set: deprecatedButtonClasses, contract: 'Bootstrap button class' },
    { set: deprecatedLabelClasses, contract: 'Bootstrap label or badge class' },
    { set: deprecatedAlertClasses, contract: 'Bootstrap alert or close class' },
    { set: deprecatedPaginationClasses, contract: 'Bootstrap pagination class' },
    { set: deprecatedNavigationClasses, contract: 'Bootstrap navigation class' },
    { set: deprecatedVisibilityClasses, contract: 'Bootstrap visibility class' },
];

// A dynamic class built as `stem-{{ expression }}` (e.g. the pre-migration
// `alert-{{ label }}`) would otherwise slip past every check above: naively
// stripping `{{ ... }}` collapses it to the token `stem-`, which isn't a
// literal entry in any deprecated-class set. Replaced with a marker instead
// of deleted, so `stem-EXPR` can be recognized as a dynamic build of a
// deprecated family (any set already containing a `stem-something` entry).
const EXPR_MARKER = 'EXPR';

function dynamicStemFamily(stem) {
    return deprecatedClassFamilies.find(({ set }) =>
        [...set].some((cls) => cls.startsWith(`${stem}-`))
    );
}

function addTemplateClassViolations(path, source) {
    for (const attribute of source.matchAll(/class="([^"]*)"/g)) {
        const tokens = attribute[1].replace(/{{[\s\S]*?}}/g, EXPR_MARKER).split(/\s+/);

        let contract;
        const deprecatedClass = tokens.find((token) => {
            if (token === 'collapse') {
                contract = 'Bootstrap collapse class';

                return true;
            }

            const family = deprecatedClassFamilies.find(({ set }) => set.has(token));

            if (family) {
                contract = family.contract;

                return true;
            }

            if (token.endsWith(`-${EXPR_MARKER}`)) {
                const dynamicFamily = dynamicStemFamily(token.slice(0, -1 - EXPR_MARKER.length));

                if (dynamicFamily) {
                    contract = dynamicFamily.contract;

                    return true;
                }
            }

            return false;
        });

        if (deprecatedClass) {
            const line = source.slice(0, attribute.index).split('\n').length;
            violations.push(`${path}:${line} ${contract}`);
        }
    }
}

// Redesigned Twig Components (templates/components/) use only Tailwind
// utilities: no class defined by a hand-written stylesheet (AGENTS.md
// "Redesign: target vs. current"), and no legacy `--cc-*` token.
const componentsRoot = 'templates/components';
const utilityStylesheets = new Set(['assets/styles/app.css', 'assets/styles/tokens.css']);

function legacyClassNames() {
    const names = new Set();

    for (const path of sourceFiles('assets/styles')) {
        if (!path.endsWith('.css') || utilityStylesheets.has(path)) continue;

        const css = withoutComments(readFileSync(join(root, path), 'utf8'), '.css')
            .replace(/url\([^)]*\)/g, '')
            .replace(/\{[^{}]*\}/g, '{}');

        // Skips `element.class` compounds such as icons.css's `svg.w-6`:
        // refinements of a Tailwind utility, not a legacy class of their own.
        for (const match of css.matchAll(/(?<![\w-])\.(-?[A-Za-z_][\w-]*)/g)) {
            names.add(match[1]);
        }
    }

    return names;
}

function addComponentViolations(path, rawSource, legacyClasses) {
    // Blank out Twig comments, keeping line numbers.
    const source = rawSource.replace(/{#[\s\S]*?#}/g, (comment) => comment.replace(/[^\n]/g, ' '));
    const lineOf = (index) => source.slice(0, index).split('\n').length;

    for (const match of source.matchAll(/var\(--cc-/g)) {
        violations.push(`${path}:${lineOf(match.index)} legacy --cc-* token in a component`);
    }

    // Every string literal and class attribute: covers class="…" and the
    // class strings passed to html_cva()/attributes.defaults().
    for (const match of source.matchAll(/class="([^"]*)"|'([^']*)'/g)) {
        const value = (match[1] ?? match[2]).replace(/{{[\s\S]*?}}/g, ' ');
        const tokens = value.trim().split(/\s+/);
        // A single bare word in a string literal is a translation domain,
        // route or role, not a class list.
        const isClassList = match[1] !== undefined || tokens.length > 1 || tokens[0].includes('-');
        const legacy = isClassList && tokens.find((token) => legacyClasses.has(token));

        if (legacy) {
            violations.push(`${path}:${lineOf(match.index)} legacy class "${legacy}" in a component`);
        }
    }
}

const legacyClasses = legacyClassNames();

for (const path of sourceFiles(componentsRoot)) {
    addComponentViolations(path, readFileSync(join(root, path), 'utf8'), legacyClasses);
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
