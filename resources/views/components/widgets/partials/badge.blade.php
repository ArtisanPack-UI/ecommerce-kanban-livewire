{{--
    The generic widget's Badge: the icon, the value, and the label, tone and
    tooltip as visually hidden text.

    @package    ArtisanPack_UI
    @subpackage EcommerceKanbanLivewire

    @since      2.0.0
--}}
<x-artisanpack-badge
    variant="soft"
    size="sm"
    :color="$tone['color']"
    :leading-icon="$icon"
    data-ec-kb-widget="{{ $widget['key'] ?? '' }}"
    data-ec-kb-tone="{{ $widget['tone'] ?? 'neutral' }}"
    :data-ec-kb-tone-icon="null !== $tone['icon'] ? '' : null"
>@if ( '' !== $label )<span class="apui-sr-only">{{ $label }}:</span> @endif{{ $value }}@if ( null !== $tone['text'] )<span class="apui-sr-only"> ({{ $tone['text'] }})</span>@endif @if ( null !== $note )<span class="apui-sr-only"> ({{ $note }})</span>@endif</x-artisanpack-badge>
