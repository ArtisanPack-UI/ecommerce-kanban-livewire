{{--
    The board picker (spec §7.1).

    @package    ArtisanPack_UI
    @subpackage EcommerceKanbanLivewire

    @since      1.0.0
--}}
@php
    use ArtisanPackUI\EcommerceKanbanLivewire\Support\BoardUrls;
@endphp
<div class="mx-auto flex max-w-4xl flex-col gap-4" data-ec-kb-picker>
    <x-artisanpack-heading level="1">{{ __( 'Kanban boards' ) }}</x-artisanpack-heading>

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
        <ul class="grid gap-3 sm:grid-cols-2" aria-label="{{ __( 'Boards' ) }}">
            @foreach ( $boards as $board )
                @php( $boardUrl = BoardUrls::show( $board ) )
                <li wire:key="board-{{ $board->id }}">
                    <x-artisanpack-card class="relative h-full" :interactive="null !== $boardUrl" data-ec-kb-board="{{ $board->key }}">
                        <div class="flex flex-col gap-2">
                            <div class="flex items-start justify-between gap-2">
                                <h2 class="text-lg font-semibold">
                                    @if ( null !== $boardUrl )
                                        <a href="{{ $boardUrl }}" class="cursor-pointer after:absolute after:inset-0">{{ $board->name }}</a>
                                    @else
                                        {{ $board->name }}
                                    @endif
                                </h2>
                                <x-artisanpack-badge size="sm" class="whitespace-nowrap">{{ trans_choice( ':count card|:count cards', (int) $board->cards_count, [ 'count' => (int) $board->cards_count ] ) }}</x-artisanpack-badge>
                            </div>

                            @if ( filled( $board->description ) )
                                <p class="text-sm ec-kb-muted">{{ $board->description }}</p>
                            @endif

                            @if ( $lastBoardId === (int) $board->id )
                                <p class="text-xs ec-kb-muted" data-ec-kb-last-board>{{ __( 'Last opened' ) }}</p>
                            @endif
                        </div>
                    </x-artisanpack-card>
                </li>
            @endforeach
        </ul>
    @endif
</div>
