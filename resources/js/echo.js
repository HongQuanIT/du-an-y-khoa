import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

// Laravel Reverb speaks the Pusher protocol.
window.Pusher = Pusher;

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
    wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
    enabledTransports: ['ws', 'wss'],
});

// A page may be frozen in the browser's back-forward cache. Close Reverb
// before it is frozen and reconnect when that page is restored.
window.addEventListener('pagehide', () => window.Echo?.disconnect());
window.addEventListener('pageshow', (event) => {
    if (event.persisted) {
        window.Echo?.connect();
    }
});
