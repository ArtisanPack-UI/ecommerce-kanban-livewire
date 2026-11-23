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

    The skip link is the first tab stop, held off-canvas until focused, and
    the page keeps a scroll padding the height of the sticky Navbar so focus
    never lands under it (WCAG 2.2 SC 2.4.11).

    Publish with `php artisan vendor:publish --tag=ecommerce-kanban-views`.

    @package    ArtisanPack_UI
    @subpackage EcommerceKanbanLivewire

    @since      1.0.0
--}}
@php
    $ecommerceKanbanPickerUrl = \ArtisanPackUI\EcommerceKanbanLivewire\Support\BoardUrls::picker();
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace( '_', '-', app()->getLocale() ) }}" class="ec-kb-standalone">
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
<body class="ec-kb-page">
    <x-artisanpack-button :href="'#ecommerce-kanban-content'" size="sm" class="ec-kb-skip-link" data-ec-kb-skip-link>{{ __( 'Skip to content' ) }}</x-artisanpack-button>

    <x-artisanpack-navbar :label="__( 'Kanban' )" class="ec-kb-standalone-nav">
        <x-slot:brand>
            @if ( null !== $ecommerceKanbanPickerUrl )
                <a href="{{ $ecommerceKanbanPickerUrl }}" class="ec-kb-brand" data-ec-kb-brand>
                    <x-artisanpack-icon name="o-view-columns" variant="tile" class="ec-kb-brand-mark" />
                    <span>{{ __( 'Kanban' ) }}</span>
                </a>
            @else
                <span class="ec-kb-brand" data-ec-kb-brand>
                    <x-artisanpack-icon name="o-view-columns" variant="tile" class="ec-kb-brand-mark" />
                    <span>{{ __( 'Kanban' ) }}</span>
                </span>
            @endif
        </x-slot:brand>

        <x-slot:actions>
            @yield( 'header' )
            <x-artisanpack-theme-toggle variant="icon" size="sm" />
        </x-slot:actions>
    </x-artisanpack-navbar>

    <main id="ecommerce-kanban-content" class="ec-kb-standalone-main" tabindex="-1">
        @yield( 'content' )
    </main>

    <x-artisanpack-toaster />

    @livewireScripts
    @include( 'ecommerce-kanban::partials.rate-limit-notice' )
    @stack( 'scripts' )
</body>
</html>
