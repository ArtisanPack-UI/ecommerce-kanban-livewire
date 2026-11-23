{{--
    The filter bar's status fields and the satellites' select and text
    controls. Included by `partials.filter-bar` with its `$prefix`.

    @package    ArtisanPack_UI
    @subpackage EcommerceKanbanLivewire

    @since      2.0.0
--}}
<div>
    <x-artisanpack-combobox
        id="{{ $prefix }}-payment"
        multiple
        wire:model.live="paymentStatus"
        :options="\ArtisanPackUI\EcommerceKanbanLivewire\Support\ControlOptions::from( $paymentOptions )"
        :label="__( 'Payment status' )"
        :placeholder="__( 'Any' )"
        clearable
        data-ec-kb-filter="payment"
    />
</div>

<div>
    <x-artisanpack-combobox
        id="{{ $prefix }}-fulfillment"
        multiple
        wire:model.live="fulfillmentStatus"
        :options="\ArtisanPackUI\EcommerceKanbanLivewire\Support\ControlOptions::from( $fulfillmentOptions )"
        :label="__( 'Fulfillment status' )"
        :placeholder="__( 'Any' )"
        clearable
        data-ec-kb-filter="fulfillment"
    />
</div>

@foreach ( $extraFields as $control )
    @php( $controlId = $prefix . '-extra-' . $control['key'] )
    <div wire:key="{{ $controlId }}">
        @if ( 'select' === $control['type'] )
            <x-artisanpack-select
                id="{{ $controlId }}"
                wire:model.live="extraFilters.{{ $control['key'] }}"
                :label="$control['label']"
                :options="\ArtisanPackUI\EcommerceKanbanLivewire\Support\ControlOptions::from( $control['options'] )"
                :placeholder="__( 'Any' )"
                data-ec-kb-filter="{{ $control['key'] }}"
            />
        @else
            <x-artisanpack-input
                id="{{ $controlId }}"
                wire:model.live.debounce.400ms="extraFilters.{{ $control['key'] }}"
                :label="$control['label']"
                data-ec-kb-filter="{{ $control['key'] }}"
            />
        @endif
    </div>
@endforeach
