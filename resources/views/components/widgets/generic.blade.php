{{--
    The generic widget payload view: a badge whose colour follows `tone`,
    with the payload's `icon`, a `tooltip`, and a link when there is a
    safe `href` (http(s), mailto, or relative). A non-neutral tone also gets
    an icon and screen-reader text, so it is never conveyed by colour alone.
    The neutral tone is a plain outline in the text colour: daisyUI's
    `badge-neutral` outline is dark grey, which fails contrast in dark mode.

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
    $icon    = Icons::name( $widget['icon'] ?? null );
    $label   = (string) ( $widget['label'] ?? '' );
    $value   = (string) ( $widget['value'] ?? '' );
    $tooltip = filled( $widget['tooltip'] ?? null ) ? (string) $widget['tooltip'] : null;
    $href    = SafeUrl::of( $widget['href'] ?? null );
    $tag     = null !== $href ? 'a' : 'span';
@endphp
@if ( '' !== $value )
    <{{ $tag }}
        @if ( null !== $href ) href="{{ $href }}" @endif
        @class( [ 'badge badge-sm gap-1 whitespace-nowrap', 'badge-' . $tone['color'] => 'neutral' !== $tone['color'], 'badge-outline' => 'neutral' === $tone['color'], 'tooltip' => null !== $tooltip, 'link link-hover' => null !== $href ] )
        @if ( null !== $tooltip ) data-tip="{{ $tooltip }}" @endif
        data-ec-kb-widget="{{ $widget['key'] ?? '' }}"
        data-ec-kb-tone="{{ $widget['tone'] ?? 'neutral' }}"
    >
        @if ( null !== $tone['icon'] )
            <x-artisanpack-icon :name="$tone['icon']" class="size-3" aria-hidden="true" data-ec-kb-tone-icon />
        @endif
        @if ( null !== $icon )
            <x-artisanpack-icon :name="$icon" class="size-3" aria-hidden="true" />
        @endif
        @if ( '' !== $label )
            <span class="sr-only">{{ $label }}:</span>
        @endif
        <span>{{ $value }}</span>
        @if ( null !== $tone['text'] )
            <span class="sr-only">({{ $tone['text'] }})</span>
        @endif
        @if ( null !== $tooltip )
            <span class="sr-only">({{ $tooltip }})</span>
        @endif
    </{{ $tag }}>
@endif
