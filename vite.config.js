import { defineConfig } from 'vite';
import symfonyPlugin from 'vite-plugin-symfony';
import tailwindcss from '@tailwindcss/vite';
import { join } from 'node:path';
import { homedir } from 'node:os';

export default defineConfig({
    server: {
        host: '0.0.0.0',
        https: {
            pfx: join(homedir(), '.symfony5/certs/default.p12'),
        },
        cors: true,
    },
    plugins: [
        symfonyPlugin({
            stimulus: true,
            viteDevServerHostname: '127.0.0.1',
        }),
        tailwindcss(),
    ],
    build: {
        outDir: 'public/build',
        rollupOptions: {
            input: {
                app: './assets/js/app.js',
            },
        },
    },
});
