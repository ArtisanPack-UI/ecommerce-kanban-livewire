{{--
    The board's filter controls (spec §7.5; #47), on the design system.

    Rendered twice by the board view. Inline (medium screens and up) it is a
    Card with `role="search"`: Search, Customer, Tags and Products stay
    visible, and everything else (the placed dates, the payment and
    fulfillment statuses, the satellites' controls and "Stale cards only")
    sits in a second row behind "More filters", which opens by itself when
    one of those is set: on load, and whenever the count goes from none to
    some (say, one set in the drawer before the window widened), read from
    the re-rendered `data-ec-kb-more-count` because Alpine keeps `more`
    across renders. The person can still close it afterwards. While filters
    are on, a footer line says how many orders show and offers Clear
    filters. With `$inDrawer` (small screens) the same fields stack in the
    Filters drawer, which brings its own summary and footer; `$prefix` keeps
    the two sets of ids apart.

    Every control writes a `FiltersCards` property, all of which live in the
    query string. The customer and product comboboxes search on the server
    (`customerSearch` / `productSearch`). `$extraControls` are the controls
    satellites add through `ap.ecommerceKanbanLivewire.filters`: toggles join
    "Stale cards only", selects and inputs join the second row.

    The tags control is keyed by its options, so it is replaced rather than
    morphed when the board's tags load.

    @package    ArtisanPack_UI
    @subpackage EcommerceKanbanLivewire

    @since      1.0.0
--}}
@php
    $inDrawer       = $inDrawer ?? false;
    $extraToggles   = array_values( array_filter( $extraControls, static fn ( array $control ): bool => 'toggle' === $control['type'] ) );
    $extraFields    = array_values( array_filter( $extraControls, static fn ( array $control ): bool => 'toggle' !== $control['type'] ) );
    $moreId         = $prefix . '-more';
    $showingSummary = trans_choice( ':shown of :total order shown|:shown of :total orders shown', $totalCards, [ 'shown' => $matchingCards, 'total' => $totalCards ] );
@endphp
@if ( ! $inDrawer )
    <x-artisanpack-card
        class="ec-kb-filters"
        role="search"
        aria-label="{{ __( 'Filter orders' ) }}"
        data-ec-kb-filters="{{ $prefix }}"
        data-ec-kb-more-count="{{ $moreFilters }}"
        x-data="{ more: {{ $moreFilters > 0 ? 'true' : 'false' }}, count: {{ (int) $moreFilters }} }"
        x-init="new MutationObserver( () => { const next = Number( $el.dataset.ecKbMoreCount ) || 0; if ( 0 === count && next > 0 ) { more = true } count = next } ).observe( $el, { attributes: true, attributeFilter: [ 'data-ec-kb-more-count' ] } )"
    >
        <div class="ec-kb-filters-row">
            @include( 'ecommerce-kanban::partials.filter-fields.primary' )

            <div class="ec-kb-filters-more">
                <x-artisanpack-button
                    variant="outline"
                    color="neutral"
                    size="sm"
                    leading-icon="o-adjustments-horizontal"
                    aria-controls="{{ $moreId }}"
                    aria-expanded="{{ $moreFilters > 0 ? 'true' : 'false' }}"
                    x-bind:aria-expanded="more ? 'true' : 'false'"
                    x-on:click="more = ! more"
                    data-ec-kb-more-filters
                >
                    {{ __( 'More filters' ) }}
                    @if ( $moreFilters > 0 )
                        <span class="ec-kb-filter-count" aria-hidden="true">{{ $moreFilters }}</span>
                        <span class="apui-sr-only">{{ trans_choice( ':count filter active|:count filters active', $moreFilters, [ 'count' => $moreFilters ] ) }}</span>
                    @endif
                </x-artisanpack-button>
            </div>
        </div>

        <div
            id="{{ $moreId }}"
            class="ec-kb-filters-extra"
            role="group"
            aria-label="{{ __( 'More filters' ) }}"
            @if ( 0 === $moreFilters ) hidden @endif
            x-bind:hidden="! more"
            data-ec-kb-filters-extra
        >
            <div class="ec-kb-filters-row">
                @include( 'ecommerce-kanban::partials.filter-fields.dates' )
                @include( 'ecommerce-kanban::partials.filter-fields.secondary' )
            </div>

            <div class="ec-kb-filters-toggles">
                @include( 'ecommerce-kanban::partials.filter-fields.toggles' )
            </div>
        </div>

        @if ( $activeFilters > 0 )
            <div class="ec-kb-filters-footer" data-ec-kb-filters-footer>
                @if ( $this->loaded )
                    <p class="ec-kb-filters-summary" data-ec-kb-filters-summary>{{ $showingSummary }}</p>
                @endif
                <x-artisanpack-button variant="ghost" color="neutral" size="sm" leading-icon="x" wire:click="clearFilters" data-ec-kb-clear-filters>{{ __( 'Clear filters' ) }}</x-artisanpack-button>
            </div>
        @endif
    </x-artisanpack-card>
@else
    <div class="ec-kb-filters-stack" role="search" aria-label="{{ __( 'Filter orders' ) }}" data-ec-kb-filters="{{ $prefix }}">
        @include( 'ecommerce-kanban::partials.filter-fields.primary' )

        <div class="ec-kb-filters-dates">
            @include( 'ecommerce-kanban::partials.filter-fields.dates' )
        </div>

        @include( 'ecommerce-kanban::partials.filter-fields.secondary' )
        @include( 'ecommerce-kanban::partials.filter-fields.toggles' )
    </div>
@endif
