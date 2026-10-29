{{--
    Loads the host's Vite entries (the Tailwind build that styles the board),
    filterable with `ap.ecommerceKanbanLivewire.layout.viteEntries`; return an
    empty array to load nothing. Skipped when there is neither a Vite build
    nor a running dev server.

    @package    ArtisanPack_UI
    @subpackage EcommerceKanbanLivewire

    @since      1.0.0
--}}
@php
    $ecommerceKanbanViteEntries = (array) applyFilters( 'ap.ecommerceKanbanLivewire.layout.viteEntries', [ 'resources/css/app.css', 'resources/js/app.js' ] );
    $ecommerceKanbanLoadsVite   = [] !== $ecommerceKanbanViteEntries
        && ( \Illuminate\Support\Facades\Vite::isRunningHot() || is_file( public_path( 'build/manifest.json' ) ) );
@endphp
@if ( $ecommerceKanbanLoadsVite )
    @vite( $ecommerceKanbanViteEntries )
@endif
