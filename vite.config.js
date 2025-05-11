import { defineConfig } from "vite"
import symfonyPlugin from "vite-plugin-symfony"
import tailwindcss from "@tailwindcss/vite"

/* if you're using React */
// import react from '@vitejs/plugin-react';

export default defineConfig({
    plugins: [
        /* react(), // if you're using React */
        symfonyPlugin(),
        tailwindcss(),
    ],
    build: {
        rollupOptions: {
            input: {
                app: "./assets/app.js",
            },
        },
        assetsInlineLimit: 0,
    },
})
