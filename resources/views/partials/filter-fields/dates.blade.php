{{--
    The filter bar's placed-date range: two date pickers, each with its own
    visible label. Included by `partials.filter-bar` with its `$prefix`.

    @package    ArtisanPack_UI
    @subpackage EcommerceKanbanLivewire

    @since      2.0.0
--}}
<div>
    <x-artisanpack-date-picker
        id="{{ $prefix }}-placed-from"
        wire:model.live="placedFrom"
        :label="__( 'Placed from' )"
        :placeholder="__( 'Any date' )"
        data-ec-kb-filter="placed-from"
    />
</div>

<div>
    <x-artisanpack-date-picker
        id="{{ $prefix }}-placed-to"
        wire:model.live="placedTo"
        :label="__( 'Placed to' )"
        :placeholder="__( 'Any date' )"
        data-ec-kb-filter="placed-to"
    />
</div>
