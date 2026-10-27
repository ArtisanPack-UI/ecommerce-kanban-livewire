{{--
    The standalone board layout (spec §5.3).

    Used when neither a configured layout, cms-framework, nor the ecommerce
    admin is available. Its contract matches theirs: a `title` section (plain
    text), a `content` section, and `styles` / `scripts` stacks, so a page
    extends whichever layout is resolved without a branch of its own.

    The package ships no CSS build. The host's Vite entries (filterable with
    `ap.ecommerceKanbanLivewire.layout.viteEntries`) are loaded when a Vite
    build or dev server is present; the host adds this package's views to its
    Tailwind `@source` list (see `php artisan ecommerce-kanban:install`).

    Publish with `php artisan vendor:publish --tag=ecommerce-kanban-views`.

    @package    ArtisanPack_UI
    @subpackage EcommerceKanbanLivewire

    @since      1.0.0
--}}
@php
    $ecommerceKanbanPickerUrl = \ArtisanPackUI\EcommerceKanbanLivewire\Support\BoardUrls::picker();
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace( '_', '-', app()->getLocale() ) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield( 'title', __( 'Kanban' ) ) &middot; {{ config( 'app.name' ) }}</title>
    @include( 'ecommerce-kanban::partials.vite-assets' )
    @livewireStyles
    @stack( 'styles' )
</head>
<body class="min-h-screen font-sans antialiased bg-base-200">
    <a href="#ecommerce-kanban-content" class="sr-only focus:not-sr-only focus:absolute focus:start-2 focus:top-2 focus:z-50 btn btn-sm">
        {{ __( 'Skip to content' ) }}
    </a>

    <header>
        <x-artisanpack-nav sticky full-width>
            <x-slot:brand>
                @if ( null !== $ecommerceKanbanPickerUrl )
                    <a href="{{ $ecommerceKanbanPickerUrl }}" class="font-bold">{{ __( 'Kanban' ) }}</a>
                @else
                    <span class="font-bold">{{ __( 'Kanban' ) }}</span>
                @endif
            </x-slot:brand>

            <x-slot:actions>
                @yield( 'header' )
                <x-artisanpack-theme-toggle aria-label="{{ __( 'Toggle dark mode' ) }}" />
            </x-slot:actions>
        </x-artisanpack-nav>
    </header>

    <main id="ecommerce-kanban-content" class="w-full p-4">
        @yield( 'content' )
    </main>

    <x-artisanpack-toast />

    @livewireScripts
    @include( 'ecommerce-kanban::partials.rate-limit-notice' )
    @stack( 'scripts' )
</body>
</html>
