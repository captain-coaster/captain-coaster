import { fileURLToPath } from 'node:url';
import { defineConfig } from 'vitest/config';

// Its own config: the tests need neither Tailwind nor the Symfony plugin of vite.config.js.
export default defineConfig({
    resolve: {
        alias: [
            // The real catalog is generated in var/ by the Symfony cache warmup
            {
                find: /^\.\.\/translator$/,
                replacement: fileURLToPath(
                    new URL('./tests/js/support/translator.js', import.meta.url)
                ),
            },
        ],
    },
    test: {
        environment: 'jsdom',
        // One jsdom per worker instead of one per file: setup.js resets what a test leaves behind
        isolate: false,
        environmentOptions: {
            jsdom: { url: 'https://captaincoaster.test/en/' },
        },
        include: ['tests/js/**/*.test.js'],
        setupFiles: ['tests/js/support/setup.js'],
        restoreMocks: true,
        unstubGlobals: true,
    },
});
