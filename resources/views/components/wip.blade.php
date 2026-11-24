{{--
    `x-artisanpack-ec-kb-wip`: a column's card count, as `n / limit` when the
    column has a WIP limit, in a soft, small design-system Badge. At or over
    the limit it turns to the warning color, adds an alert icon, and says so
    in visually hidden text, so the state is never color only.

    @package    ArtisanPack_UI
    @subpackage EcommerceKanbanLivewire

    @since      1.0.0
--}}
@props( [
    'count',
    'limit' => null,
] )
@php
    $count   = (int) $count;
    $atLimit = null !== $limit && $count >= (int) $limit;
    $text    = null === $limit
        ? trans_choice( ':count card|:count cards', $count, [ 'count' => $count ] )
        : ( $atLimit
            ? __( ':count of :limit cards, WIP limit reached', [ 'count' => $count, 'limit' => (int) $limit ] )
            : __( ':count of :limit cards', [ 'count' => $count, 'limit' => (int) $limit ] ) );
@endphp
<x-artisanpack-badge
    {{ $attributes }}
    variant="soft"
    size="sm"
    :color="$atLimit ? 'warning' : 'neutral'"
    :leading-icon="$atLimit ? 'triangle-alert' : null"
    data-ec-kb-wip
    :data-ec-kb-wip-reached="$atLimit ? '' : null"
><span aria-hidden="true">{{ null === $limit ? $count : $count . ' / ' . (int) $limit }}</span><span class="apui-sr-only">{{ $text }}</span></x-artisanpack-badge>
