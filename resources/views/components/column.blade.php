{{--
    `x-artisanpack-ec-kb-column`: one board column (spec §8; #46). A
    labelled region with the header (collapse toggle, a dot in the column's
    color, icon, label, "N shown" while filtered, the WIP badge, the
    automations count) and the card list. A collapsed column narrows to its
    header, with the label set vertically, and loads no cards. The surface is
    token-only (see resources/css/kanban.css); the controls are design-system
    Buttons, Badges and Skeletons.

    `$column` is the board component's column array (`id`, `label`, `color`,
    `icon`, `wip_limit`, `count` (every card), `matching` (cards the filters
    leave, or null without filters), `exit`, `collapsed`, `loaded`, `lazy`,
    `automations`, `cards`, `has_more`).

    A column whose cards haven't loaded shows two skeleton cards. When it is `lazy`
    (the board has loaded, this column hasn't scrolled into view yet), the
    skeletons dispatch `ec-kb-column-visible` once they come into view and
    the board loads the column.

    When automations fire into the column, the header shows how many; the
    count opens the automations panel for the column.

    The card list is a named group either way. When `$draggable`, it is
    also a livewire-drag-and-drop context in
    the `cards` group, so cards move between columns; drops and drag ends
    go to the board's `drop()` / `end()` (see the board view), and
    `$dragMessages` are the translated announcement templates.

    When `$editing` (the board's "Edit board" mode), the header carries the
    column's edit controls: move left / right (`$first` / `$last` disable
    them at the ends), edit, card widgets, and remove. The board passes the
    outer drag context's item attributes through `$attributes`, so the
    column itself can be dragged to a new place. The explicit `region` role
    keeps livewire-drag-and-drop from making the column a button, since it
    holds the column's controls and cards.

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
    $headingId   = 'ec-kb-column-' . $column['id'] . '-title';
    $listId      = 'ec-kb-column-' . $column['id'] . '-cards';
    $collapsed   = (bool) $column['collapsed'];
    $automations = (int) ( $column['automations'] ?? 0 );
@endphp
<section
    {{ $attributes->class( [ 'ec-kb-column' ] ) }}
    role="region"
    aria-labelledby="{{ $headingId }}"
    data-ec-kb-column="{{ $column['id'] }}"
    @if ( $collapsed ) data-ec-kb-collapsed @endif
>
    <header class="ec-kb-column-header">
        <x-artisanpack-button
            variant="ghost"
            color="neutral"
            size="xs"
            icon-only
            :leading-icon="$collapsed ? 'chevron-right' : 'chevron-down'"
            wire:click="toggleColumn( {{ $column['id'] }} )"
            aria-expanded="{{ $collapsed ? 'false' : 'true' }}"
            aria-controls="{{ $listId }}"
            aria-label="{{ $collapsed ? __( 'Expand :column', [ 'column' => $column['label'] ] ) : __( 'Collapse :column', [ 'column' => $column['label'] ] ) }}"
            data-ec-kb-collapse
        />

        <span class="ec-kb-column-dot" aria-hidden="true" @if ( null !== $column['color'] ) style="--ec-kb-column-color: {{ $column['color'] }}" @endif data-ec-kb-column-color></span>

        @if ( null !== $column['icon'] )
            <x-artisanpack-icon :name="$column['icon']" class="ec-kb-column-icon" size="sm" />
        @endif

        <h2 id="{{ $headingId }}" class="ec-kb-column-title">{{ $column['label'] }}</h2>

        @if ( null !== $column['count'] )
            @if ( null !== $column['matching'] && $column['matching'] !== $column['count'] && ! $collapsed )
                <span class="ec-kb-column-matching" data-ec-kb-matching>{{ trans_choice( ':count shown|:count shown', $column['matching'], [ 'count' => $column['matching'] ] ) }}</span>
            @endif
            <x-artisanpack-ec-kb-wip :count="$column['count']" :limit="$column['wip_limit']" />
        @endif

        @if ( $automations > 0 && ! $collapsed )
            <x-artisanpack-button
                variant="ghost"
                color="neutral"
                size="xs"
                leading-icon="o-bolt"
                x-on:click="$dispatch( 'ec-kb-edit-automations', { columnId: {{ $column['id'] }} } )"
                aria-label="{{ trans_choice( ':count automation runs when a card moves into :column|:count automations run when a card moves into :column', $automations, [ 'count' => $automations, 'column' => $column['label'] ] ) }}"
                data-ec-kb-column-automations="{{ $automations }}"
            >{{ $automations }}</x-artisanpack-button>
        @endif
    </header>

    @if ( $editing )
        <div class="ec-kb-column-tools" role="toolbar" aria-label="{{ __( 'Edit :column', [ 'column' => $column['label'] ] ) }}" data-ec-kb-column-tools>
            <x-artisanpack-tooltip :content="__( 'Move left' )"><x-artisanpack-button
                variant="ghost" color="neutral" size="sm" icon-only
                leading-icon="arrow-left"
                wire:click="moveColumn( {{ $column['id'] }}, -1 )"
                :disabled="$first"
                aria-label="{{ __( 'Move :column left', [ 'column' => $column['label'] ] ) }}"
                data-ec-kb-move-column="left"
            /></x-artisanpack-tooltip>
            <x-artisanpack-tooltip :content="__( 'Move right' )"><x-artisanpack-button
                variant="ghost" color="neutral" size="sm" icon-only
                leading-icon="arrow-right"
                wire:click="moveColumn( {{ $column['id'] }}, 1 )"
                :disabled="$last"
                aria-label="{{ __( 'Move :column right', [ 'column' => $column['label'] ] ) }}"
                data-ec-kb-move-column="right"
            /></x-artisanpack-tooltip>
            <x-artisanpack-tooltip :content="__( 'Edit column' )"><x-artisanpack-button
                variant="ghost" color="neutral" size="sm" icon-only
                leading-icon="pencil"
                wire:click="editColumn( {{ $column['id'] }} )"
                aria-label="{{ __( 'Edit :column', [ 'column' => $column['label'] ] ) }}"
                data-ec-kb-edit-column
            /></x-artisanpack-tooltip>
            <x-artisanpack-tooltip :content="__( 'Card widgets' )"><x-artisanpack-button
                variant="ghost" color="neutral" size="sm" icon-only
                leading-icon="o-squares-plus"
                x-on:click="$dispatch( 'ec-kb-edit-widgets', { columnId: {{ $column['id'] }} } )"
                aria-label="{{ __( 'Card widgets for :column', [ 'column' => $column['label'] ] ) }}"
                data-ec-kb-column-widgets
            /></x-artisanpack-tooltip>
            <x-artisanpack-tooltip :content="__( 'Automations' )"><x-artisanpack-button
                variant="ghost" color="neutral" size="sm" icon-only
                leading-icon="o-bolt"
                x-on:click="$dispatch( 'ec-kb-edit-automations', { columnId: {{ $column['id'] }} } )"
                aria-label="{{ __( 'Automations for :column', [ 'column' => $column['label'] ] ) }}"
                data-ec-kb-column-automations-edit
            /></x-artisanpack-tooltip>
            <x-artisanpack-tooltip :content="__( 'Remove column' )"><x-artisanpack-button
                variant="ghost" color="error" size="sm" icon-only
                leading-icon="trash"
                wire:click="confirmDeleteColumn( {{ $column['id'] }} )"
                aria-label="{{ __( 'Remove :column', [ 'column' => $column['label'] ] ) }}"
                data-ec-kb-delete-column
            /></x-artisanpack-tooltip>
        </div>
    @endif

    <div id="{{ $listId }}" class="ec-kb-column-body" @if ( $collapsed ) hidden @endif>
        @if ( ! $collapsed )
            @if ( ! $loaded )
                <div
                    class="ec-kb-column-loading"
                    aria-hidden="true"
                    data-ec-kb-loading
                    @if ( $column['lazy'] ?? false )
                        x-intersect.once="$dispatch( 'ec-kb-column-visible', { columnId: {{ $column['id'] }} } )"
                        data-ec-kb-lazy
                    @endif
                >
                    <x-artisanpack-skeleton />
                    <x-artisanpack-skeleton />
                </div>
            @else
                <div
                    class="ec-kb-column-cards"
                    role="group"
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
                            :icon="null === $column['matching'] ? 'o-cube' : 'o-funnel'"
                            :title="null === $column['matching'] ? __( 'No orders in this column' ) : __( 'No matching orders in this column' )"
                            data-ec-kb-empty-column
                        />
                    @endif
                </div>

                {{-- Focus moves to the first card of the new page, so it isn't lost when the button goes. --}}
                @if ( $column['has_more'] )
                    <x-artisanpack-button
                        variant="ghost"
                        color="neutral"
                        size="sm"
                        class="ec-kb-column-more"
                        x-on:click="const shown = {{ count( $column['cards'] ) }}; const list = $el.closest( 'section' ); $wire.showMore( {{ $column['id'] }} ).then( () => setTimeout( () => list.querySelectorAll( '[data-ec-kb-card]' )[ shown ]?.focus(), 50 ) )"
                        wire:loading.attr="aria-busy"
                        wire:target="showMore( {{ $column['id'] }} )"
                        data-ec-kb-show-more
                    >{{ __( 'Show more' ) }}</x-artisanpack-button>
                @endif
            @endif
        @endif
    </div>
</section>
