/**
 * Builds the asset bundle the browser tests load (tests/Browser).
 *
 * The package ships no CSS or JS build: a host compiles the board's views
 * with its own Tailwind setup and imports livewire-drag-and-drop itself
 * (`php artisan ecommerce-kanban:install` prints both). The browser suite
 * stands in for that host, so it builds a small Tailwind + daisyUI bundle
 * and a script that loads drag-and-drop, into the Testbench public
 * directory the browser tests point at.
 *
 * daisyUI is pinned to ~5.0 in package.json: later 5.x releases hide
 * `.tab-content`, which leaves the component library's tab panels empty
 * (ArtisanPack-UI/livewire-ui-components#119).
 */
import tailwindcss from '@tailwindcss/vite';
import laravel from 'laravel-vite-plugin';
import { defineConfig } from 'vite';

export default defineConfig( {
    plugins: [
        laravel( {
            input: [
                'tests/Browser/workbench/resources/css/app.css',
                'tests/Browser/workbench/resources/js/app.js',
            ],
            publicDirectory: 'tests/Browser/workbench/public',
            buildDirectory: 'build',
            refresh: false,
        } ),
        tailwindcss(),
    ],
} );
