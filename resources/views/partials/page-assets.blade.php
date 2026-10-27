{{--
    Pushes the host's Vite build, the Livewire assets, and the toast container
    onto the layout stacks when the resolved layout ships none of them
    (cms-framework's admin layout). The standalone and ecommerce admin
    layouts include them themselves.

    @package    ArtisanPack_UI
    @subpackage EcommerceKanbanLivewire

    @since      1.0.0
--}}
@if ( $ecommerceKanbanPushesAssets ?? false )
    @push( 'styles' )
        @include( 'ecommerce-kanban::partials.vite-assets' )
        @livewireStyles
    @endpush

    @push( 'scripts' )
        <x-artisanpack-toast />
        @livewireScripts
        @include( 'ecommerce-kanban::partials.rate-limit-notice' )
    @endpush
@endif
