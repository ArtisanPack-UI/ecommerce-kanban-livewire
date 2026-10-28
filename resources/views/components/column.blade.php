{{--
    `x-artisanpack-ec-kb-column`: one board column (spec §8). A labelled
    region with the header (colour, icon, label, count / WIP limit, collapse
    toggle) and the card list. A collapsed column shows only its header and
    loads no cards.

    `$column` is the board component's column array (`id`, `label`, `color`,
    `icon`, `wip_limit`, `count` (every card), `matching` (cards the filters
    leave, or null without filters), `exit`, `collapsed`, `cards`,
    `has_more`).

    When `$draggable`, the card list is a livewire-drag-and-drop context in
    the `cards` group, so cards move between columns; drops and drag ends
    go to the board's `drop()` / `end()` (see the board view), and
    `$dragMessages` are the translated announcement templates.

    When `$editing` (the board's "Edit board" mode), the header carries the
    column's edit controls: move left / right (`$first` / `$last` disable
    them at the ends), edit, card widgets, and remove. The board passes the
    outer drag context's item attributes through `$attributes`, so the
    column itself can be dragged to a new place.

    @package    ArtisanPack_UI
    @subpackage EcommerceKanbanLivewire

    @since      1.0.0
--}}
@props( [
    'column',
    'loaded'       => true,
    'draggable'    => false,
    'dragMessages' => [],
    'editing'      => false,
    'first'        => false,
    'last'         => false,
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
            @if ( null !== $column['matching'] && $column['matching'] !== $column['count'] && ! $collapsed )
                <span class="text-xs text-base-content/70 whitespace-nowrap" data-ec-kb-matching>{{ trans_choice( ':count shown|:count shown', $column['matching'], [ 'count' => $column['matching'] ] ) }}</span>
            @endif
            <x-artisanpack-ec-kb-wip :count="$column['count']" :limit="$column['wip_limit']" />
        @endif
    </header>

    @if ( $editing )
        <div @class( [ 'flex flex-wrap items-center gap-1 border-b border-base-300 px-3 pb-2', 'flex-col' => $collapsed ] ) role="toolbar" aria-label="{{ __( 'Edit :column', [ 'column' => $column['label'] ] ) }}" data-ec-kb-column-tools>
            <x-artisanpack-button
                variant="ghost" size="sm" class="btn-square gap-0"
                icon="o-arrow-left"
                wire:click="moveColumn( {{ $column['id'] }}, -1 )"
                :disabled="$first"
                :tooltip="__( 'Move left' )"
                aria-label="{{ __( 'Move :column left', [ 'column' => $column['label'] ] ) }}"
                data-ec-kb-move-column="left"
            />
            <x-artisanpack-button
                variant="ghost" size="sm" class="btn-square gap-0"
                icon="o-arrow-right"
                wire:click="moveColumn( {{ $column['id'] }}, 1 )"
                :disabled="$last"
                :tooltip="__( 'Move right' )"
                aria-label="{{ __( 'Move :column right', [ 'column' => $column['label'] ] ) }}"
                data-ec-kb-move-column="right"
            />
            <x-artisanpack-button
                variant="ghost" size="sm" class="btn-square gap-0"
                icon="o-pencil-square"
                wire:click="editColumn( {{ $column['id'] }} )"
                :tooltip="__( 'Edit column' )"
                aria-label="{{ __( 'Edit :column', [ 'column' => $column['label'] ] ) }}"
                data-ec-kb-edit-column
            />
            <x-artisanpack-button
                variant="ghost" size="sm" class="btn-square gap-0"
                icon="o-squares-plus"
                x-on:click="$dispatch( 'ec-kb-edit-widgets', { columnId: {{ $column['id'] }} } )"
                :tooltip="__( 'Card widgets' )"
                aria-label="{{ __( 'Card widgets for :column', [ 'column' => $column['label'] ] ) }}"
                data-ec-kb-column-widgets
            />
            <x-artisanpack-button
                variant="ghost" size="sm" class="btn-square gap-0 text-error"
                icon="o-trash"
                wire:click="confirmDeleteColumn( {{ $column['id'] }} )"
                :tooltip="__( 'Remove column' )"
                aria-label="{{ __( 'Remove :column', [ 'column' => $column['label'] ] ) }}"
                data-ec-kb-delete-column
            />
        </div>
    @endif

    <div id="{{ $listId }}" @class( [ 'flex flex-col gap-2 px-3 pb-3', 'hidden' => $collapsed ] )>
        @if ( ! $collapsed )
            @if ( ! $loaded )
                <div class="flex flex-col gap-2" aria-hidden="true" data-ec-kb-loading>
                    <div class="skeleton h-20 w-full"></div>
                    <div class="skeleton h-20 w-full"></div>
                </div>
            @else
                <div
                    class="group flex min-h-16 flex-col gap-2 rounded-box focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary"
                    aria-label="{{ __( 'Orders in :column', [ 'column' => $column['label'] ] ) }}"
                    data-ec-kb-cards="{{ $column['id'] }}"
                    wire:key="column-{{ $column['id'] }}-cards"
                    @if ( $draggable )
                        x-drag-context
                        data-drag-group="cards"
                        data-drag-context-id="{{ $column['id'] }}"
                        data-drag-label="{{ $column['label'] }}"
                        data-drag-messages="{{ json_encode( $dragMessages ) }}"
                        data-ec-kb-total="{{ (int) $column['count'] }}"
                        data-ec-kb-limit="{{ $column['wip_limit'] ?? '' }}"
                        data-ec-kb-full="{{ __( ':column is at its WIP limit.', [ 'column' => $column['label'] ] ) }}"
                        x-on:drag:start="dragStarted( $event, $el )"
                        x-on:drag:cancel="dragStopped( $event, $el )"
                        x-on:drag:drop="drop( $event, $el )"
                        x-on:drag:end="end( $event, $el )"
                    @endif
                >
                    @foreach ( $column['cards'] as $card )
                        @include( 'ecommerce-kanban::partials.card-item', [ 'card' => $card, 'draggable' => $draggable ] )
                    @endforeach

                    @if ( [] === $column['cards'] )
                        <x-artisanpack-ec-kb-empty
                            compact
                            :title="null === $column['matching'] ? __( 'No orders in this column' ) : __( 'No matching orders in this column' )"
                            class="group-has-[[data-ec-kb-card]]:hidden"
                            data-ec-kb-empty-column
                        />
                    @endif
                </div>

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
