import { Livewire, Alpine } from '../../vendor/livewire/livewire/dist/livewire.esm';

// Reverb is optional. Do not open an idle WebSocket on every page when no
// realtime feature is enabled; this also avoids stale sockets during BFCache
// navigation in local development.
if (import.meta.env.VITE_REVERB_ENABLED === 'true') {
    import('./echo');
}

// Single Alpine instance, bundled with Livewire (avoids double-registration).
// Register Alpine plugins/components here before starting Livewire.
// e.g. Alpine.plugin(somePlugin)

window.Alpine = Alpine;

Livewire.start();
