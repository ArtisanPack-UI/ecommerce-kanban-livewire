{{--
    A board page. The board needs the full width, so the page doesn't sit in
    a narrow content column even under an admin layout.

    @package    ArtisanPack_UI
    @subpackage EcommerceKanbanLivewire

    @since      1.0.0
--}}
@extends( $ecommerceKanbanLayout )

@section( 'title', $board->name )

@section( 'content' )
    @include( 'ecommerce-kanban::partials.page-assets' )

    <div class="w-full max-w-none" data-ecommerce-kanban-page>
        <livewire:artisanpack-ecommerce-kanban-board :board="$board" />
    </div>
@endsection
