import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { google } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';

const vitePort = Number(process.env.VITE_PORT || 5173);
const appPort = Number(process.env.APP_PORT || 80);

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
            fonts: [
                google('Be Vietnam Pro', {
                    weights: [400, 500, 600, 700, 800],
                    display: 'swap',
                    preload: true,
                    subsets: ['latin', 'vietnamese'],
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
        host: '0.0.0.0',
        port: vitePort,
        // Page is served from Nginx (APP_PORT); Vite must allow that origin for @vite/client.
        cors: {
            origin: `http://localhost:${appPort}`,
        },
        hmr: {
            host: 'localhost',
            protocol: 'ws',
            clientPort: vitePort,
            port: vitePort,
        },
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
