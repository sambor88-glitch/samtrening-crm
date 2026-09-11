import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
            // Fetched at build time and served from our own domain, so the browser never calls a
            // font CDN. latin-ext carries the Polish letters.
            fonts: [
                bunny('Anton', { weights: [400], subsets: ['latin', 'latin-ext'] }),
                bunny('Archivo', { weights: [400, 600, 800], subsets: ['latin', 'latin-ext'] }),
            ],
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
