import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { defineConfig } from 'vite';
import Symfony from '@symfony/reprise/vite';
import inject from '@rollup/plugin-inject';

// Single source of truth for the versioned vendor/ path below and the
// matching setWorkerUrl() call in map_controller.js — keeps the two in
// sync automatically whenever the maplibre-gl dependency is bumped.
const { version: maplibreGlVersion } = JSON.parse(
    readFileSync('./node_modules/maplibre-gl/package.json', 'utf-8'),
);

export default defineConfig(({ command }) => ({
    input: {
        app: './assets/js/app.js',
        coaster: './assets/js/coaster.js',
        'top-list': './assets/js/top-list.js',
    },

    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./assets', import.meta.url)),
            '@images': fileURLToPath(new URL('./assets/images', import.meta.url)),
        },
    },

    css: {
        preprocessorOptions: {
            less: {
                // Enable inline JavaScript in LESS files (needed for Bootstrap 3.x)
                javascriptEnabled: true,
            },
        },
    },

    define: {
        // Legacy theme scripts (assets/js/theme/) assume Node's `global`.
        global: 'window',
    },

    server: {
        // Allow access from mobile devices on the local network. Vite
        // always allows numeric-IP Host headers regardless of
        // allowedHosts, which is how LAN devices reach the dev server —
        // so allowedHosts stays at its default (blocks hostname-based
        // requests, e.g. DNS rebinding), just host needs widening.
        host: true,
    },

    plugins: [
        Symfony({
            stimulus: 'assets/controllers.json',
            integrity: {
                enabled: command === 'build',
            },
            copy: [
                // MapLibre GL parses vector tiles in a Web Worker whose URL it
                // resolves dynamically at runtime — that resolution doesn't
                // survive bundling, so the worker silently fails to start.
                // Copying it as a static asset and pointing setWorkerUrl() at
                // it (in map_controller.js) works around this. The
                // destination is namespaced under the installed maplibre-gl
                // version (not content-hashed — the worker's own source
                // imports its sibling shared file by a fixed relative
                // filename, so hashing filenames independently would break
                // that) so a version bump can't leave a stale worker/shared
                // file cached under a URL a fresh main bundle still points
                // at.
                {
                    from: './node_modules/maplibre-gl/dist',
                    to: `vendor/maplibre-gl-${maplibreGlVersion}`,
                    pattern: /maplibre-gl-(worker|shared)\.mjs$/,
                    includeSubdirectories: false,
                    hash: false,
                },
            ],
        }),
        // Replacement for Encore's autoProvidejQuery(): a few legacy files
        // (theme scripts, some Stimulus controllers) reference $/jQuery as
        // globals instead of importing it.
        inject({ $: 'jquery', jQuery: 'jquery' }),
    ],
}));
