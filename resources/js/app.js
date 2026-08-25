import { Livewire, Alpine } from '../../vendor/livewire/livewire/dist/livewire.esm';
import { bootLivekitRooms } from './classroom/livekit-room';
import { bootLiveRooms } from './classroom/live-room';
import { registerRichEditor } from './admin/rich-editor';
import { registerMediaPicker } from './admin/media-picker';
import { bootstrapTheme } from './theme';
import { bootstrapCookieConsent, registerCookieBannerAlpine } from './cookie-consent';
import './support-typing';
import { bootAdminSupportInbox } from './admin/support-inbox';
import { bootAdminSupportThread } from './admin/support-thread';
import { bootUserSupportThread } from './support-user-thread';
import { bootNotifications } from './notifications';
import { bootActivityHeartbeat } from './activity-heartbeat';

bootstrapTheme();
bootstrapCookieConsent();

// Reverb is loaded lazily for features such as support chat. This avoids an
// idle socket on every page while still allowing support to be realtime even
// when VITE_REVERB_ENABLED is false.
// IMPORTANT: define before any boot* that calls it (deferred modules often run
// when document.readyState is already "interactive").
window.enableMedlearnRealtime = () => {
    if (!window.__medlearnEchoLoading) {
        window.__medlearnEchoLoading = import('./echo').then(() => {
            window.dispatchEvent(new Event('medlearn:echo-ready'));
            return window.Echo;
        }).catch((error) => {
            window.__medlearnEchoLoading = null;
            throw error;
        });
    }

    return window.__medlearnEchoLoading;
};

if (import.meta.env.VITE_REVERB_ENABLED === 'true') {
    window.enableMedlearnRealtime();
}

const bootSupportUi = () => {
    bootAdminSupportInbox();
    bootAdminSupportThread();
    bootUserSupportThread();
    bootNotifications();
    bootActivityHeartbeat();
};
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootSupportUi, { once: true });
} else {
    bootSupportUi();
}

// Register Alpine.data before Livewire boots Alpine (and on alpine:init as fallback).
document.addEventListener('alpine:init', () => {
    registerCookieBannerAlpine(window.Alpine ?? Alpine);
});

// Single Alpine instance, bundled with Livewire (avoids double-registration).
// Guard against Vite HMR / double-eval re-running Livewire.start(), which
// throws: Cannot redefine property: $persist
if (! window.__medlearnLivewireStarted) {
    window.__medlearnLivewireStarted = true;
    window.Alpine = Alpine;
    registerRichEditor(Alpine);
    registerMediaPicker(Alpine);
    registerCookieBannerAlpine(Alpine);
    Livewire.start();
} else if (window.Alpine) {
    registerRichEditor(window.Alpine);
    registerMediaPicker(window.Alpine);
    registerCookieBannerAlpine(window.Alpine);
} else {
    window.Alpine = Alpine;
    registerRichEditor(Alpine);
    registerMediaPicker(Alpine);
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
