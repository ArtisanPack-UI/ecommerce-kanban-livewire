{{--
    A board (spec §7.2): a header with the board switcher, the filter bar,
    then the columns left to right in a horizontally scrolling strip.

    The collapsed columns are remembered per board in `localStorage` under
    `$storageKey`; the first render draws only the column headers, then
    `loadBoard()` loads the cards of the open columns on screen. Columns
    further along load as they scroll into view (`ec-kb-column-visible`,
    batched into `loadColumns()`).

    Single columns re-render without the board (spec §12): a successful
    drop's two columns arrive in `ec-kb-columns-rendered`, and real-time
    card events, polls, and lazy loads return column HTML; `morphColumns()`
    morphs each in place, or re-renders the board when the result asks for
    it (or Alpine's morph plugin is missing). Column morphs wait while a
    card is being dragged.

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
    to `refreshColumns()` (card events naming columns) or `boardEvent()`.
    Without Echo it polls every `$realtime['poll']` seconds, skipping while
    a card is dragged, a move is in flight, or the tab is hidden, and polls
    at once when the tab is shown again. `x-artisanpack-ec-kb-connection`
    shows which applies.

    Automation feedback (spec §7.10): while live, the board sets
    `liveFeedback` and collects the engine's `automation-fired` /
    `automation-failed` broadcasts for cards this browser moved in the last
    `feedbackWindow` ms (`ec-kb-card-moved` marks them), then sends each
    card's batch to `automationFeedback()` for one toast. Otherwise the move
    request toasts from the order timeline itself.

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
        pendingColumns: new Set(),
        columnTimer: null,
        lazyColumns: new Set(),
        lazyTimer: null,
        recentMoves: {},
        feedback: {},
        feedbackWindow: 15000,
        moveTimeout: 20000,
        pendingMoves: {},
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
            this.watchFailures();

            // The dialogs close on Escape or the backdrop by setting their
            // property in the browser only; run the matching cancel action so
            // the server forgets the pending change and focus goes back.
            this.closeWith( 'moveDialog', 'closeMoveDialog' );
            this.closeWith( 'confirmingMove', 'cancelMove' );
            this.closeWith( 'addOrderDialog', 'cancelAddOrder' );
            this.closeWith( 'confirmingRemove', 'cancelRemoveCard' );
            this.closeWith( 'columnDialog', 'closeColumnDialog' );
            this.closeWith( 'addColumnDialog', 'cancelAddColumn' );
            this.closeWith( 'confirmingDeleteColumn', 'cancelDeleteColumn' );

            this.$wire.loadBoard( this.read(), this.visibleColumns() ).then( () => {
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
        watchFailures() {
            // Once per page: tell every board when a Livewire request fails.
            if ( window.ecommerceKanbanFailureHook || ! window.Livewire ) {
                return;
            }

            window.ecommerceKanbanFailureHook = true;
            window.Livewire.hook( 'request', ( { fail } ) => {
                fail( ( { status } ) => window.dispatchEvent( new CustomEvent( 'ec-kb-request-failed', { detail: { status } } ) ) );
            } );
        },
        requestFailed() {
            const pending = Object.values( this.pendingMoves );

            if ( 0 === pending.length ) {
                return;
            }

            // The board can't tell which move the failed request carried, so
            // it puts every pending one back and re-reads the board.
            pending.forEach( ( revert ) => revert() );
            this.$wire.$refresh();
        },
        closeWith( property, action ) {
            this.$wire.$watch( property, ( open ) => {
                // A close the server made (after a save, say) is already
                // confirmed: only one made here still has to be sent.
                if ( ! open && false !== this.$wire.__instance?.canonical?.[ property ] ) {
                    this.$wire[ action ]();
                }
            } );
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
                this.$wire.poll().then( ( result ) => this.morphColumns( result ) );
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

            const live = 'live' === this.connection;

            if ( this.$wire.liveFeedback !== live ) {
                this.$wire.$set( 'liveFeedback', live, false );
            }

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
                this.queueFeedback( orderId, event );

                return;
            }

            const columns = [ event.from_column_id, event.to_column_id, event.column_id ].filter( ( id ) => null !== id && undefined !== id ).map( Number );

            if ( [ 'card-moved', 'card-reordered', 'card-added', 'card-removed' ].includes( event.type ) && columns.length > 0 ) {
                columns.forEach( ( id ) => this.pendingColumns.add( id ) );
                clearTimeout( this.columnTimer );
                this.columnTimer = setTimeout( () => this.flushColumns(), 300 );

                return;
            }

            this.$wire.boardEvent( event.type );
        },
        visibleColumns() {
            const strip = this.$refs.columns;

            if ( ! strip ) {
                return [];
            }

            const box    = strip.getBoundingClientRect();
            const margin = box.width / 2;

            return Array.from( strip.querySelectorAll( '[data-ec-kb-column]' ) )
                .filter( ( column ) => {
                    const rect = column.getBoundingClientRect();

                    return rect.right >= box.left - margin && rect.left <= box.right + margin;
                } )
                .map( ( column ) => Number( column.dataset.ecKbColumn ) );
        },
        queueLazy( columnId ) {
            this.lazyColumns.add( Number( columnId ) );
            clearTimeout( this.lazyTimer );
            this.lazyTimer = setTimeout( () => {
                const ids = Array.from( this.lazyColumns );

                this.lazyColumns.clear();
                this.$wire.loadColumns( ids ).then( ( result ) => this.morphColumns( result ) );
            }, 100 );
        },
        flushColumns() {
            if ( null !== this.dragging ) {
                this.columnTimer = setTimeout( () => this.flushColumns(), 500 );

                return;
            }

            const ids = Array.from( this.pendingColumns );

            this.pendingColumns.clear();

            if ( ids.length > 0 ) {
                this.$wire.refreshColumns( ids ).then( ( result ) => this.morphColumns( result ) );
            }
        },
        morphColumns( result ) {
            const columns = Object.entries( result?.columns ?? {} );

            if ( result?.refresh || ( columns.length > 0 && ! window.Alpine?.morph ) ) {
                return this.$wire.$refresh();
            }

            if ( null !== this.dragging && columns.length > 0 ) {
                return setTimeout( () => this.morphColumns( result ), 500 );
            }

            for ( const [ columnId, html ] of columns ) {
                const column = this.$root.querySelector( '[data-ec-kb-column=\'' + Number( columnId ) + '\']' );

                if ( ! column ) {
                    return this.$wire.$refresh();
                }

                window.Alpine.morph( column, html );
            }
        },
        markMoved( orderId ) {
            const now = Date.now();

            Object.keys( this.recentMoves ).forEach( ( id ) => {
                if ( now - this.recentMoves[ id ] > this.feedbackWindow ) {
                    delete this.recentMoves[ id ];
                }
            } );

            this.recentMoves[ Number( orderId ) ] = now;
        },
        queueFeedback( orderId, event ) {
            if ( null === orderId || ! this.realtime.echo ) {
                return;
            }

            const entry = this.feedback[ orderId ] ?? { fired: [], failed: [], timer: null };

            ( 'automation-failed' === event.type ? entry.failed : entry.fired ).push( Number( event.automation_id ) );
            clearTimeout( entry.timer );
            entry.timer = setTimeout( () => this.flushFeedback( orderId ), 1500 );
            this.feedback[ orderId ] = entry;
        },
        flushFeedback( orderId ) {
            const entry = this.feedback[ orderId ];
            const moved = this.recentMoves[ orderId ];

            delete this.feedback[ orderId ];

            if ( entry && undefined !== moved && Date.now() - moved <= this.feedbackWindow ) {
                this.$wire.automationFeedback( orderId, entry.fired, entry.failed );
            }
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

            let done   = false;
            const undo = () => {
                if ( ! done ) {
                    done = true;
                    delete this.pendingMoves[ detail.moveId ];
                    window.LivewireDragAndDrop?.revert( detail.moveId );
                    this.$wire.$refresh();
                }
            };

            this.pendingMoves[ detail.moveId ] = undo;

            this.$wire.reorderColumns( detail.toOrderedIds.map( Number ) )
                .then( ( result ) => {
                    if ( 'rejected' === result ) {
                        return undo();
                    }

                    done = true;
                    delete this.pendingMoves[ detail.moveId ];
                } )
                .catch( undo );
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
        focusSelector( selector ) {
            // After the dialog that asked has closed and the board re-rendered.
            setTimeout( () => this.$root.querySelector( selector )?.focus(), 100 );
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
            let done      = false;
            let watchdog  = null;
            const finish  = () => {
                if ( done ) {
                    return false;
                }

                done = true;
                clearTimeout( watchdog );
                delete this.pendingMoves[ detail.moveId ];
                setTimeout( () => delete this.inFlight[ orderId ], 3000 );

                return true;
            };
            const revert  = () => {
                if ( finish() ) {
                    window.LivewireDragAndDrop?.revert( detail.moveId );
                    this.state( detail.itemId, 'reverting' );
                    setTimeout( () => this.state( detail.itemId, 'idle' ), 2000 );
                }
            };
            const accept  = () => finish() && this.state( detail.itemId, 'idle' );

            this.inFlight[ orderId ]          = true;
            this.pendingMoves[ detail.moveId ] = revert;
            this.markMoved( orderId );
            this.state( detail.itemId, 'moving' );

            // A refused move resolves `rejected`. A failed request (rate
            // limited, server error) doesn't always settle the call, so
            // requestFailed() reverts pending moves too, and so does the
            // watchdog should nothing answer at all.
            watchdog = setTimeout( () => {
                revert();
                this.$wire.$refresh();
            }, this.moveTimeout );

            this.$wire.moveCard( orderId, Number( detail.toContext ), before, after )
                .then( ( result ) => 'rejected' === result ? revert() : accept() )
                .catch( revert );
        },
    }"
    x-on:ec-kb-focus-card.window="focusCard( $event.detail.orderId )"
    x-on:ec-kb-focus.window="focusSelector( $event.detail.selector )"
    x-on:ec-kb-request-failed.window="requestFailed()"
    x-on:ec-kb-focus-column.window="focusColumn( $event.detail )"
    x-on:ec-kb-columns-rendered.window="morphColumns( { columns: $event.detail.columns } )"
    x-on:ec-kb-card-moved.window="markMoved( $event.detail.orderId )"
    x-on:ec-kb-column-visible="queueLazy( $event.detail.columnId )"
>
    <div class="apui-sr-only" aria-live="assertive" x-text="announcement" data-ec-kb-announcer></div>

    {{-- How many cards the filters leave, announced when it changes. --}}
    <p class="apui-sr-only" role="status" data-ec-kb-filter-status>
        @if ( $activeFilters > 0 && $this->loaded )
            {{ trans_choice( ':count order matches the filters.|:count orders match the filters.', $matchingCards, [ 'count' => $matchingCards ] ) }}
        @endif
    </p>

    <div class="flex flex-wrap items-center justify-between gap-2">
        <div class="flex items-center gap-2">
            <h1 class="text-2xl font-bold" id="ec-kb-board-title">{{ $board->name }}</h1>

            @if ( $boards->count() > 1 || null !== $pickerUrl )
                @php
                    $switchTargets = [];

                    foreach ( $boards as $option ) {
                        $switchTargets[ (string) $option->id ] = BoardUrls::show( $option );
                    }

                    $switchTargets['all'] = $pickerUrl;
                @endphp
                <x-artisanpack-dropdown-menu
                    :label="__( 'Switch board' )"
                    data-ec-kb-switcher
                    data-ec-kb-targets="{{ json_encode( $switchTargets, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) }}"
                    x-on:select="const url = JSON.parse( $el.dataset.ecKbTargets )[ $event.detail.value ]; if ( url ) { window.location.assign( url ) }"
                >
                    @foreach ( $boards as $option )
                        @if ( null !== BoardUrls::show( $option ) )
                            <x-artisanpack-dropdown-menu-item
                                type="radio"
                                :value="(string) $option->id"
                                :checked="(int) $option->id === (int) $board->id"
                                wire:key="switch-{{ $option->id }}"
                                data-ec-kb-switch="{{ $option->id }}"
                            >{{ $option->name }}</x-artisanpack-dropdown-menu-item>
                        @endif
                    @endforeach

                    @if ( null !== $pickerUrl )
                        <x-artisanpack-dropdown-menu-separator />
                        <x-artisanpack-dropdown-menu-item value="all" icon="o-squares-2x2" data-ec-kb-switch="all">{{ __( 'All boards' ) }}</x-artisanpack-dropdown-menu-item>
                    @endif
                </x-artisanpack-dropdown-menu>
            @endif
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <x-artisanpack-ec-kb-connection :seconds="$realtime['poll']" />

            @foreach ( $toolbar as $action )
                @if ( null !== $action['url'] && null !== $action['event'] )
                    <x-artisanpack-button
                        variant="ghost" color="neutral" size="sm"
                        :leading-icon="$action['icon']"
                        :href="$action['url']"
                        x-on:click="if ( ! ( $event.metaKey || $event.ctrlKey || $event.shiftKey || $event.altKey ) ) { $event.preventDefault(); $wire.dispatch( {{ \Illuminate\Support\Js::from( $action['event'] ) }}, { boardId: {{ (int) $board->id }} } ) }"
                        data-ec-kb-toolbar-action="{{ $action['key'] }}"
                    >{{ $action['label'] }}</x-artisanpack-button>
                @elseif ( null !== $action['url'] )
                    <x-artisanpack-button variant="ghost" color="neutral" size="sm" :leading-icon="$action['icon']" :href="$action['url']" data-ec-kb-toolbar-action="{{ $action['key'] }}">{{ $action['label'] }}</x-artisanpack-button>
                @else
                    <x-artisanpack-button
                        variant="ghost" color="neutral" size="sm"
                        :leading-icon="$action['icon']"
                        x-on:click="$wire.dispatch( {{ \Illuminate\Support\Js::from( $action['event'] ) }}, { boardId: {{ (int) $board->id }} } )"
                        data-ec-kb-toolbar-action="{{ $action['key'] }}"
                    >{{ $action['label'] }}</x-artisanpack-button>
                @endif
            @endforeach

            @unless ( $editing )
                <x-artisanpack-button
                    variant="ghost" color="neutral" size="sm"
                    leading-icon="o-bolt"
                    x-on:click="$dispatch( 'ec-kb-edit-automations', { columnId: null } )"
                    data-ec-kb-automations
                >{{ __( 'Automations' ) }}</x-artisanpack-button>
            @endunless

            @if ( $canEdit && ! $editing )
                <x-artisanpack-button size="sm" leading-icon="plus" wire:click="openAddOrder" data-ec-kb-add-order>{{ __( 'Add order' ) }}</x-artisanpack-button>
                <x-artisanpack-button variant="ghost" color="neutral" size="sm" leading-icon="pencil" wire:click="startEditing" data-ec-kb-edit-board>{{ __( 'Edit board' ) }}</x-artisanpack-button>
            @endif

            @if ( $editing )
                <x-artisanpack-button
                    variant="ghost" color="neutral" size="sm"
                    leading-icon="o-squares-plus"
                    x-on:click="$dispatch( 'ec-kb-edit-widgets', { columnId: null } )"
                    data-ec-kb-board-widgets
                >{{ __( 'Default card widgets' ) }}</x-artisanpack-button>
                @if ( null !== $advancedUrl )
                    <x-artisanpack-button variant="ghost" color="neutral" size="sm" leading-icon="settings" :href="$advancedUrl" data-ec-kb-advanced-settings>{{ __( 'Advanced settings' ) }}</x-artisanpack-button>
                @endif
                <x-artisanpack-button size="sm" leading-icon="check" wire:click="stopEditing" data-ec-kb-stop-editing>{{ __( 'Done editing' ) }}</x-artisanpack-button>
            @endif

            {{-- Below md the filters live in a drawer: its button, and Clear filters while any are on. --}}
            <div class="ec-kb-below-md flex flex-wrap items-center gap-2">
                <x-artisanpack-button
                    variant="outline"
                    color="neutral"
                    size="sm"
                    leading-icon="o-funnel"
                    x-on:click="$dispatch( 'ec-kb-open-filters' )"
                    data-ec-kb-filters-toggle
                >
                    {{ __( 'Filters' ) }}
                    @if ( $activeFilters > 0 )
                        <span class="ec-kb-filter-count" aria-hidden="true" data-ec-kb-filter-count>{{ $activeFilters }}</span>
                        <span class="apui-sr-only">{{ trans_choice( ':count filter active|:count filters active', $activeFilters, [ 'count' => $activeFilters ] ) }}</span>
                    @endif
                </x-artisanpack-button>

                @if ( $activeFilters > 0 )
                    <x-artisanpack-button variant="ghost" color="neutral" size="sm" leading-icon="x" wire:click="clearFilters" data-ec-kb-toolbar-clear-filters>{{ __( 'Clear filters' ) }}</x-artisanpack-button>
                @endif
            </div>
        </div>

        @if ( filled( $board->description ) )
            <p class="ec-kb-muted w-full text-sm">{{ $board->description }}</p>
        @endif
    </div>

    <div class="ec-kb-from-md">
        @include( 'ecommerce-kanban::partials.filter-bar', [ 'prefix' => 'ec-kb-filters' ] )
    </div>

    <x-artisanpack-drawer
        side="end"
        size="sm"
        class="ec-kb-filters-drawer"
        :title="__( 'Filters' )"
        :description="$this->loaded ? trans_choice( ':shown of :total order shown|:shown of :total orders shown', $totalCards, [ 'shown' => $matchingCards, 'total' => $totalCards ] ) : null"
        x-on:ec-kb-open-filters.window="open = true"
        data-ec-kb-filters-drawer
    >
        @include( 'ecommerce-kanban::partials.filter-bar', [ 'prefix' => 'ec-kb-filters-drawer', 'inDrawer' => true ] )

        <x-slot:footer>
            @if ( $activeFilters > 0 )
                <x-artisanpack-button variant="ghost" color="neutral" wire:click="clearFilters" data-ec-kb-drawer-clear-filters>{{ __( 'Clear filters' ) }}</x-artisanpack-button>
            @endif
            <x-artisanpack-button x-on:click="open = false" data-ec-kb-show-orders>{{ trans_choice( 'Show :count order|Show :count orders', $matchingCards, [ 'count' => $matchingCards ] ) }}</x-artisanpack-button>
        </x-slot:footer>
    </x-artisanpack-drawer>

    @unless ( $board->is_active )
        <x-artisanpack-alert color="warning" icon="o-eye-slash" data-ec-kb-inactive-notice>
            {{ __( 'This board is switched off. Only people who can edit it see it.' ) }}
        </x-artisanpack-alert>
    @endunless

    @if ( $editing )
        <x-artisanpack-alert color="info" icon="pencil" data-ec-kb-editing-notice>
            {{ __( 'Editing the board. Drag column headers or use the arrows to reorder columns; changes save as you make them.' ) }}
        </x-artisanpack-alert>
    @endif

    @if ( [] === $columns && ! $editing )
        <x-artisanpack-ec-kb-empty
            live
            icon="o-view-columns"
            :title="__( 'This board has no columns' )"
            :description="__( 'Add columns to the board in its settings to place orders on it.' )"
            data-ec-kb-no-columns
        >
            @if ( $canEdit )
                <x-artisanpack-button variant="outline" color="neutral" leading-icon="pencil" wire:click="startEditing" data-ec-kb-empty-edit-board>{{ __( 'Edit board' ) }}</x-artisanpack-button>
            @endif
        </x-artisanpack-ec-kb-empty>
    @else
        {{-- Focusable so keyboard users can scroll it with the arrow keys. --}}
        <div
            class="ec-kb-strip"
            role="group"
            tabindex="0"
            aria-labelledby="ec-kb-board-title"
            aria-busy="{{ $this->loaded ? 'false' : 'true' }}"
            data-ec-kb-columns
            x-ref="columns"
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
                        :loaded="$column['loaded']"
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
                    @include( 'ecommerce-kanban::partials.column-item', [ 'column' => $column, 'draggable' => $canMove ] )
                @endif
            @endforeach

            @if ( $editing )
                <div class="flex w-60 shrink-0 items-start" wire:key="add-column">
                    <x-artisanpack-button variant="outline" color="neutral" class="w-full" leading-icon="plus" wire:click="openAddColumn" data-ec-kb-add-column>{{ __( 'Add column' ) }}</x-artisanpack-button>
                </div>
            @endif
        </div>
    @endif

    {{-- "Move to…" (spec §7.3, D10): the single-pointer alternative to dragging. --}}
    <x-artisanpack-dialog wire:model="moveDialog" :title="null === $moveNumber ? __( 'Move to…' ) : __( 'Move order :number', [ 'number' => $moveNumber ] )" data-ec-kb-move-dialog>
        <x-artisanpack-form wire:submit="submitMove">
            <x-artisanpack-select
                id="ec-kb-move-column"
                placeholder=""
                wire:model.live="moveColumnId"
                :label="__( 'Column' )"
                :options="\ArtisanPackUI\EcommerceKanbanLivewire\Support\ControlOptions::from( $moveColumns )"
                data-ec-kb-move-column
            />

            <x-artisanpack-radio-group
                id="ec-kb-move-position"
                wire:model.live="movePosition"
                :label="__( 'Position' )"
                :options="\ArtisanPackUI\EcommerceKanbanLivewire\Support\ControlOptions::from( array_values( array_filter( [
                    [ 'id' => 'top', 'name' => __( 'Top of the column' ) ],
                    [ 'id' => 'bottom', 'name' => __( 'Bottom of the column' ) ],
                    [] === $moveCards ? null : [ 'id' => 'after', 'name' => __( 'After a card' ) ],
                ] ) ) )"
                data-ec-kb-move-position
            />

            @if ( 'after' === $movePosition && [] !== $moveCards )
                <x-artisanpack-select
                    id="ec-kb-move-after"
                    wire:model="moveAfterOrderId"
                    :label="__( 'After' )"
                    :options="\ArtisanPackUI\EcommerceKanbanLivewire\Support\ControlOptions::from( $moveCards )"
                    :placeholder="__( 'Choose a card' )"
                    :hint="$moveCardsTruncated ? __( 'Showing the first :count cards. Use Top or Bottom, or drag, for cards further down.', [ 'count' => count( $moveCards ) ] ) : null"
                    data-ec-kb-move-after
                />
            @endif

            <x-slot:actions>
                <x-artisanpack-button variant="ghost" color="neutral" wire:click="closeMoveDialog">{{ __( 'Cancel' ) }}</x-artisanpack-button>
                <x-artisanpack-button type="submit" wire:loading.attr="disabled" wire:target="submitMove" data-ec-kb-move-submit>{{ __( 'Move' ) }}</x-artisanpack-button>
            </x-slot:actions>
        </x-artisanpack-form>
    </x-artisanpack-dialog>

    {{-- Moving into an exit column changes the whole order's status, so it is confirmed first. --}}
    <x-artisanpack-dialog wire:model="confirmingMove" :title="null === $pending ? '' : __( 'Move order :number to :column?', $pending )" data-ec-kb-confirm-move>
        @if ( null !== $pending )
            <p>{{ __( 'This changes the whole order\'s status to :status, on every board it is on.', [ 'status' => $pending['status'] ] ) }}</p>
        @endif

        <x-slot:footer>
            <x-artisanpack-button variant="ghost" color="neutral" wire:click="cancelMove" data-ec-kb-cancel-move>{{ __( 'Cancel' ) }}</x-artisanpack-button>
            <x-artisanpack-button wire:click="confirmMove" wire:loading.attr="disabled" wire:target="confirmMove" data-ec-kb-confirm-move-button>{{ __( 'Move' ) }}</x-artisanpack-button>
        </x-slot:footer>
    </x-artisanpack-dialog>

    @if ( $canEdit )
        {{-- "Add order" (spec §7.6.1): search orders that aren't on the board and put one on it. --}}
        <x-artisanpack-dialog wire:model="addOrderDialog" :title="__( 'Add an order to this board' )" data-ec-kb-add-order-dialog>
            <x-artisanpack-form wire:submit="submitAddOrder">
                <x-artisanpack-input
                    id="ec-kb-add-order-search"
                    wire:model.live.debounce.300ms="addOrderSearch"
                    :label="__( 'Search orders' )"
                    :placeholder="__( 'Order number or email' )"
                    leading-icon="search"
                    data-ec-kb-add-order-search
                />

                @if ( '' !== trim( (string) $addOrderSearch ) )
                    @if ( [] === $addOrderResults )
                        <p class="ec-kb-muted text-sm" data-ec-kb-add-order-none>{{ __( "No orders that aren't already on this board match." ) }}</p>
                    @else
                        <x-artisanpack-radio-group
                            id="ec-kb-add-order-id"
                            wire:model="addOrderId"
                            :label="__( 'Order' )"
                            :options="\ArtisanPackUI\EcommerceKanbanLivewire\Support\ControlOptions::from( $addOrderResults )"
                            data-ec-kb-add-order-results
                        />
                    @endif
                @endif

                <x-artisanpack-select
                    id="ec-kb-add-order-column"
                    placeholder=""
                    wire:model="addOrderColumnId"
                    :label="__( 'Column' )"
                    :options="\ArtisanPackUI\EcommerceKanbanLivewire\Support\ControlOptions::from( $addOrderColumns )"
                    :hint="__( 'The entry column is the first column that fits the order\'s status.' )"
                    data-ec-kb-add-order-column
                />

                <x-slot:actions>
                    <x-artisanpack-button variant="ghost" color="neutral" wire:click="cancelAddOrder">{{ __( 'Cancel' ) }}</x-artisanpack-button>
                    <x-artisanpack-button type="submit" wire:loading.attr="disabled" wire:target="submitAddOrder" data-ec-kb-add-order-submit>{{ __( 'Add to board' ) }}</x-artisanpack-button>
                </x-slot:actions>
            </x-artisanpack-form>
        </x-artisanpack-dialog>

        {{-- Removing a card takes the order off this board only; it runs once per token. --}}
        <x-artisanpack-dialog wire:model="confirmingRemove" :title="null === $removingNumber ? '' : __( 'Remove order :number from this board?', [ 'number' => $removingNumber ] )" data-ec-kb-confirm-remove>
            <p>{{ __( "The order stays on any other boards it is on, and its status doesn't change." ) }}</p>

            <x-slot:footer>
                <x-artisanpack-button variant="ghost" color="neutral" wire:click="cancelRemoveCard" data-ec-kb-cancel-remove>{{ __( 'Cancel' ) }}</x-artisanpack-button>
                @if ( null !== $removeToken )
                    <x-artisanpack-button color="error" wire:click="removeCard( {{ \Illuminate\Support\Js::from( $removeToken ) }} )" wire:loading.attr="disabled" wire:target="removeCard" data-ec-kb-remove-button>{{ __( 'Remove from board' ) }}</x-artisanpack-button>
                @endif
            </x-slot:footer>
        </x-artisanpack-dialog>

        {{-- Column editor (spec §7.8): the engine's validation messages show on the fields. The color picker stays in the dialog's layer: its own portal inside the dialog's leaves its positioner outside the widget. --}}
        <x-artisanpack-dialog wire:model="columnDialog" :title="null === $editingColumn ? __( 'Edit column' ) : __( 'Edit column: :substatus', [ 'substatus' => $editingColumn ] )" data-ec-kb-column-dialog>
            <x-artisanpack-form wire:submit="saveColumn">
                <x-artisanpack-input
                    id="ec-kb-column-label"
                    wire:model="columnForm.label_override"
                    :label="__( 'Label' )"
                    :hint="__( 'Leave empty to use the sub-status name.' )"
                    data-ec-kb-column-label
                />
                <x-artisanpack-color-picker
                    id="ec-kb-column-color"
                    wire:model="columnForm.color_override"
                    :label="__( 'Color' )"
                    :alpha="false"
                    :portal="false"
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
                    <x-artisanpack-button variant="ghost" color="neutral" wire:click="closeColumnDialog">{{ __( 'Cancel' ) }}</x-artisanpack-button>
                    <x-artisanpack-button type="submit" wire:loading.attr="disabled" wire:target="saveColumn" data-ec-kb-column-save>{{ __( 'Save' ) }}</x-artisanpack-button>
                </x-slot:actions>
            </x-artisanpack-form>
        </x-artisanpack-dialog>

        <x-artisanpack-dialog wire:model="addColumnDialog" :title="__( 'Add a column' )" data-ec-kb-add-column-dialog>
            <x-artisanpack-form wire:submit="addColumn">
                @if ( [] === $substatusOptions )
                    <p class="ec-kb-muted text-sm" data-ec-kb-no-substatuses>{{ __( 'Every sub-status already has a column on this board.' ) }}</p>
                @else
                    <x-artisanpack-select
                        id="ec-kb-add-column-substatus"
                        wire:model="newSubstatusId"
                        :label="__( 'Sub-status' )"
                        :options="\ArtisanPackUI\EcommerceKanbanLivewire\Support\ControlOptions::from( collect( $substatusOptions )->map( static fn ( array $options, string $group ): array => [ 'label' => $group, 'options' => $options ] )->values() )"
                        :placeholder="__( 'Choose a sub-status' )"
                        data-ec-kb-add-column-substatus
                    />
                @endif

                <x-slot:actions>
                    <x-artisanpack-button variant="ghost" color="neutral" wire:click="cancelAddColumn">{{ __( 'Cancel' ) }}</x-artisanpack-button>
                    @if ( [] !== $substatusOptions )
                        <x-artisanpack-button type="submit" wire:loading.attr="disabled" wire:target="addColumn" data-ec-kb-add-column-submit>{{ __( 'Add column' ) }}</x-artisanpack-button>
                    @endif
                </x-slot:actions>
            </x-artisanpack-form>
        </x-artisanpack-dialog>

        {{-- Removing a column runs once per token; cards on it must move first. --}}
        <x-artisanpack-dialog wire:model="confirmingDeleteColumn" :title="null === $deletingColumn ? '' : __( 'Remove column :column?', [ 'column' => $deletingColumn['label'] ] )" data-ec-kb-confirm-delete-column>
            @if ( null !== $deletingColumn )
                <div class="flex flex-col gap-4">
                    @if ( $deletingColumn['cards'] > 0 )
                        <p data-ec-kb-delete-column-cards>{{ trans_choice( 'This column has :count card. Choose a column to move it to before removing this one.|This column has :count cards. Choose a column to move them to before removing this one.', $deletingColumn['cards'], [ 'count' => $deletingColumn['cards'] ] ) }}</p>

                        <x-artisanpack-select
                            id="ec-kb-delete-column-move-to"
                            wire:model.live="deleteMoveTo"
                            :label="__( 'Move the cards to' )"
                            :options="\ArtisanPackUI\EcommerceKanbanLivewire\Support\ControlOptions::from( $moveToColumns )"
                            :placeholder="__( 'Choose a column' )"
                            data-ec-kb-delete-column-move-to
                        />

                        {{-- An exit column changes every moved order's status, so it is confirmed, as a drop into one is. --}}
                        @if ( null !== $deletingColumn['exit'] )
                            <x-artisanpack-alert color="warning" icon="triangle-alert" data-ec-kb-delete-column-exit>
                                {{ trans_choice( 'Moving :count order to :column changes its status to :status on every board it is on.|Moving :count orders to :column changes each order\'s status to :status on every board it is on.', $deletingColumn['cards'], [ 'count' => $deletingColumn['cards'], 'column' => $deletingColumn['exit']['column'], 'status' => $deletingColumn['exit']['status'] ] ) }}
                            </x-artisanpack-alert>

                            <x-artisanpack-checkbox
                                id="ec-kb-delete-column-confirm-exit"
                                wire:model="confirmDeleteExit"
                                :label="__( 'I understand' )"
                                data-ec-kb-delete-column-confirm-exit
                            />
                        @endif
                    @else
                        <p>{{ __( 'Automations that move cards into this column are removed with it.' ) }}</p>
                    @endif
                </div>
            @endif

            <x-slot:footer>
                <x-artisanpack-button variant="ghost" color="neutral" wire:click="cancelDeleteColumn" data-ec-kb-cancel-delete-column>{{ __( 'Cancel' ) }}</x-artisanpack-button>
                @if ( null !== $deletingColumn )
                    <x-artisanpack-button color="error" wire:click="deleteColumn( {{ \Illuminate\Support\Js::from( $deletingColumn['token'] ) }} )" wire:loading.attr="disabled" wire:target="deleteColumn" data-ec-kb-delete-column-button>{{ __( 'Remove column' ) }}</x-artisanpack-button>
                @endif
            </x-slot:footer>
        </x-artisanpack-dialog>

        <livewire:artisanpack-ecommerce-kanban-widget-picker :board-id="$board->id" wire:key="widget-picker-{{ $board->id }}" />
    @endif

    <livewire:artisanpack-ecommerce-kanban-automations :board-id="$board->id" wire:key="automations-{{ $board->id }}" />

    <livewire:artisanpack-ecommerce-kanban-card-detail :board-id="$board->id" wire:key="card-detail-{{ $board->id }}" />
</div>
