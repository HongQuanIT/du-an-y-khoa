import { Livewire, Alpine } from '../../vendor/livewire/livewire/dist/livewire.esm';
import { bootLivekitRooms } from './classroom/livekit-room';
import { bootLiveRooms } from './classroom/live-room';
import { registerRichEditor } from './admin/rich-editor';
import { bootstrapTheme } from './theme';
import { bootstrapCookieConsent, registerCookieBannerAlpine } from './cookie-consent';

bootstrapTheme();
bootstrapCookieConsent();

// Register Alpine.data before Livewire boots Alpine (and on alpine:init as fallback).
document.addEventListener('alpine:init', () => {
    registerCookieBannerAlpine(window.Alpine ?? Alpine);
});

// Reverb is optional. Do not open an idle WebSocket on every page when no
// realtime feature is enabled; this also avoids stale sockets during BFCache
// navigation in local development.
if (import.meta.env.VITE_REVERB_ENABLED === 'true') {
    import('./echo');
}

// Single Alpine instance, bundled with Livewire (avoids double-registration).
// Guard against Vite HMR / double-eval re-running Livewire.start(), which
// throws: Cannot redefine property: $persist
if (! window.__medlearnLivewireStarted) {
    window.__medlearnLivewireStarted = true;
    window.Alpine = Alpine;
    registerRichEditor(Alpine);
    registerCookieBannerAlpine(Alpine);
    Livewire.start();
} else if (window.Alpine) {
    registerRichEditor(window.Alpine);
    registerCookieBannerAlpine(window.Alpine);
} else {
    window.Alpine = Alpine;
    registerRichEditor(Alpine);
    registerCookieBannerAlpine(Alpine);
}

const bootClassroom = () => {
    bootLivekitRooms();
    bootLiveRooms();
};
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootClassroom, { once: true });
} else {
    bootClassroom();
}
