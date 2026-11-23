/**
 * Builds the asset bundle the browser tests load (tests/Browser).
 *
 * The package ships no CSS or JS build: a host loads the ArtisanPack UI
 * design system and livewire-drag-and-drop with its own build
 * (`php artisan ecommerce-kanban:install` prints the board's part). The
 * browser suite stands in for that host, so it builds a small Tailwind +
 * design-system bundle and a script that loads the design system's behavior
 * engine and drag-and-drop, into the Testbench public directory the browser
 * tests point at.
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
