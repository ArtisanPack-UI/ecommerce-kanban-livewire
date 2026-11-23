{{--
    The generic widget payload view: a soft, small design-system Badge whose
    color follows `tone`, led by the tone's icon and then the payload's own
    `icon`, with a `tooltip` and an underlined link when there is a safe
    `href` (http(s), mailto, or relative). A non-neutral tone also gets
    screen-reader text, so it is never conveyed by color alone. Tags (see
    `WidgetViews::TAG_KEYS`) read in the info color with no tone icon.

    A badge with a tooltip is focusable, so keyboard users can open it; the
    tooltip's text is also part of the badge's name.

    Receives `$widget`: `key`, `label`, `value`, `tone`, and optionally
    `icon`, `tooltip`, `href`.

    @package    ArtisanPack_UI
    @subpackage EcommerceKanbanLivewire

    @since      1.0.0
--}}
@php
    use ArtisanPackUI\EcommerceKanbanLivewire\Support\Icons;
    use ArtisanPackUI\EcommerceKanbanLivewire\Support\SafeUrl;
    use ArtisanPackUI\EcommerceKanbanLivewire\Support\WidgetViews;

    $tone    = WidgetViews::tone( $widget['tone'] ?? null, $widget['key'] ?? null );
    $icons   = array_values( array_unique( array_filter( [ $tone['icon'], Icons::name( $widget['icon'] ?? null ) ] ) ) );
    $label   = (string) ( $widget['label'] ?? '' );
    $value   = (string) ( $widget['value'] ?? '' );
    $tooltip = filled( $widget['tooltip'] ?? null ) ? (string) $widget['tooltip'] : null;
    $href    = SafeUrl::of( $widget['href'] ?? null );
@endphp
@if ( '' !== $value )
    @php( $badgeData = [ 'widget' => $widget, 'tone' => $tone, 'icons' => $icons, 'label' => $label, 'value' => $value, 'note' => $tooltip ] )
    @if ( null !== $href && null !== $tooltip )
        <x-artisanpack-tooltip :content="$tooltip"><a href="{{ $href }}" class="ec-kb-widget-link">@include( 'ecommerce-kanban::components.widgets.partials.badge', $badgeData )</a></x-artisanpack-tooltip>
    @elseif ( null !== $href )
        <a href="{{ $href }}" class="ec-kb-widget-link">@include( 'ecommerce-kanban::components.widgets.partials.badge', $badgeData )</a>
    @elseif ( null !== $tooltip )
        <x-artisanpack-tooltip :content="$tooltip"><span class="ec-kb-widget-tip" tabindex="0">@include( 'ecommerce-kanban::components.widgets.partials.badge', $badgeData )</span></x-artisanpack-tooltip>
    @else
        @include( 'ecommerce-kanban::components.widgets.partials.badge', $badgeData )
    @endif
@endif
