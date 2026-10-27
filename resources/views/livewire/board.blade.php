{{--
    A board (spec §7.2): a header with the board switcher, then the columns
    left to right in a horizontally scrolling strip.

    The collapsed columns are remembered per board in `localStorage` under
    `$storageKey`; the first render draws only the column headers, then
    `loadBoard()` loads the cards of the columns left open.

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
            $wire.loadBoard( this.read() ).then( () => $wire.$watch( 'collapsed', ( ids ) => this.write( ids ) ) );
        },
    }"
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
                                wire:key="switch-{{ $option->id }}"
                            />
                        @endif
                    @endforeach

                    @if ( null !== $pickerUrl )
                        <x-artisanpack-menu-separator />
                        <x-artisanpack-menu-item :title="__( 'All boards' )" icon="o-squares-2x2" :link="$pickerUrl" no-wire-navigate />
                    @endif
                </x-artisanpack-dropdown>
            @endif
        </div>

        @if ( filled( $board->description ) )
            <p class="w-full text-sm text-base-content/70">{{ $board->description }}</p>
        @endif
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
                <x-artisanpack-ec-kb-column :column="$column" :loaded="$this->loaded" wire:key="column-{{ $column['id'] }}" />
            @endforeach
        </div>
    @endif
</div>
