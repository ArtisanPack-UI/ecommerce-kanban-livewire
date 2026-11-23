{{--
    The standalone board layout (spec §5.3).

    Used when neither a configured layout, cms-framework, nor the ecommerce
    admin is available. Its contract matches theirs: a `title` section (plain
    text), a `content` section, and `styles` / `scripts` stacks, so a page
    extends whichever layout is resolved without a branch of its own.

    The package ships no CSS build. The design system's CSS and behavior
    engine come from the host's Vite entries (filterable with
    `ap.ecommerceKanbanLivewire.layout.viteEntries`), loaded when a Vite
    build or dev server is present; the host adds this package's views to its
    Tailwind `@source` list (see `php artisan ecommerce-kanban:install`). The
    board's own token-only styles are inlined (see partials/styles), and
    `@apuiThemeScript` applies the light or dark choice before the first
    paint.

    Publish with `php artisan vendor:publish --tag=ecommerce-kanban-views`.

    @package    ArtisanPack_UI
    @subpackage EcommerceKanbanLivewire

    @since      1.0.0
--}}
@php
    $ecommerceKanbanPickerUrl = \ArtisanPackUI\EcommerceKanbanLivewire\Support\BoardUrls::picker();
@endphp
<!DOCTYPE html>
{{-- The scroll padding keeps a focused control from scrolling under the sticky header (WCAG 2.2 SC 2.4.11). --}}
<html lang="{{ str_replace( '_', '-', app()->getLocale() ) }}" style="scroll-padding-top: 5rem">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield( 'title', __( 'Kanban' ) ) &middot; {{ config( 'app.name' ) }}</title>
    @apuiThemeScript
    @include( 'ecommerce-kanban::partials.vite-assets' )
    @include( 'ecommerce-kanban::partials.styles' )
    @livewireStyles
    @stack( 'styles' )
</head>
<body class="ec-kb-page antialiased">
    <a href="#ecommerce-kanban-content" class="apui-button sr-only focus:not-sr-only focus:absolute focus:start-2 focus:top-2 focus:z-50" data-variant="solid" data-color="primary" data-size="sm">
        {{ __( 'Skip to content' ) }}
    </a>

    <x-artisanpack-navbar :label="__( 'Kanban' )">
        <x-slot:brand>
            @if ( null !== $ecommerceKanbanPickerUrl )
                <a href="{{ $ecommerceKanbanPickerUrl }}" class="font-bold">{{ __( 'Kanban' ) }}</a>
            @else
                <span class="font-bold">{{ __( 'Kanban' ) }}</span>
            @endif
        </x-slot:brand>

        <x-slot:actions>
            @yield( 'header' )
            <x-artisanpack-theme-toggle variant="icon" />
        </x-slot:actions>
    </x-artisanpack-navbar>

    <main id="ecommerce-kanban-content" class="w-full p-4">
        @yield( 'content' )
    </main>

    <x-artisanpack-toaster />

    @livewireScripts
    @include( 'ecommerce-kanban::partials.rate-limit-notice' )
    @stack( 'scripts' )
</body>
</html>
