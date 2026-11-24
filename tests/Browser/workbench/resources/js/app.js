/*
 * Stands in for a host application's main script: the design system's
 * behavior engine, which the component library's 3.0 components run on, and
 * livewire-drag-and-drop, as the install command tells a host to load them.
 * Alpine comes with Livewire, so the engine registers as Alpine starts.
 */
import apuiBehavior from '@artisanpack-ui/behavior/alpine';
import '@artisanpack-ui/livewire-drag-and-drop';

document.addEventListener( 'alpine:init', () => window.Alpine.plugin( apuiBehavior ) );
