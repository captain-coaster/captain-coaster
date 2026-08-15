import { defineConfig } from 'vite';
import symfonyPlugin from 'vite-plugin-symfony';
import tailwindcss from '@tailwindcss/vite';
import { join } from 'node:path';
import { homedir } from 'node:os';
import { existsSync } from 'node:fs';

const certPath = join(homedir(), '.symfony5/certs/default.p12');

export default defineConfig(({ command }) => ({
    server: {
        host: '0.0.0.0',
        https: existsSync(certPath) ? { pfx: certPath } : undefined,
        cors: true,
    },
    plugins: [
        symfonyPlugin({
            stimulus: true,
            viteDevServerHostname: '127.0.0.1',
        }),
        tailwindcss(),
    ],
    // Strip console.* and debugger only in production builds
    esbuild: command === 'build' ? { drop: ['console', 'debugger'] } : {},
    build: {
        outDir: 'public/build',
        rollupOptions: {
            input: {
                app: './assets/js/app.js',
            },
        },
    },
}));
