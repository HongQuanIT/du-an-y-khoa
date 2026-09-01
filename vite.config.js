import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { google } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';

const vitePort = Number(process.env.VITE_PORT || 5173);
const appPort = Number(process.env.APP_PORT || 80);

/** Browsers omit :80/:443 in Origin; allow both forms for local dev. */
function resolveDevCorsOrigins() {
    const origins = new Set();

    const add = (raw) => {
        try {
            const url = new URL(raw);
            origins.add(url.origin);

            const defaultPort = url.protocol === 'https:' ? '443' : '80';
            const port = url.port || defaultPort;

            if (port === '80' || port === '443') {
                origins.add(`${url.protocol}//${url.hostname}`);
            }
            if (port === '80') {
                origins.add(`${url.protocol}//${url.hostname}:80`);
            }
        } catch {
            // ignore invalid URL
        }
    };

    if (process.env.APP_URL) {
        add(process.env.APP_URL);
    }

    add(`http://localhost:${appPort}`);
    add('http://127.0.0.1');

    return [...origins];
}

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/js/admin/dashboard-charts.js',
                'resources/js/classroom/presenter-window.js',
            ],
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
        // Page is served from Nginx (APP_PORT); allow Origin variants (with/without :80).
        cors: {
            origin: resolveDevCorsOrigins(),
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
