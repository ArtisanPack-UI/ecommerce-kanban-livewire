{{--
    `x-artisanpack-ec-kb-connection`: how the board keeps up with changes
    made elsewhere (spec §7.7) — live (Echo), polling (every `poll_interval`
    seconds), offline, or off. Reads `connection` from the board's Alpine
    scope and shows one ghost Badge per state: a success dot for live, a
    warning dot for polling, an error wifi-off icon for offline and a neutral
    dot when nothing updates. Always in text, never color alone. `$seconds`
    is the poll interval.

    @package    ArtisanPack_UI
    @subpackage EcommerceKanbanLivewire

    @since      1.0.0
--}}
@props( [
    'seconds' => 0,
] )
<span
    {{ $attributes->class( [ 'ec-kb-connection' ] ) }}
    role="status"
    data-ec-kb-connection
    x-bind:data-ec-kb-connection-state="connection"
>
    <x-artisanpack-badge variant="ghost" color="success" dot x-show="'live' === connection">{{ __( 'Live' ) }}</x-artisanpack-badge>
    <x-artisanpack-badge variant="ghost" color="warning" dot x-show="'polling' === connection" x-cloak>{{ trans_choice( 'Updates every :count second|Updates every :count seconds', (int) $seconds, [ 'count' => (int) $seconds ] ) }}</x-artisanpack-badge>
    <x-artisanpack-badge variant="ghost" color="error" leading-icon="wifi-off" x-show="'offline' === connection" x-cloak>{{ __( 'Offline' ) }}</x-artisanpack-badge>
    <x-artisanpack-badge variant="ghost" color="neutral" dot x-show="'off' === connection" x-cloak>{{ __( 'Not updating automatically' ) }}</x-artisanpack-badge>
</span>
