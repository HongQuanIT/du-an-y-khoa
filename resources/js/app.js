import { Livewire, Alpine } from '../../vendor/livewire/livewire/dist/livewire.esm';
<<<<<<< HEAD

// Reverb is optional. Do not open an idle WebSocket on every page when no
// realtime feature is enabled; this also avoids stale sockets during BFCache
// navigation in local development.
if (import.meta.env.VITE_REVERB_ENABLED === 'true') {
    import('./echo');
}

// Single Alpine instance, bundled with Livewire (avoids double-registration).
// Register Alpine plugins/components here before starting Livewire.
// e.g. Alpine.plugin(somePlugin)
=======
import './echo';
import { bootLivekitRooms } from './classroom/livekit-room';
import { bootLiveRooms } from './classroom/live-room';
>>>>>>> 8baca02 (feat(classroom): phòng live chữa đề với LiveKit và tương tác realtime)

window.Alpine = Alpine;

Livewire.start();

const bootClassroom = () => {
    bootLivekitRooms();
    bootLiveRooms();
};
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootClassroom, { once: true });
} else {
    bootClassroom();
}
