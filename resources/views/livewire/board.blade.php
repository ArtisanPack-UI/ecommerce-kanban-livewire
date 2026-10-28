{{--
    A board (spec §7.2): a header with the board switcher, the filter bar,
    then the columns left to right in a horizontally scrolling strip.

    The collapsed columns are remembered per board in `localStorage` under
    `$storageKey`; the first render draws only the column headers, then
    `loadBoard()` loads the cards of the columns left open.

    When the user may move cards, each column's card list is a
    livewire-drag-and-drop context (spec §5.5). `drop()` refuses a drop
    into a column at its WIP limit before anything moves (the engine still
    decides); `end()` sends the move to `moveCard()` with the cards the card
    landed between, keeps the card where it was dropped while the request
    runs, and reverts that move (by its `moveId`) when the engine refuses.
    Every card also has a "Move to…" dialog, the single-pointer alternative
    to dragging (D10).

    The switcher's links stop their clicks at the item: the dropdown's own
    click handler cancels every click inside it, which blocks navigation
    (livewire-ui-components#134).

    Real-time (spec §7.7): with `$realtime['echo']` and `window.Echo`, the
    board listens for the engine's `kanbanBoardEvent` on the private board
    channel and leaves it on navigation. `remote()` ignores the echo of the
    user's own in-flight move (`actor_user_id`), cancels a drag whose card a
    remote change moved, batches `card-updated` events so each card
    re-renders once (`refreshCards()`, morphed in place), and sends the rest
    to `boardEvent()`. Without Echo it polls every `$realtime['poll']`
    seconds, skipping while a card is dragged, a move is in flight, or the
    tab is hidden, and polls at once when the tab is shown again. `x-artisanpack-ec-kb-connection` shows which applies.

    In "Edit board" mode the column strip is an outer drag context (group
    `columns`): a header drop calls `reorderColumns()` and is reverted when
    refused. Cards don't drag in edit mode.

    @package    ArtisanPack_UI
    @subpackage EcommerceKanbanLivewire

    @since      1.0.0
--}}
@php
    use ArtisanPackUI\EcommerceKanbanLivewire\Support\BoardUrls;
@endphp
<div
    class="flex flex-col gap-4"
    data-ec-kb-board-view="{{ $board->id }}"
    x-data="{
        storageKey: @js( $storageKey ),
        boardId: {{ (int) $board->id }},
        realtime: @js( $realtime ),
        connection: 'off',
        channel: null,
        onState: null,
        pollTimer: null,
        dragging: null,
        inFlight: {},
        pendingCards: new Set(),
        cardTimer: null,
        announcement: '',
        remoteMoveMessage: @js( __( 'This card was moved by someone else, so your move was canceled.' ) ),
        read() {
            try {
                const value = JSON.parse( window.localStorage.getItem( this.storageKey ) ?? '[]' );

                return Array.isArray( value ) ? value : [];
            } catch ( error ) {
                return [];
            }
        },
        write( ids ) {
            try {
                window.localStorage.setItem( this.storageKey, JSON.stringify( ids ) );
            } catch ( error ) {
                // Storage is unavailable (private mode); collapsing still works for this visit.
            }
        },
        init() {
            this.connect();

            this.$wire.loadBoard( this.read() ).then( () => {
                this.$wire.$watch( 'collapsed', ( ids ) => this.write( ids ) );

                const card = new URLSearchParams( window.location.search ).get( 'card' );

                if ( null !== card ) {
                    this.$nextTick( () => this.card( card )?.scrollIntoView( { block: 'nearest', inline: 'nearest' } ) );
                }
            } );
        },
        destroy() {
            this.disconnect();
        },
        connect() {
            this.onNetwork    = () => this.updateConnection();
            this.onVisibility = () => this.pollNow();
            window.addEventListener( 'online', this.onNetwork );
            window.addEventListener( 'offline', this.onNetwork );
            document.addEventListener( 'visibilitychange', this.onVisibility );
            document.addEventListener( 'livewire:navigating', () => this.disconnect(), { once: true } );

            if ( this.realtime.echo && window.Echo ) {
                try {
                    this.channel = window.Echo.private( this.realtime.channel );
                    this.channel.listen( this.realtime.event, ( event ) => this.remote( event ) );
                    this.channel.error?.( () => {
                        window.Echo.leave( this.realtime.channel );
                        this.channel = null;
                        this.updateConnection();
                    } );
                    this.onState = () => this.updateConnection();
                    window.Echo.connector?.pusher?.connection?.bind?.( 'state_change', this.onState );
                } catch ( error ) {
                    this.channel = null;
                }
            }

            this.updateConnection();
        },
        disconnect() {
            if ( null !== this.channel ) {
                window.Echo?.leave( this.realtime.channel );
                this.channel = null;
            }

            if ( this.onState ) {
                window.Echo?.connector?.pusher?.connection?.unbind?.( 'state_change', this.onState );
                this.onState = null;
            }

            clearInterval( this.pollTimer );
            window.removeEventListener( 'online', this.onNetwork );
            window.removeEventListener( 'offline', this.onNetwork );
            document.removeEventListener( 'visibilitychange', this.onVisibility );
        },
        pollNow() {
            if ( 'polling' === this.connection && null === this.dragging && ! document.hidden && navigator.onLine && 0 === Object.keys( this.inFlight ).length ) {
                this.$wire.poll();
            }
        },
        updateConnection() {
            const state = window.Echo?.connector?.pusher?.connection?.state;

            if ( ! navigator.onLine ) {
                this.connection = 'offline';
            } else if ( null !== this.channel && ( undefined === state || 'connected' === state ) ) {
                this.connection = 'live';
            } else {
                this.connection = this.realtime.poll > 0 ? 'polling' : 'off';
            }

            clearInterval( this.pollTimer );

            if ( 'polling' === this.connection ) {
                this.pollTimer = setInterval( () => this.pollNow(), this.realtime.poll * 1000 );
            }
        },
        remote( event ) {
            if ( ! event || Number( event.board_id ) !== this.boardId ) {
                return;
            }

            const orderId = null === event.order_id || undefined === event.order_id ? null : Number( event.order_id );

            if ( null !== orderId && this.inFlight[ orderId ] && null !== this.realtime.user && Number( event.actor_user_id ) === this.realtime.user ) {
                return;
            }

            if ( null !== orderId && this.dragging === orderId && [ 'card-moved', 'card-reordered', 'card-removed' ].includes( event.type ) ) {
                this.cancelDrag();
            }

            if ( 'card-updated' === event.type ) {
                if ( null !== orderId ) {
                    this.queueCard( orderId );
                }

                return;
            }

            if ( [ 'automation-fired', 'automation-failed' ].includes( event.type ) ) {
                return;
            }

            this.$wire.boardEvent( event.type );
        },
        cancelDrag() {
            this.card( this.dragging )?.dispatchEvent( new KeyboardEvent( 'keydown', { key: 'Escape', bubbles: true } ) );
            this.dragging     = null;
            this.announcement = '';
            this.$nextTick( () => this.announcement = this.remoteMoveMessage );
        },
        queueCard( orderId ) {
            this.pendingCards.add( orderId );
            clearTimeout( this.cardTimer );
            this.cardTimer = setTimeout( () => this.flushCards(), 300 );
        },
        flushCards() {
            const ids = Array.from( this.pendingCards );

            this.pendingCards.clear();

            if ( 0 === ids.length ) {
                return;
            }

            this.$wire.refreshCards( ids ).then( ( result ) => {
                if ( result?.refresh || ! window.Alpine?.morph ) {
                    return this.$wire.$refresh();
                }

                Object.entries( result?.cards ?? {} ).forEach( ( [ orderId, html ] ) => {
                    const card = this.card( orderId );

                    if ( card && Number( orderId ) !== this.dragging ) {
                        window.Alpine.morph( card, html );
                    }
                } );
            } );
        },
        dragStarted( event, list ) {
            if ( event.detail.fromElement === list || event.detail.fromContext === list.dataset.dragContextId ) {
                this.dragging = Number( event.detail.itemId );
            }
        },
        dragStopped() {
            this.dragging = null;
        },
        endColumns( event, strip ) {
            const detail = event.detail;

            if ( detail.toElement !== strip || detail.fromIndex === detail.toIndex ) {
                return;
            }

            this.$wire.reorderColumns( detail.toOrderedIds.map( Number ) ).then( ( result ) => {
                if ( 'rejected' === result ) {
                    window.LivewireDragAndDrop?.revert( detail.moveId );
                    this.$wire.$refresh();
                }
            } );
        },
        focusColumn( detail ) {
            setTimeout( () => {
                const column = this.$root.querySelector( '[data-ec-kb-column=\'' + Number( detail.columnId ) + '\']' );
                const button = column?.querySelector( '[data-ec-kb-move-column=\'' + detail.direction + '\']:not([disabled])' ) ?? column?.querySelector( '[data-ec-kb-move-column]:not([disabled])' );

                button?.focus();
            }, 50 );
        },
        card( orderId ) {
            return Array.from( this.$root.querySelectorAll( '[data-ec-kb-card]' ) ).find( ( card ) => Number( card.dataset.ecKbCard ) === Number( orderId ) );
        },
        focusCard( orderId ) {
            setTimeout( () => this.card( orderId )?.focus(), 50 );
        },
        state( orderId, state ) {
            window.dispatchEvent( new CustomEvent( 'ec-kb-card-state', { detail: { orderId: Number( orderId ), state } } ) );
        },
        drop( event, list ) {
            const detail = event.detail;
            const limit  = parseInt( list.dataset.ecKbLimit, 10 );

            if ( detail.toElement !== list || detail.fromContext === detail.toContext || Number.isNaN( limit ) ) {
                return;
            }

            if ( Number( list.dataset.ecKbTotal ) >= limit ) {
                detail.reject( list.dataset.ecKbFull );
            }
        },
        end( event, list ) {
            const detail = event.detail;

            if ( detail.toElement === list || detail.fromElement === list ) {
                this.dragging = null;
            }

            if ( detail.toElement !== list || ( detail.fromContext === detail.toContext && detail.fromIndex === detail.toIndex ) ) {
                return;
            }

            const ids    = detail.toOrderedIds.map( Number );
            const at     = ids.indexOf( Number( detail.itemId ) );
            const after  = at > 0 ? ids[ at - 1 ] : null;
            const before = at < ids.length - 1 ? ids[ at + 1 ] : null;
            const orderId = Number( detail.itemId );
            const settle  = () => setTimeout( () => delete this.inFlight[ orderId ], 3000 );
            const revert  = () => {
                window.LivewireDragAndDrop?.revert( detail.moveId );
                this.state( detail.itemId, 'reverting' );
                setTimeout( () => this.state( detail.itemId, 'idle' ), 2000 );
            };

            this.inFlight[ orderId ] = true;
            this.state( detail.itemId, 'moving' );

            this.$wire.moveCard( orderId, Number( detail.toContext ), before, after )
                .then( ( result ) => 'rejected' === result ? revert() : this.state( detail.itemId, 'idle' ) )
                .catch( revert )
                .finally( settle );
        },
    }"
    x-on:ec-kb-focus-card.window="focusCard( $event.detail.orderId )"
    x-on:ec-kb-focus-column.window="focusColumn( $event.detail )"
