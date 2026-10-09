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
        files: ['scripts/**/*.mjs', '*.js'],
        languageOptions: { globals: globals.node },
    },
]);
