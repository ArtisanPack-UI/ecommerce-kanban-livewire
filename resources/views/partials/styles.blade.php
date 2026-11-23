{{--
    Inlines the board's own stylesheet (resources/css/kanban.css): the
    token-only styles the design system has no component for, such as the
    card, the column and the filter bar. Inlined, so a host needs no build
    step for it. Once per page; the standalone layout puts it in `<head>` and
    `partials.page-assets` pushes it for layouts that ship no assets.

    @package    ArtisanPack_UI
    @subpackage EcommerceKanbanLivewire

    @since      2.0.0
--}}
@once
    @php( $ecommerceKanbanStylesNonce = \Illuminate\Support\Facades\Vite::cspNonce() )
    <style data-ecommerce-kanban-styles @if ( null !== $ecommerceKanbanStylesNonce && '' !== $ecommerceKanbanStylesNonce ) nonce="{{ $ecommerceKanbanStylesNonce }}" @endif>{!! \ArtisanPackUI\EcommerceKanbanLivewire\Support\KanbanStyles::css() !!}</style>
@endonce