>
    <div class="sr-only" aria-live="assertive" x-text="announcement" data-ec-kb-announcer></div>

    <div class="flex flex-wrap items-center justify-between gap-2">
        <div class="flex items-center gap-2">
            <h1 class="text-2xl font-bold" id="ec-kb-board-title">{{ $board->name }}</h1>

            @if ( $boards->count() > 1 || null !== $pickerUrl )
                <x-artisanpack-dropdown :label="__( 'Switch board' )" icon="o-chevron-up-down" class="btn-sm btn-ghost" data-ec-kb-switcher>
                    @foreach ( $boards as $option )
                        @php( $optionUrl = BoardUrls::show( $option ) )
                        @if ( null !== $optionUrl )
                            <x-artisanpack-menu-item
                                :title="$option->name"
                                :link="$optionUrl"
                                :active="(int) $option->id === (int) $board->id"
                                :aria-current="(int) $option->id === (int) $board->id ? 'page' : null"
                                no-wire-navigate
                                x-on:click.stop
                                wire:key="switch-{{ $option->id }}"
                            />
                        @endif
                    @endforeach

                    @if ( null !== $pickerUrl )
                        <x-artisanpack-menu-separator />
                        <x-artisanpack-menu-item :title="__( 'All boards' )" icon="o-squares-2x2" :link="$pickerUrl" no-wire-navigate x-on:click.stop />
                    @endif
                </x-artisanpack-dropdown>
            @endif
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <x-artisanpack-ec-kb-connection />

            @if ( $canEdit && ! $editing )
                <x-artisanpack-button size="sm" icon="o-plus" wire:click="openAddOrder" :label="__( 'Add order' )" data-ec-kb-add-order />
                <x-artisanpack-button variant="ghost" size="sm" icon="o-pencil-square" wire:click="startEditing" :label="__( 'Edit board' )" data-ec-kb-edit-board />
            @endif

            @if ( $editing )
                <x-artisanpack-button
                    variant="ghost"
                    size="sm"
                    icon="o-squares-plus"
                    x-on:click="$dispatch( 'ec-kb-edit-widgets', { columnId: null } )"
                    :label="__( 'Default card widgets' )"
                    data-ec-kb-board-widgets
                />
                @if ( null !== $advancedUrl )
                    <x-artisanpack-button variant="ghost" size="sm" icon="o-cog-6-tooth" :link="$advancedUrl" no-wire-navigate :label="__( 'Advanced settings' )" data-ec-kb-advanced-settings />
                @endif
                <x-artisanpack-button size="sm" icon="o-check" wire:click="stopEditing" :label="__( 'Done editing' )" data-ec-kb-stop-editing />
            @endif

            @if ( $activeFilters > 0 )
                <x-artisanpack-button
                    class="btn-sm btn-ghost"
                    icon="o-x-mark"
                    wire:click="clearFilters"
                    :label="__( 'Clear filters' )"
                    data-ec-kb-clear-filters
                />
            @endif

            <x-artisanpack-button
                class="btn-sm md:hidden"
                icon="o-funnel"
                x-on:click="$dispatch( 'ec-kb-open-filters' )"
                data-ec-kb-filters-toggle
            >
                {{ __( 'Filters' ) }}
                @if ( $activeFilters > 0 )
                    <span class="badge badge-sm badge-primary" data-ec-kb-filter-count>
                        <span aria-hidden="true">{{ $activeFilters }}</span>
                        <span class="sr-only">{{ trans_choice( ':count filter active|:count filters active', $activeFilters, [ 'count' => $activeFilters ] ) }}</span>
                    </span>
                @endif
            </x-artisanpack-button>
        </div>

        @if ( filled( $board->description ) )
            <p class="w-full text-sm text-base-content/70">{{ $board->description }}</p>
        @endif
    </div>

    <div class="hidden md:block">
        @include( 'ecommerce-kanban::partials.filter-bar', [ 'prefix' => 'ec-kb-filters' ] )
    </div>

    <div class="md:hidden">
        <x-artisanpack-drawer
            right
            :title="__( 'Filters' )"
            open-on="ec-kb-open-filters"
            close-on-escape
            with-close-button
            class="w-11/12 max-w-sm"
        >
            @include( 'ecommerce-kanban::partials.filter-bar', [ 'prefix' => 'ec-kb-filters-drawer' ] )
        </x-artisanpack-drawer>
    </div>

    @if ( $editing )
        <x-artisanpack-alert icon="o-pencil-square" class="alert-info" data-ec-kb-editing-notice>
            {{ __( 'Editing the board. Drag column headers or use the arrows to reorder columns; changes save as you make them.' ) }}
        </x-artisanpack-alert>
    @endif

    @if ( [] === $columns && ! $editing )
        <x-artisanpack-ec-kb-empty
            icon="o-view-columns"
            :title="__( 'This board has no columns' )"
            :description="__( 'Add columns to the board in its settings to place orders on it.' )"
            data-ec-kb-no-columns
        />
    @else
        {{-- Focusable so keyboard users can scroll it with the arrow keys. --}}
        <div
            class="flex snap-x snap-mandatory items-start gap-4 overflow-x-auto pb-4 md:snap-none"
            role="group"
            tabindex="0"
            aria-labelledby="ec-kb-board-title"
            aria-busy="{{ $this->loaded ? 'false' : 'true' }}"
            data-ec-kb-columns
            @if ( $editing )
                x-drag-context
                data-drag-group="columns"
                data-drag-context-id="board-{{ $board->id }}"
                data-drag-label="{{ $board->name }}"
                data-drag-orientation="horizontal"
                data-drag-messages="{{ json_encode( [ ...$dragMessages, 'item' => __( 'Column' ), 'container' => __( 'the board' ) ] ) }}"
                x-on:drag:end="endColumns( $event, $el )"
            @endif
        >
            @foreach ( $columns as $column )
                @if ( $editing )
                    <x-artisanpack-ec-kb-column
                        :column="$column"
                        :loaded="$this->loaded"
                        :draggable="false"
                        :drag-messages="$dragMessages"
                        editing
                        :first="$loop->first"
                        :last="$loop->last"
                        wire:key="column-{{ $column['id'] }}"
                        x-drag-item="{{ $column['id'] }}"
                        data-drag-label="{{ $column['label'] }}"
                    />
                @else
                    <x-artisanpack-ec-kb-column
                        :column="$column"
                        :loaded="$this->loaded"
                        :draggable="$canMove"
                        :drag-messages="$dragMessages"
                        wire:key="column-{{ $column['id'] }}"
                    />
                @endif
            @endforeach

            @if ( $editing )
                <div class="flex w-60 shrink-0 items-start" wire:key="add-column">
                    <x-artisanpack-button variant="outline" class="w-full" icon="o-plus" wire:click="openAddColumn" :label="__( 'Add column' )" data-ec-kb-add-column />
                </div>
            @endif
        </div>
    @endif

    {{-- "Move to…" (spec §7.3, D10): the single-pointer alternative to dragging. --}}
    <x-artisanpack-modal wire:model="moveDialog" :title="null === $moveNumber ? __( 'Move to…' ) : __( 'Move order :number', [ 'number' => $moveNumber ] )" data-ec-kb-move-dialog>
        <x-artisanpack-form wire:submit="submitMove" no-separator>
            <x-artisanpack-select
                id="ec-kb-move-column"
                wire:model.live="moveColumnId"
                :label="__( 'Column' )"
                :options="$moveColumns"
                data-ec-kb-move-column
            />

            <x-artisanpack-radio
                id="ec-kb-move-position"
                wire:model.live="movePosition"
                :label="__( 'Position' )"
                :options="array_values( array_filter( [
                    [ 'id' => 'top', 'name' => __( 'Top of the column' ) ],
                    [ 'id' => 'bottom', 'name' => __( 'Bottom of the column' ) ],
                    [] === $moveCards ? null : [ 'id' => 'after', 'name' => __( 'After a card' ) ],
                ] ) )"
                data-ec-kb-move-position
            />

            @if ( 'after' === $movePosition && [] !== $moveCards )
                <x-artisanpack-select
                    id="ec-kb-move-after"
                    wire:model="moveAfterOrderId"
                    :label="__( 'After' )"
                    :options="$moveCards"
                    :placeholder="__( 'Choose a card' )"
                    data-ec-kb-move-after
                />
            @endif

            <x-slot:actions>
                <x-artisanpack-button :label="__( 'Cancel' )" x-on:click="$wire.moveDialog = false" />
                <x-artisanpack-button type="submit" color="primary" :label="__( 'Move' )" spinner="submitMove" data-ec-kb-move-submit />
            </x-slot:actions>
        </x-artisanpack-form>
    </x-artisanpack-modal>

    {{-- Moving into an exit column changes the whole order's status, so it is confirmed first. --}}
    <x-artisanpack-modal wire:model="confirmingMove" :title="null === $pending ? '' : __( 'Move order :number to :column?', $pending )" data-ec-kb-confirm-move>
        @if ( null !== $pending )
            <p>{{ __( 'This changes the whole order\'s status to :status, on every board it is on.', [ 'status' => $pending['status'] ] ) }}</p>
        @endif

        <x-slot:actions>
            <x-artisanpack-button :label="__( 'Cancel' )" wire:click="cancelMove" data-ec-kb-cancel-move />
            <x-artisanpack-button color="primary" :label="__( 'Move' )" wire:click="confirmMove" spinner="confirmMove" data-ec-kb-confirm-move-button />
        </x-slot:actions>
    </x-artisanpack-modal>

    @if ( $canEdit )
        {{-- "Add order" (spec §7.6.1): search orders that aren't on the board and put one on it. --}}
        <x-artisanpack-modal wire:model="addOrderDialog" :title="__( 'Add an order to this board' )" data-ec-kb-add-order-dialog>
            <x-artisanpack-form wire:submit="submitAddOrder" no-separator>
                <x-artisanpack-input
                    id="ec-kb-add-order-search"
                    wire:model.live.debounce.300ms="addOrderSearch"
                    :label="__( 'Search orders' )"
                    :placeholder="__( 'Order number or email' )"
                    icon="o-magnifying-glass"
                    data-ec-kb-add-order-search
                />

                @if ( '' !== trim( (string) $addOrderSearch ) )
                    @if ( [] === $addOrderResults )
                        <p class="text-sm text-base-content/70" data-ec-kb-add-order-none>{{ __( "No orders that aren't already on this board match." ) }}</p>
                    @else
                        <x-artisanpack-radio
                            id="ec-kb-add-order-id"
                            wire:model="addOrderId"
                            :label="__( 'Order' )"
                            :options="$addOrderResults"
                            data-ec-kb-add-order-results
                        />
                    @endif
                @endif

                <x-artisanpack-select
                    id="ec-kb-add-order-column"
                    wire:model="addOrderColumnId"
                    :label="__( 'Column' )"
                    :options="$addOrderColumns"
                    :hint="__( 'The entry column is the first column that fits the order\'s status.' )"
                    data-ec-kb-add-order-column
                />

                <x-slot:actions>
                    <x-artisanpack-button :label="__( 'Cancel' )" x-on:click="$wire.addOrderDialog = false" />
                    <x-artisanpack-button type="submit" color="primary" :label="__( 'Add to board' )" spinner="submitAddOrder" data-ec-kb-add-order-submit />
                </x-slot:actions>
            </x-artisanpack-form>
        </x-artisanpack-modal>

        {{-- Removing a card takes the order off this board only; it runs once per token. --}}
        <x-artisanpack-modal wire:model="confirmingRemove" :title="null === $removingNumber ? '' : __( 'Remove order :number from this board?', [ 'number' => $removingNumber ] )" data-ec-kb-confirm-remove>
            <p>{{ __( "The order stays on any other boards it is on, and its status doesn't change." ) }}</p>

            <x-slot:actions>
                <x-artisanpack-button :label="__( 'Cancel' )" wire:click="cancelRemoveCard" data-ec-kb-cancel-remove />
                @if ( null !== $removeToken )
                    <x-artisanpack-button color="error" :label="__( 'Remove from board' )" wire:click="removeCard( {{ \Illuminate\Support\Js::from( $removeToken ) }} )" spinner="removeCard" data-ec-kb-remove-button />
                @endif
            </x-slot:actions>
        </x-artisanpack-modal>

        {{-- Column editor (spec §7.8): the engine's validation messages show on the fields. --}}
        <x-artisanpack-modal wire:model="columnDialog" :title="null === $editingColumn ? __( 'Edit column' ) : __( 'Edit column: :substatus', [ 'substatus' => $editingColumn ] )" data-ec-kb-column-dialog>
            <x-artisanpack-form wire:submit="saveColumn" no-separator>
                <x-artisanpack-input
                    id="ec-kb-column-label"
                    wire:model="columnForm.label_override"
                    :label="__( 'Label' )"
                    :hint="__( 'Leave empty to use the sub-status name.' )"
                    data-ec-kb-column-label
                />
                <x-artisanpack-colorpicker
                    id="ec-kb-column-color"
                    wire:model="columnForm.color_override"
                    :label="__( 'Color' )"
                    placeholder="#RRGGBB"
                    clearable
                    :hint="__( 'Leave empty to use the sub-status color.' )"
                    data-ec-kb-column-color
                />
                <x-artisanpack-input
                    id="ec-kb-column-icon"
                    wire:model="columnForm.icon_override"
                    :label="__( 'Icon' )"
                    :hint="__( 'An icon name such as o-truck. Leave empty to use the sub-status icon.' )"
                    data-ec-kb-column-icon
                />
                <x-artisanpack-input
                    id="ec-kb-column-wip"
                    type="number"
                    min="1"
                    wire:model="columnForm.wip_limit"
                    :label="__( 'WIP limit' )"
                    :hint="__( 'The most cards this column may hold. Leave empty for no limit.' )"
                    data-ec-kb-column-wip
                />

                <x-slot:actions>
                    <x-artisanpack-button :label="__( 'Cancel' )" x-on:click="$wire.columnDialog = false" />
                    <x-artisanpack-button type="submit" color="primary" :label="__( 'Save' )" spinner="saveColumn" data-ec-kb-column-save />
                </x-slot:actions>
            </x-artisanpack-form>
        </x-artisanpack-modal>

        <x-artisanpack-modal wire:model="addColumnDialog" :title="__( 'Add a column' )" data-ec-kb-add-column-dialog>
            <x-artisanpack-form wire:submit="addColumn" no-separator>
                @if ( [] === $substatusOptions )
                    <p class="text-sm text-base-content/70" data-ec-kb-no-substatuses>{{ __( 'Every sub-status already has a column on this board.' ) }}</p>
                @else
                    <x-artisanpack-select-group
                        id="ec-kb-add-column-substatus"
                        wire:model="newSubstatusId"
                        :label="__( 'Sub-status' )"
                        :options="$substatusOptions"
                        :placeholder="__( 'Choose a sub-status' )"
                        data-ec-kb-add-column-substatus
                    />
                @endif

                <x-slot:actions>
                    <x-artisanpack-button :label="__( 'Cancel' )" x-on:click="$wire.addColumnDialog = false" />
                    @if ( [] !== $substatusOptions )
                        <x-artisanpack-button type="submit" color="primary" :label="__( 'Add column' )" spinner="addColumn" data-ec-kb-add-column-submit />
                    @endif
                </x-slot:actions>
            </x-artisanpack-form>
        </x-artisanpack-modal>

        {{-- Removing a column runs once per token; cards on it must move first. --}}
        <x-artisanpack-modal wire:model="confirmingDeleteColumn" :title="null === $deletingColumn ? '' : __( 'Remove column :column?', [ 'column' => $deletingColumn['label'] ] )" data-ec-kb-confirm-delete-column>
            @if ( null !== $deletingColumn )
                <div class="flex flex-col gap-4">
                    @if ( $deletingColumn['cards'] > 0 )
                        <p data-ec-kb-delete-column-cards>{{ trans_choice( 'This column has :count card. Choose a column to move it to before removing this one.|This column has :count cards. Choose a column to move them to before removing this one.', $deletingColumn['cards'], [ 'count' => $deletingColumn['cards'] ] ) }}</p>

                        <x-artisanpack-select
                            id="ec-kb-delete-column-move-to"
                            wire:model="deleteMoveTo"
                            :label="__( 'Move the cards to' )"
                            :options="$moveToColumns"
                            :placeholder="__( 'Choose a column' )"
                            data-ec-kb-delete-column-move-to
                        />
                    @else
                        <p>{{ __( 'Automations that move cards into this column are removed with it.' ) }}</p>
                    @endif
                </div>
            @endif

            <x-slot:actions>
                <x-artisanpack-button :label="__( 'Cancel' )" wire:click="cancelDeleteColumn" />
                @if ( null !== $deletingColumn )
                    <x-artisanpack-button color="error" :label="__( 'Remove column' )" wire:click="deleteColumn( {{ \Illuminate\Support\Js::from( $deletingColumn['token'] ) }} )" spinner="deleteColumn" data-ec-kb-delete-column-button />
                @endif
            </x-slot:actions>
        </x-artisanpack-modal>

        <livewire:artisanpack-ecommerce-kanban-widget-picker :board-id="$board->id" wire:key="widget-picker-{{ $board->id }}" />
    @endif

    <livewire:artisanpack-ecommerce-kanban-card-detail :board-id="$board->id" wire:key="card-detail-{{ $board->id }}" />
</div>
