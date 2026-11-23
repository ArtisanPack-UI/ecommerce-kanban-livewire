{{--
    The board's own inline styles, on every page, plus the host's Vite
    build, the Livewire assets, and the toaster pushed onto the layout
    stacks when the resolved layout ships none of them (cms-framework's
    admin layout). The standalone and ecommerce admin layouts include those
    themselves.

    @package    ArtisanPack_UI
    @subpackage EcommerceKanbanLivewire

    @since      1.0.0
--}}
@include( 'ecommerce-kanban::partials.styles' )

@if ( $ecommerceKanbanPushesAssets ?? false )
    @push( 'styles' )
        @include( 'ecommerce-kanban::partials.vite-assets' )
        @livewireStyles
    @endpush

    @push( 'scripts' )
        <x-artisanpack-toaster />
        @livewireScripts
        @include( 'ecommerce-kanban::partials.rate-limit-notice' )
    @endpush
@endif
