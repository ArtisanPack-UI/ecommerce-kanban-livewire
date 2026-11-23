{{--
    The generic widget's Badge: the tone icon, then the widget's own icon,
    then the value, with the label, tone and tooltip as visually hidden text.

    @package    ArtisanPack_UI
    @subpackage EcommerceKanbanLivewire

    @since      2.0.0
--}}
<x-artisanpack-badge
    variant="soft"
    size="sm"
    :color="$tone['color']"
    :classes="[ 'icon' => 'ec-kb-widget-icons' ]"
    data-ec-kb-widget="{{ $widget['key'] ?? '' }}"
    data-ec-kb-tone="{{ $widget['tone'] ?? 'neutral' }}"
    :data-ec-kb-tone-icon="null !== $tone['icon'] ? '' : null"
>
    <x-slot:icon>
        @foreach ( $icons as $badgeIcon )
            <x-artisanpack-icon :name="$badgeIcon" size="sm" />
        @endforeach
    </x-slot:icon>
    @include( 'ecommerce-kanban::components.widgets.partials.badge-text' )
</x-artisanpack-badge>
