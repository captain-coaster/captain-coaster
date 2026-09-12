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
        const tokens = attribute[1].replace(/{{[\s\S]*?}}/g, '').split(/\s+/);

        const deprecatedClass = tokens.find(
            (token) =>
                token === 'collapse' ||
                deprecatedMediaClasses.has(token) ||
                deprecatedCollectionClasses.has(token) ||
                deprecatedPanelClasses.has(token) ||
                deprecatedFieldClasses.has(token) ||
                deprecatedButtonClasses.has(token) ||
                deprecatedLabelClasses.has(token) ||
                deprecatedAlertClasses.has(token) ||
                deprecatedPaginationClasses.has(token)
        );

        if (deprecatedClass) {
            const line = source.slice(0, attribute.index).split('\n').length;
            const contract =
                deprecatedClass === 'collapse'
                    ? 'Bootstrap collapse class'
                    : deprecatedMediaClasses.has(deprecatedClass)
                      ? 'Bootstrap media class'
                      : deprecatedCollectionClasses.has(deprecatedClass)
                        ? 'Bootstrap collection class'
                        : deprecatedPanelClasses.has(deprecatedClass)
                          ? 'Bootstrap panel class'
                          : deprecatedFieldClasses.has(deprecatedClass)
                            ? 'Bootstrap field class'
                            : deprecatedButtonClasses.has(deprecatedClass)
                              ? 'Bootstrap button class'
                              : deprecatedLabelClasses.has(deprecatedClass)
                                ? 'Bootstrap label or badge class'
                                : deprecatedAlertClasses.has(deprecatedClass)
                                  ? 'Bootstrap alert or close class'
                                  : 'Bootstrap pagination class';
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
