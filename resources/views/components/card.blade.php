{{--
    `x-artisanpack-ec-kb-card`: one card (spec §7.2, §8). Order number,
    customer, placed date, then the widgets in order, with the actions menu.

    The card is a focusable group named by a summary ("Order 1042, Jane
    Doe, 3 items, $124.00"). It is a `div`: `role="group"` isn't allowed on
    an `article`. While a move is in flight the board dispatches
    `ec-kb-card-state` on `window` with `{ orderId, state }`, where `state`
    is `moving`, `reverting`, or `idle`; the card dims while moving and
    flags a move that was undone, in text as well as colour.

    `$card` is the board component's card array (see `CardPresenter`, plus
    `actions` from `CardActions`); `classes` from the
    `ap.ecommerceKanbanLivewire.cardClasses` filter are added to the card. On a board the column passes the
    drag-and-drop attributes (`x-drag-item`, `data-drag-label`) through
    `$attributes`; the explicit `role` keeps livewire-drag-and-drop from
    making the card a button, since it holds the actions menu.

    An action with both a URL and an event dispatches the event on a plain
    click and follows the link on a modified click (new tab). Link clicks
    stop at the item: the dropdown's own click handler cancels the default
    action of every click inside it, which would otherwise block the link
    (livewire-ui-components#134).

    Only opacity and the ring transition: livewire-drag-and-drop moves its
    floating copy of the card with `transform`, and a transform transition
    would make that copy trail the pointer. Neither transitions nor pulses
    when the user asks for reduced motion.

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
<div
    {{ $attributes->class( [ 'card card-compact border border-base-300 bg-base-100 transition-[opacity,box-shadow] motion-reduce:transition-none focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary', ...( $card['classes'] ?? [] ) ] ) }}
    tabindex="0"
    role="group"
    aria-label="{{ $card['label'] }}"
    data-ec-kb-card="{{ $orderId }}"
    x-data="{ state: 'idle' }"
    x-on:ec-kb-card-state.window="if ( Number( $event.detail?.orderId ) === {{ $orderId }} ) { state = $event.detail.state ?? 'idle' }"
    x-bind:class="{ 'opacity-60 animate-pulse motion-reduce:animate-none': 'moving' === state, 'ring-2 ring-error': 'reverting' === state }"
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
                        @if ( null !== $action['url'] && null !== $action['event'] )
                            <x-artisanpack-menu-item
                                :title="$action['label']"
                                :icon="$action['icon']"
                                :link="$action['url']"
                                no-wire-navigate
                                x-on:click="$event.stopPropagation(); if ( ! ( $event.metaKey || $event.ctrlKey || $event.shiftKey || $event.altKey ) ) { $event.preventDefault(); $wire.dispatch( {{ \Illuminate\Support\Js::from( $action['event'] ) }}, { orderId: {{ $orderId }}, columnId: {{ (int) $card['column_id'] }}, boardId: $wire.boardId } ) }"
                                data-ec-kb-action="{{ $action['key'] }}"
                            />
                        @elseif ( null !== $action['url'] )
                            <x-artisanpack-menu-item :title="$action['label']" :icon="$action['icon']" :link="$action['url']" no-wire-navigate x-on:click.stop data-ec-kb-action="{{ $action['key'] }}" />
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
</div>
