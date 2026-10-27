{{--
    The board's filter controls (spec §7.5). Rendered twice by the board
    view: inline above the columns on medium screens and up, and inside a
    drawer on small screens; `$prefix` keeps the two sets of ids apart.

    Every control writes a `FiltersCards` property, all of which live in the
    query string. `$extraControls` are the controls satellites add through
    `ap.ecommerceKanbanLivewire.filters`.

    @package    ArtisanPack_UI
    @subpackage EcommerceKanbanLivewire

    @since      1.0.0
--}}
<div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4" role="search" aria-label="{{ __( 'Filter orders' ) }}" data-ec-kb-filters="{{ $prefix }}">
    <x-artisanpack-input
        id="{{ $prefix }}-search"
        type="search"
        wire:model.live.debounce.400ms="search"
        :label="__( 'Search' )"
        :placeholder="__( 'Order number, email, or name' )"
        icon="o-magnifying-glass"
        data-ec-kb-filter="search"
    />

    <x-artisanpack-choices
        id="{{ $prefix }}-customer"
        wire:model.live="customerId"
        :options="$customerOptions"
        :label="__( 'Customer' )"
        :placeholder="__( 'Search customers' )"
        :no-result-text="__( 'No customers found.' )"
        search-function="searchCustomers"
        single
        searchable
        clearable
        data-ec-kb-filter="customer"
    />

    <x-artisanpack-choices-offline
        id="{{ $prefix }}-tags"
        wire:model.live="tags"
        :options="$tagOptions"
        :label="__( 'Tags' )"
        :no-result-text="__( 'No tags on this board.' )"
        searchable
        clearable
        data-ec-kb-filter="tags"
    />

    <x-artisanpack-choices
        id="{{ $prefix }}-products"
        wire:model.live="productIds"
        :options="$productOptions"
        :label="__( 'Products' )"
        :placeholder="__( 'Search products' )"
        :no-result-text="__( 'No products found.' )"
        search-function="searchProducts"
        searchable
        clearable
        data-ec-kb-filter="products"
    />

    <x-artisanpack-datetime
        id="{{ $prefix }}-placed-from"
        type="date"
        wire:model.live="placedFrom"
        :label="__( 'Placed from' )"
        data-ec-kb-filter="placed-from"
    />

    <x-artisanpack-datetime
        id="{{ $prefix }}-placed-to"
        type="date"
        wire:model.live="placedTo"
        :label="__( 'Placed to' )"
        data-ec-kb-filter="placed-to"
    />

    <x-artisanpack-choices-offline
        id="{{ $prefix }}-payment"
        wire:model.live="paymentStatus"
        :options="$paymentOptions"
        :label="__( 'Payment status' )"
        clearable
        data-ec-kb-filter="payment"
    />

    <x-artisanpack-choices-offline
        id="{{ $prefix }}-fulfillment"
        wire:model.live="fulfillmentStatus"
        :options="$fulfillmentOptions"
        :label="__( 'Fulfillment status' )"
        clearable
        data-ec-kb-filter="fulfillment"
    />

    @foreach ( $extraControls as $control )
        @php( $controlId = $prefix . '-extra-' . $control['key'] )
        @if ( 'toggle' === $control['type'] )
            <x-artisanpack-toggle id="{{ $controlId }}" wire:model.live="extraFilters.{{ $control['key'] }}" :label="$control['label']" data-ec-kb-filter="{{ $control['key'] }}" />
        @elseif ( 'select' === $control['type'] )
            <x-artisanpack-select
                id="{{ $controlId }}"
                wire:model.live="extraFilters.{{ $control['key'] }}"
                :label="$control['label']"
                :options="$control['options']"
                :placeholder="__( 'Any' )"
                data-ec-kb-filter="{{ $control['key'] }}"
            />
        @else
            <x-artisanpack-input id="{{ $controlId }}" wire:model.live.debounce.400ms="extraFilters.{{ $control['key'] }}" :label="$control['label']" data-ec-kb-filter="{{ $control['key'] }}" />
        @endif
    @endforeach

    <div class="flex items-end gap-3">
        <x-artisanpack-toggle id="{{ $prefix }}-stale" wire:model.live="stale" :label="__( 'Stale cards only' )" data-ec-kb-filter="stale" />
    </div>
</div>
