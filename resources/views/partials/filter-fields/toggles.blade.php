{{--
    The filter bar's switches: the satellites' toggle controls, then "Stale
    cards only". Included by `partials.filter-bar` with its `$prefix`.

    @package    ArtisanPack_UI
    @subpackage EcommerceKanbanLivewire

    @since      2.0.0
--}}
@foreach ( $extraToggles as $control )
    @php( $controlId = $prefix . '-extra-' . $control['key'] )
    <div wire:key="{{ $controlId }}">
        <x-artisanpack-toggle id="{{ $controlId }}" wire:model.live="extraFilters.{{ $control['key'] }}" :label="$control['label']" data-ec-kb-filter="{{ $control['key'] }}" />
    </div>
@endforeach

<div>
    <x-artisanpack-toggle id="{{ $prefix }}-stale" wire:model.live="stale" :label="__( 'Stale cards only' )" data-ec-kb-filter="stale" />
</div>
