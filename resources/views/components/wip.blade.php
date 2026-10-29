{{--
    `x-artisanpack-ec-kb-wip`: a column's card count, as `n / limit` when the
    column has a WIP limit. At or over the limit it turns to the warning tone,
    adds an icon, and says so in text, so the state is never colour only.

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
<span
    {{ $attributes->class( [ 'badge badge-sm gap-1 whitespace-nowrap', 'badge-warning' => $atLimit, 'badge-ghost' => ! $atLimit ] ) }}
    data-ec-kb-wip
    @if ( $atLimit ) data-ec-kb-wip-reached @endif
>
    @if ( $atLimit )
        <x-artisanpack-icon name="o-exclamation-triangle" class="size-3" aria-hidden="true" />
    @endif
    <span aria-hidden="true">{{ null === $limit ? $count : $count . ' / ' . (int) $limit }}</span>
    <span class="sr-only">{{ $text }}</span>
</span>
