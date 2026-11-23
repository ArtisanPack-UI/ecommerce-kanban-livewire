{{--
    The filter bar's always-visible fields: search, customer, tags and
    products. Included by `partials.filter-bar` with its `$prefix`.

    @package    ArtisanPack_UI
    @subpackage EcommerceKanbanLivewire

    @since      2.0.0
--}}
<div class="ec-kb-filters-search">
    <x-artisanpack-input
        id="{{ $prefix }}-search"
        type="search"
        wire:model.live.debounce.400ms="search"
        :label="__( 'Search' )"
        :placeholder="__( 'Order number, email, or name' )"
        leading-icon="search"
        data-ec-kb-filter="search"
    />
</div>

<div>
    <x-artisanpack-combobox
        id="{{ $prefix }}-customer"
        wire:model.live="customerId"
        :options="\ArtisanPackUI\EcommerceKanbanLivewire\Support\ControlOptions::from( $customerOptions )"
        :label="__( 'Customer' )"
        :placeholder="__( 'Search customers' )"
        :empty-label="__( 'No customers found.' )"
        remote
        search-model="customerSearch"
        clearable
        data-ec-kb-filter="customer"
    />
</div>

<div wire:key="{{ $prefix }}-tags-{{ md5( (string) json_encode( $tagOptions ) ) }}">
    <x-artisanpack-combobox
        id="{{ $prefix }}-tags"
        multiple
        wire:model.live="tags"
        :options="\ArtisanPackUI\EcommerceKanbanLivewire\Support\ControlOptions::from( $tagOptions )"
        :label="__( 'Tags' )"
        :placeholder="__( 'Any tag' )"
        :empty-label="__( 'No tags on this board.' )"
        clearable
        data-ec-kb-filter="tags"
    />
</div>

<div>
    <x-artisanpack-combobox
        id="{{ $prefix }}-products"
        multiple
        wire:model.live="productIds"
        :options="\ArtisanPackUI\EcommerceKanbanLivewire\Support\ControlOptions::from( $productOptions )"
        :label="__( 'Products' )"
        :placeholder="__( 'Search products' )"
        :empty-label="__( 'No products found.' )"
        remote
        search-model="productSearch"
        clearable
        data-ec-kb-filter="products"
    />
</div>
