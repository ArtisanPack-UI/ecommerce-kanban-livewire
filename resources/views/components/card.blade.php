{{--
    `x-artisanpack-ec-kb-card`: one card (spec §7.2, §8; #44). Order number,
    customer, placed date, then the widgets in order, with the actions menu.

    A token-only surface (raised, muted hairline, medium radius, no shadow;
    see resources/css/kanban.css) holding design-system parts: soft Badges
    for the widgets and a DropdownMenu for the actions.

    The card is a focusable group named by a summary ("Order 1042, Jane
    Doe, 3 items, $124.00"). It is a `div`: `role="group"` isn't allowed on
    an `article`. While a move is in flight the board dispatches
    `ec-kb-card-state` on `window` with `{ orderId, state }`, where `state`
    is `moving`, `reverting`, or `idle`; the card steps onto the sunken
    surface with a dashed edge and shows a spinner while moving (at full
    opacity, so its text keeps AA contrast) and gets an error edge after a
    move was undone, each with a visible line of text, so the state is never
    shown by color alone. That line is `aria-hidden`: livewire-drag-and-drop
    announces the drop and the revert, and the card carries `aria-busy`.

    With `preview` (the widget picker's preview), the card is a plain group:
    no tab stop, no `data-ec-kb-card` and no move-state wiring, so it never
    stands in for the board's copy of the card.

    `$card` is the board component's card array (see `CardPresenter`, plus
    `actions` from `CardActions`); `classes` from the
    `ap.ecommerceKanbanLivewire.cardClasses` filter are added to the card.
    On a board the column passes the drag-and-drop attributes
    (`x-drag-item`, `data-drag-label`, `data-ec-kb-draggable` for the grab
    cursor) through `$attributes`; the explicit `role` keeps
    livewire-drag-and-drop from making the card a button, since it holds the
    actions menu.

    The actions menu lists Open, Move to…, the satellites' actions, then a
    separator and Remove from board in the error color. Menu items report a
    `select` with their key: an action with an event dispatches it to the
    board's Livewire component, an action with only a URL follows it. An
    action with both follows its URL in a new tab when the item was chosen
    with Ctrl, Cmd, Shift or Alt held (the board records the modifiers in
    `window.ecommerceKanbanModifiedAt`, since the menu's `select` carries
    only the value), and dispatches the event otherwise.

    @package    ArtisanPack_UI
    @subpackage EcommerceKanbanLivewire

    @since      1.0.0
--}}
@props( [
    'card',
    'preview' => false,
] )
@php
    $orderId     = (int) $card['order_id'];
    // Explicit ids for the parts the component library would otherwise hash
    // from their props: a single-card or single-column refresh renders
    // without the board's id claims, so a tooltip with the same text as one
    // on another card would get that card's id (F10). The preview has its
    // own prefix, since it shows a card that is also on the board.
    $partPrefix  = ( $preview ? 'ec-kb-preview-' : 'ec-kb-card-' ) . $orderId;
    $columnId    = (int) $card['column_id'];
    $actions     = $card['actions'] ?? [];
    $menuActions = array_values( array_filter( $actions, static fn ( array $action ): bool => 'remove' !== $action['key'] ) );
    $remove      = array_values( array_filter( $actions, static fn ( array $action ): bool => 'remove' === $action['key'] ) )[0] ?? null;
    $targets     = [];

    foreach ( $actions as $action ) {
        $targets[ $action['key'] ] = [ 'url' => $action['url'], 'event' => $action['event'] ];
    }

    // The number sits in the mono face; the sentence around it stays translatable.
    $orderLine = str_replace(
        e( '__EC_KB_NUMBER__' ),
        '<span class="ec-kb-card-number">' . e( $card['number'] ) . '</span>',
        e( __( 'Order :number', [ 'number' => '__EC_KB_NUMBER__' ] ) ),
    );
@endphp
<div
    {{ $attributes->class( [ 'ec-kb-card', ...( $card['classes'] ?? [] ) ] ) }}
    @unless ( $preview ) tabindex="0" @endunless
    role="group"
    aria-label="{{ $card['label'] }}"
    @if ( $preview )
        data-ec-kb-card-preview="{{ $orderId }}"
    @else
        data-ec-kb-card="{{ $orderId }}"
        data-ec-kb-state="idle"
        x-data="{ state: 'idle' }"
        x-on:ec-kb-card-state.window="if ( Number( $event.detail?.orderId ) === {{ $orderId }} ) { state = $event.detail.state ?? 'idle' }"
        x-bind:aria-busy="'moving' === state ? 'true' : 'false'"
        x-bind:data-ec-kb-state="state"
    @endif
>
    <div class="ec-kb-card-header">
        <div class="ec-kb-card-summary">
            <p class="ec-kb-card-order">{!! $orderLine !!}</p>
            <p class="ec-kb-card-customer">{{ $card['customer'] }}</p>
            @if ( '' !== $card['placed_at'] )
                <p class="ec-kb-card-date">
                    <time datetime="{{ $card['placed_at_iso'] }}">{{ $card['placed_at'] }}</time>
                </p>
            @endif
        </div>

        @if ( [] !== $actions )
            <x-artisanpack-dropdown-menu
                :id="$partPrefix . '-actions'"
                align="end"
                :classes="[ 'trigger' => 'ec-kb-card-actions-trigger' ]"
                data-ec-kb-card-actions
                data-ec-kb-targets="{{ json_encode( $targets, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) }}"
                x-on:select="const target = JSON.parse( $el.dataset.ecKbTargets )[ $event.detail.value ]; const modified = Date.now() - ( window.ecommerceKanbanModifiedAt ?? 0 ) < 1000; if ( target && null !== target.url && null !== target.event && modified ) { window.open( target.url, '_blank', 'noopener' ) } else if ( target && null !== target.event ) { $wire.dispatch( target.event, { orderId: {{ $orderId }}, columnId: {{ $columnId }}, boardId: $wire.boardId } ) } else if ( target && null !== target.url ) { window.location.assign( target.url ) }"
            >
                <x-slot:label>
                    <x-artisanpack-icon name="o-ellipsis-vertical" />
                    <span class="apui-sr-only">{{ __( 'Actions for order :number', [ 'number' => $card['number'] ] ) }}</span>
                </x-slot:label>

                @foreach ( $menuActions as $action )
                    <x-artisanpack-dropdown-menu-item :value="$action['key']" :icon="$action['icon']" data-ec-kb-action="{{ $action['key'] }}">{{ $action['label'] }}</x-artisanpack-dropdown-menu-item>
                @endforeach

                @if ( null !== $remove )
                    @if ( [] !== $menuActions )
                        <x-artisanpack-dropdown-menu-separator />
                    @endif
                    <x-artisanpack-dropdown-menu-item :value="$remove['key']" :icon="$remove['icon']" color="error" data-ec-kb-action="{{ $remove['key'] }}">{{ $remove['label'] }}</x-artisanpack-dropdown-menu-item>
                @endif
            </x-artisanpack-dropdown-menu>
        @endif
    </div>

    @if ( [] !== $card['widgets'] )
        <ul class="ec-kb-card-widgets" aria-label="{{ __( 'Details' ) }}">
            @foreach ( $card['widgets'] as $widget )
                <li>
                    <x-artisanpack-ec-kb-widget :widget="[ ...$widget, 'id_prefix' => $partPrefix ]" />
                </li>
            @endforeach
        </ul>
    @endif

    @unless ( $preview )
        <p class="ec-kb-card-state" aria-hidden="true" x-bind:data-ec-kb-tone="'reverting' === state ? 'error' : null" x-show="'idle' !== state" x-cloak data-ec-kb-card-state>
            <span class="ec-kb-spinner" x-show="'moving' === state"></span>
            <span x-show="'moving' === state">{{ __( 'Moving…' ) }}</span>
            <x-artisanpack-icon name="undo-2" :mirror="true" x-show="'reverting' === state" />
            <span x-show="'reverting' === state">{{ __( 'Move undone' ) }}</span>
        </p>
    @endunless
</div>
