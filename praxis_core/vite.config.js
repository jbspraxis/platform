import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            // charts.js is its own entry: only the pages that draw a chart load it (with import())
            input: ['resources/css/app.css', 'resources/js/app.js', 'resources/js/charts.js'],
            refresh: true,
        }),
        tailwindcss(),
    ],
    build: {
        rollupOptions: {
            // Keep each entry's exports: the pages read Chart from charts.js's default export
            preserveEntrySignatures: 'exports-only',
        },
    },
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
