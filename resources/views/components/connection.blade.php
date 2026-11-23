{{--
    `x-artisanpack-ec-kb-connection`: how the board keeps up with changes
    made elsewhere (spec §7.7) — live (Echo), polling (every `poll_interval`
    seconds), or offline. Reads `connection` from the board's Alpine
    scope. Shown in text, not color alone. `$seconds` is the poll interval.

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
    <span class="ec-kb-connection-dot" aria-hidden="true"></span>
    <span x-show="'live' === connection">{{ __( 'Live' ) }}</span>
    <span x-show="'polling' === connection" x-cloak>{{ trans_choice( 'Updates every :count second|Updates every :count seconds', (int) $seconds, [ 'count' => (int) $seconds ] ) }}</span>
    <span x-show="'offline' === connection" x-cloak>{{ __( 'Offline' ) }}</span>
    <span x-show="'off' === connection" x-cloak>{{ __( 'Not updating automatically' ) }}</span>
</span>
