{{--
    The generic widget payload view: a soft, small design-system Badge whose
    color follows `tone`, led by the tone's icon (else the payload's `icon`),
    with a `tooltip` and a link when there is a safe `href` (http(s), mailto,
    or relative). A non-neutral tone also gets an icon and screen-reader
    text, so it is never conveyed by color alone.

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

    $tone    = WidgetViews::tone( $widget['tone'] ?? null );
    $icon    = $tone['icon'] ?? Icons::name( $widget['icon'] ?? null );
    $label   = (string) ( $widget['label'] ?? '' );
    $value   = (string) ( $widget['value'] ?? '' );
    $tooltip = filled( $widget['tooltip'] ?? null ) ? (string) $widget['tooltip'] : null;
    $href    = SafeUrl::of( $widget['href'] ?? null );
@endphp
@if ( '' !== $value )
    @php( $badgeData = [ 'widget' => $widget, 'tone' => $tone, 'icon' => $icon, 'label' => $label, 'value' => $value, 'note' => $tooltip ] )
    @if ( null !== $href && null !== $tooltip )
        <x-artisanpack-tooltip :content="$tooltip"><a href="{{ $href }}" class="ec-kb-widget-link">@include( 'ecommerce-kanban::components.widgets.partials.badge', $badgeData )</a></x-artisanpack-tooltip>
    @elseif ( null !== $href )
        <a href="{{ $href }}" class="ec-kb-widget-link">@include( 'ecommerce-kanban::components.widgets.partials.badge', $badgeData )</a>
    @elseif ( null !== $tooltip )
        <x-artisanpack-tooltip :content="$tooltip"><span class="ec-kb-widget-tip">@include( 'ecommerce-kanban::components.widgets.partials.badge', $badgeData )</span></x-artisanpack-tooltip>
    @else
        @include( 'ecommerce-kanban::components.widgets.partials.badge', $badgeData )
    @endif
@endif
