{{--
    `x-artisanpack-ec-kb-column`: one board column (spec §8). A labelled
    region with the header (colour, icon, label, count / WIP limit, collapse
    toggle) and the card list. A collapsed column shows only its header and
    loads no cards.

    `$column` is the board component's column array (`id`, `label`, `color`,
    `icon`, `wip_limit`, `count`, `collapsed`, `cards`, `has_more`).

    @package    ArtisanPack_UI
    @subpackage EcommerceKanbanLivewire

    @since      1.0.0
--}}
@props( [
    'column',
    'loaded' => true,
] )
@php
    $headingId = 'ec-kb-column-' . $column['id'] . '-title';
    $listId    = 'ec-kb-column-' . $column['id'] . '-cards';
    $collapsed = (bool) $column['collapsed'];
@endphp
<section
    {{ $attributes->class( [ 'flex shrink-0 snap-start flex-col rounded-box bg-base-100 shadow-sm', 'w-80 max-w-[85vw]' => ! $collapsed, 'w-14' => $collapsed ] ) }}
    aria-labelledby="{{ $headingId }}"
    data-ec-kb-column="{{ $column['id'] }}"
    @if ( $collapsed ) data-ec-kb-collapsed @endif
>
    <header
        @class( [ 'flex gap-2 border-t-4 p-3', 'items-center' => ! $collapsed, 'flex-col items-center' => $collapsed ] )
        style="border-top-color: {{ $column['color'] ?? 'transparent' }}"
    >
        <button
            type="button"
            class="btn btn-ghost btn-xs btn-square"
            wire:click="toggleColumn( {{ $column['id'] }} )"
            aria-expanded="{{ $collapsed ? 'false' : 'true' }}"
            aria-controls="{{ $listId }}"
            data-ec-kb-collapse
        >
            <x-artisanpack-icon :name="$collapsed ? 'o-chevron-right' : 'o-chevron-down'" class="size-4" aria-hidden="true" />
            <span class="sr-only">{{ $collapsed ? __( 'Expand :column', [ 'column' => $column['label'] ] ) : __( 'Collapse :column', [ 'column' => $column['label'] ] ) }}</span>
        </button>

        @if ( null !== $column['icon'] )
            <x-artisanpack-icon :name="$column['icon']" class="size-4 shrink-0" aria-hidden="true" />
        @endif

        <h2
            id="{{ $headingId }}"
            @class( [ 'font-semibold', 'min-w-0 flex-1 truncate' => ! $collapsed, '[writing-mode:vertical-rl]' => $collapsed ] )
        >{{ $column['label'] }}</h2>

        @if ( null !== $column['count'] )
            <x-artisanpack-ec-kb-wip :count="$column['count']" :limit="$column['wip_limit']" />
        @endif
    </header>

    <div id="{{ $listId }}" @class( [ 'flex flex-col gap-2 px-3 pb-3', 'hidden' => $collapsed ] )>
        @if ( ! $collapsed )
            @if ( ! $loaded )
                <div class="flex flex-col gap-2" aria-hidden="true" data-ec-kb-loading>
                    <div class="skeleton h-20 w-full"></div>
                    <div class="skeleton h-20 w-full"></div>
                </div>
            @elseif ( [] === $column['cards'] )
                <x-artisanpack-ec-kb-empty compact :title="__( 'No orders in this column' )" data-ec-kb-empty-column />
            @else
                <ul class="flex flex-col gap-2" aria-label="{{ __( 'Orders in :column', [ 'column' => $column['label'] ] ) }}">
                    @foreach ( $column['cards'] as $card )
                        <li wire:key="card-{{ $card['order_id'] }}">
                            <x-artisanpack-ec-kb-card :card="$card" />
                        </li>
                    @endforeach
                </ul>

                @if ( $column['has_more'] )
                    <x-artisanpack-button
                        class="btn-sm btn-ghost w-full"
                        wire:click="showMore( {{ $column['id'] }} )"
                        spinner="showMore( {{ $column['id'] }} )"
                        :label="__( 'Show more' )"
                        data-ec-kb-show-more
                    />
                @endif
            @endif
        @endif
    </div>
</section>
