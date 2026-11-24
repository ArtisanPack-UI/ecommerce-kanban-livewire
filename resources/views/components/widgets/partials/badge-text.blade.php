{{--
    The generic widget Badge's text: the label, then the value, then the tone
    and tooltip, with everything but the value visually hidden.

    @package    ArtisanPack_UI
    @subpackage EcommerceKanbanLivewire

    @since      2.0.0
--}}
@if ( '' !== $label )<span class="apui-sr-only">{{ $label }}:</span> @endif{{ $value }}@if ( null !== $tone['text'] )<span class="apui-sr-only"> ({{ $tone['text'] }})</span>@endif @if ( null !== $note )<span class="apui-sr-only"> ({{ $note }})</span>@endif
