{{--
    The board picker (spec §7.1): a PageHeader over an auto-fill grid of
    Cards, one per board. A board with a URL is an interactive Card whose
    title link stretches over it, so the card is one tab stop and the focus
    ring goes around the whole card; a board without one is a plain Card.

    @package    ArtisanPack_UI
    @subpackage EcommerceKanbanLivewire

    @since      1.0.0
--}}
@php
    use ArtisanPackUI\EcommerceKanbanLivewire\Support\BoardUrls;
@endphp
<div class="ec-kb-picker" data-ec-kb-picker>
    <x-artisanpack-page-header
        :title="__( 'Kanban boards' )"
        :description="$boards->isEmpty() ? null : __( 'Pick a board to work orders through its columns.' )"
    />

    @if ( $boards->isEmpty() )
        <x-artisanpack-ec-kb-empty
            live
            first-use
            icon="o-view-columns"
            :title="__( 'No boards yet' )"
            :description="__( 'A board groups orders into columns by fulfillment step. Create one to start working orders here.' )"
            data-ec-kb-no-boards
        >
            @if ( null !== $createBoardUrl )
                <x-artisanpack-button :href="$createBoardUrl" leading-icon="plus" data-ec-kb-create-board>{{ __( 'Create board' ) }}</x-artisanpack-button>
            @endif
        </x-artisanpack-ec-kb-empty>
    @else
        {{-- Card titles are h3s: this heading keeps the outline in order and names the list. --}}
        <h2 class="apui-sr-only" id="ec-kb-picker-boards">{{ __( 'Boards' ) }}</h2>
        <ul class="ec-kb-picker-grid" aria-labelledby="ec-kb-picker-boards">
            @foreach ( $boards as $board )
                @php
                    $boardUrl   = BoardUrls::show( $board );
                    $lastOpened = $lastBoardId === (int) $board->id;
                @endphp
                <li wire:key="board-{{ $board->id }}">
                    <x-artisanpack-card
                        class="ec-kb-picker-card"
                        :classes="[ 'body' => 'ec-kb-picker-card-body' ]"
                        :title="$board->name"
                        :href="$boardUrl"
                        :description="filled( $board->description ) ? $board->description : null"
                        data-ec-kb-board="{{ $board->key }}"
                    >
                        <x-slot:actions>
                            <x-artisanpack-badge size="sm" data-ec-kb-board-count>{{ trans_choice( ':count card|:count cards', (int) $board->cards_count, [ 'count' => (int) $board->cards_count ] ) }}</x-artisanpack-badge>
                        </x-slot:actions>

                        @if ( $lastOpened )
                            <p class="ec-kb-picker-last" data-ec-kb-last-board>
                                <x-artisanpack-icon name="o-clock" size="sm" />
                                <span>{{ __( 'Last opened' ) }}</span>
                            </p>
                        @endif
                    </x-artisanpack-card>
                </li>
            @endforeach
        </ul>
    @endif
</div>
