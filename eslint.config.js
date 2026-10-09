import js from '@eslint/js';
import { defineConfig, globalIgnores } from 'eslint/config';
import globals from 'globals';

export default defineConfig([
    globalIgnores(['node_modules/', 'public/', 'var/', 'vendor/']),
    js.configs.recommended,
    {
        files: ['assets/**/*.js'],
        languageOptions: {
            // Routing: FOSJsRoutingBundle, loaded by a script tag in base.html.twig
            globals: { ...globals.browser, Routing: 'readonly' },
        },
    },
    {
        // Vitest in jsdom: the controllers' globals, with Routing stubbed by the setup file
        files: ['tests/js/**/*.js'],
        languageOptions: {
            globals: { ...globals.browser, Routing: 'writable' },
        },
    },
    {
        files: ['scripts/**/*.mjs', '*.js'],
        languageOptions: { globals: globals.node },
    },
]);
