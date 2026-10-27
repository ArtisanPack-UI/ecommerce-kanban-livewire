{{--
    The board picker page.

    @package    ArtisanPack_UI
    @subpackage EcommerceKanbanLivewire

    @since      1.0.0
--}}
@extends( $ecommerceKanbanLayout )

@section( 'title', __( 'Kanban boards' ) )

@section( 'content' )
    @include( 'ecommerce-kanban::partials.page-assets' )

    <livewire:artisanpack-ecommerce-kanban-board-picker :pick="$pick" />
@endsection
