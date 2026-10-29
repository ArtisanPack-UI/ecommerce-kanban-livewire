{{--
    One column as the board lists it outside "Edit board" mode. Shared by
    the board and its column refreshes (`columnsHtml()`: after a drop, a
    real-time event, a poll, or a lazy load), so a refreshed column renders
    exactly as the board would render it.

    @package    ArtisanPack_UI
    @subpackage EcommerceKanbanLivewire

    @since      1.0.0
--}}
<x-artisanpack-ec-kb-column
    :column="$column"
    :loaded="$column['loaded']"
    :draggable="$draggable"
    :drag-messages="$dragMessages"
    wire:key="column-{{ $column['id'] }}"
/>
