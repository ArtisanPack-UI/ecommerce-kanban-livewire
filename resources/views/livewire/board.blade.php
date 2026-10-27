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
            this.$wire.loadBoard( this.read() ).then( () => {
                this.$wire.$watch( 'collapsed', ( ids ) => this.write( ids ) );

                const card = new URLSearchParams( window.location.search ).get( 'card' );

                if ( null !== card ) {
                    this.$nextTick( () => this.card( card )?.scrollIntoView( { block: 'nearest', inline: 'nearest' } ) );
                }
            } );
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

            if ( detail.toElement !== list || ( detail.fromContext === detail.toContext && detail.fromIndex === detail.toIndex ) ) {
                return;
            }

            const ids    = detail.toOrderedIds.map( Number );
            const at     = ids.indexOf( Number( detail.itemId ) );
            const after  = at > 0 ? ids[ at - 1 ] : null;
            const before = at < ids.length - 1 ? ids[ at + 1 ] : null;
            const revert = () => {
                window.LivewireDragAndDrop?.revert( detail.moveId );
                this.state( detail.itemId, 'reverting' );
                setTimeout( () => this.state( detail.itemId, 'idle' ), 2000 );
            };

            this.state( detail.itemId, 'moving' );

            this.$wire.moveCard( Number( detail.itemId ), Number( detail.toContext ), before, after )
                .then( ( result ) => 'rejected' === result ? revert() : this.state( detail.itemId, 'idle' ) )
                .catch( revert );
        },
    }"
    x-on:ec-kb-focus-card.window="focusCard( $event.detail.orderId )"
>
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

        <div class="flex items-center gap-2">
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

    @if ( [] === $columns )
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
        >
            @foreach ( $columns as $column )
                <x-artisanpack-ec-kb-column
                    :column="$column"
                    :loaded="$this->loaded"
                    :draggable="$canMove"
                    :drag-messages="$dragMessages"
                    wire:key="column-{{ $column['id'] }}"
                />
            @endforeach
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

    <livewire:artisanpack-ecommerce-kanban-card-detail :board-id="$board->id" wire:key="card-detail-{{ $board->id }}" />
</div>
