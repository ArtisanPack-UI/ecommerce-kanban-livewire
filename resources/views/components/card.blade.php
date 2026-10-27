{{--
    `x-artisanpack-ec-kb-card`: one card (spec §7.2, §8). Order number,
    customer, placed date, then the widgets in order, with the actions menu.

    The card is focusable and named by a summary ("Order 1042, Jane Doe,
    3 items, $124.00"). While a move is in flight the board dispatches
    `ec-kb-card-state` on `window` with `{ orderId, state }`, where `state`
    is `moving`, `reverting`, or `idle`; the card dims while moving and
    flags a move that was undone, in text as well as colour.

    `$card` is the board component's card array (see `CardPresenter`, plus
    `actions` from `CardActions`).

    @package    ArtisanPack_UI
    @subpackage EcommerceKanbanLivewire

    @since      1.0.0
--}}
@props( [
    'card',
] )
@php
    $orderId = (int) $card['order_id'];
@endphp
<article
    {{ $attributes->class( [ 'card card-compact border border-base-300 bg-base-100 transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary' ] ) }}
    tabindex="0"
    aria-label="{{ $card['label'] }}"
    data-ec-kb-card="{{ $orderId }}"
    x-data="{ state: 'idle' }"
    x-on:ec-kb-card-state.window="if ( Number( $event.detail?.orderId ) === {{ $orderId }} ) { state = $event.detail.state ?? 'idle' }"
    x-bind:class="{ 'opacity-60 animate-pulse': 'moving' === state, 'ring-2 ring-error': 'reverting' === state }"
    x-bind:aria-busy="'moving' === state ? 'true' : 'false'"
    x-bind:data-ec-kb-state="state"
>
    <div class="card-body gap-2 p-3">
        <div class="flex items-start justify-between gap-2">
            <div class="min-w-0">
                <p class="font-semibold">{{ __( 'Order :number', [ 'number' => $card['number'] ] ) }}</p>
                <p class="truncate text-sm">{{ $card['customer'] }}</p>
                @if ( '' !== $card['placed_at'] )
                    <p class="text-xs text-base-content/70">
                        <time datetime="{{ $card['placed_at_iso'] }}">{{ $card['placed_at'] }}</time>
                    </p>
                @endif
            </div>

            @if ( [] !== $card['actions'] )
                <x-artisanpack-dropdown right data-ec-kb-card-actions>
                    <x-slot:trigger class="btn btn-ghost btn-xs btn-square" aria-label="{{ __( 'Actions for order :number', [ 'number' => $card['number'] ] ) }}">
                        <x-artisanpack-icon name="o-ellipsis-vertical" class="size-4" aria-hidden="true" />
                    </x-slot:trigger>

                    @foreach ( $card['actions'] as $action )
                        @if ( null !== $action['url'] )
                            <x-artisanpack-menu-item :title="$action['label']" :icon="$action['icon']" :link="$action['url']" no-wire-navigate data-ec-kb-action="{{ $action['key'] }}" />
                        @else
                            <x-artisanpack-menu-item
                                :title="$action['label']"
                                :icon="$action['icon']"
                                x-on:click="$wire.dispatch( {{ \Illuminate\Support\Js::from( $action['event'] ) }}, { orderId: {{ $orderId }}, columnId: {{ (int) $card['column_id'] }}, boardId: $wire.boardId } )"
                                data-ec-kb-action="{{ $action['key'] }}"
                            />
                        @endif
                    @endforeach
                </x-artisanpack-dropdown>
            @endif
        </div>

        @if ( [] !== $card['widgets'] )
            <ul class="flex flex-wrap items-center gap-1" aria-label="{{ __( 'Details' ) }}">
                @foreach ( $card['widgets'] as $widget )
                    <li>
                        <x-artisanpack-ec-kb-widget :widget="$widget" />
                    </li>
                @endforeach
            </ul>
        @endif

        <p class="text-xs" role="status" x-show="'idle' !== state" x-cloak>
            <span x-show="'moving' === state">{{ __( 'Moving…' ) }}</span>
            <span x-show="'reverting' === state" class="text-error">{{ __( 'Move undone' ) }}</span>
        </p>
    </div>
</article>
