import { Livewire, Alpine } from '../../vendor/livewire/livewire/dist/livewire.esm';
import './echo';

// Single Alpine instance, bundled with Livewire (avoids double-registration).
// Register Alpine plugins/components here before starting Livewire.
// e.g. Alpine.plugin(somePlugin)

window.Alpine = Alpine;

Livewire.start();
