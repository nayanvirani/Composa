import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';
import { fileURLToPath } from 'node:url';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/js/app.jsx', 'resources/css/site.css'],
            refresh: true,
        }),
        react(),
    ],
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
            // The same engine the storefront runs, for the builder's live preview and price tests.
            '@engine': fileURLToPath(new URL('./engine/js/src/index.js', import.meta.url)),
        },
    },
    build: {
        // The entry carries React, Inertia and Polaris (~140 kB gzipped); pages load on demand.
        chunkSizeWarningLimit: 600,
    },
    server: {
        watch: { ignored: ['**/storage/framework/views/**'] },
    },
});
