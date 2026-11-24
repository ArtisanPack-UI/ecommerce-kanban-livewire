{{--
    `x-artisanpack-ec-kb-connection`: how the board keeps up with changes
    made elsewhere (spec §7.7) — live (Echo), polling (every `poll_interval`
    seconds), offline, or off. Reads `connection` from the board's Alpine
    scope and shows one ghost Badge per state: a success dot for live, a
    warning dot for polling, an error wifi-off icon for offline and a neutral
    dot when nothing updates. Always in text, never color alone. `$seconds`
    is the poll interval.

    The Badges are `aria-hidden`: screen readers announce text changes in a
    live region, not a node being shown, so one always-present `role=status`
    span holds the current state's text (F6). Every Badge, "Live" included,
    is `x-cloak`ed until Alpine sets the state, so a board that only polls
    never flashes "Live" first.

    @package    ArtisanPack_UI
    @subpackage EcommerceKanbanLivewire

    @since      1.0.0
--}}
@props( [
    'seconds' => 0,
] )
@php
    $ecKbConnectionLabels = [
        'live'    => __( 'Live' ),
        'polling' => trans_choice( 'Updates every :count second|Updates every :count seconds', (int) $seconds, [ 'count' => (int) $seconds ] ),
        'offline' => __( 'Offline' ),
        'off'     => __( 'Not updating automatically' ),
    ];
@endphp
<span
    {{ $attributes->class( [ 'ec-kb-connection' ] ) }}
    data-ec-kb-connection
    x-bind:data-ec-kb-connection-state="connection"
>
    <span class="apui-sr-only" role="status" data-ec-kb-connection-status x-text="{{ \Illuminate\Support\Js::from( $ecKbConnectionLabels ) }}[ connection ] ?? ''"></span>
    <x-artisanpack-badge variant="ghost" color="success" dot aria-hidden="true" x-show="'live' === connection" x-cloak>{{ $ecKbConnectionLabels['live'] }}</x-artisanpack-badge>
    <x-artisanpack-badge variant="ghost" color="warning" dot aria-hidden="true" x-show="'polling' === connection" x-cloak>{{ $ecKbConnectionLabels['polling'] }}</x-artisanpack-badge>
    <x-artisanpack-badge variant="ghost" color="error" leading-icon="wifi-off" aria-hidden="true" x-show="'offline' === connection" x-cloak>{{ $ecKbConnectionLabels['offline'] }}</x-artisanpack-badge>
    <x-artisanpack-badge variant="ghost" color="neutral" dot aria-hidden="true" x-show="'off' === connection" x-cloak>{{ $ecKbConnectionLabels['off'] }}</x-artisanpack-badge>
</span>
