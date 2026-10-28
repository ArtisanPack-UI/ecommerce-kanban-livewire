{{--
    One card as a column lists it: with the drag-and-drop item attributes
    and a grab cursor when `$draggable`. Shared by the column and the board's single-card
    refresh (`refreshCards()`), so a refreshed card renders exactly as the
    column would render it.

    @package    ArtisanPack_UI
    @subpackage EcommerceKanbanLivewire

    @since      1.0.0
--}}
@if ( $draggable )
    <x-artisanpack-ec-kb-card
        :card="$card"
        class="cursor-grab active:cursor-grabbing"
        wire:key="card-{{ $card['order_id'] }}"
        x-drag-item="{{ (int) $card['order_id'] }}"
        data-drag-label="{{ __( 'Order :number', [ 'number' => $card['number'] ] ) }}"
    />
@else
    <x-artisanpack-ec-kb-card :card="$card" wire:key="card-{{ $card['order_id'] }}" />
@endif
