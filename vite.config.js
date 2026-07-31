import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny, google } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
            fonts: [
                bunny('Be Vietnam Pro', {
                    weights: [400, 500, 600, 700, 800],
                }),
                google('Material Symbols Outlined', {
                    weights: [100, 200, 300, 400, 500, 600, 700],
                    display: 'block',
                    preload: false,
                    subsets: ['fallback'],
                    optimizedFallbacks: false,
                }),
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
