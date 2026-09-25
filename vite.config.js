import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { defineConfig } from 'vite';
import Symfony from '@symfony/reprise/vite';
import tailwindcss from '@tailwindcss/vite';

// Single source of truth for the versioned vendor/ path below and the
// matching setWorkerUrl() call in map_controller.js — keeps the two in
// sync automatically whenever the maplibre-gl dependency is bumped.
const { version: maplibreGlVersion } = JSON.parse(
    readFileSync('./node_modules/maplibre-gl/package.json', 'utf-8'),
);

// bin/dev passes Symfony CLI's TLS certificate, so assets share the page's scheme.
const tlsP12 = process.env.VITE_TLS_P12;
const devOrigin =
    process.env.VITE_DEV_ORIGIN ??
    `${tlsP12 ? 'https' : 'http'}://localhost:${process.env.VITE_PORT ?? 5173}`;

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

    server: {
        // Allow access from mobile devices on the local network. Vite
        // always allows numeric-IP Host headers regardless of
        // allowedHosts, which is how LAN devices reach the dev server —
        // so allowedHosts stays at its default (blocks hostname-based
        // requests, e.g. DNS rebinding), just host needs widening.
        host: true,
        // bin/dev gives each worktree its own port and the page's asset
        // origin (the LAN IP, so a phone can load them too).
        port: Number(process.env.VITE_PORT ?? 5173),
        strictPort: true,
        https: tlsP12 ? { pfx: readFileSync(tlsP12), passphrase: '' } : undefined,
        origin: devOrigin,
        // Vite's default CORS only allows localhost pages; a phone loads the
        // page from the LAN IP.
        cors: {
            origin: [
                /^https?:\/\/(localhost|127\.0\.0\.1)(:\d+)?$/,
                `${new URL(devOrigin).protocol}//${new URL(devOrigin).hostname}:${process.env.SYMFONY_PORT ?? 8000}`,
            ],
        },
    },

    plugins: [
        tailwindcss(),
        Symfony({
            stimulus: 'assets/controllers.json',
            integrity: {
                enabled: command === 'build',
            },
            copy: [
                // Images referenced from Twig (badge filenames come from the
                // database), resolved via asset('build/images/...').
                {
                    from: './assets/images',
                    to: 'images',
                    pattern: /\.(png|svg)$/,
                },
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
    ],
}));
