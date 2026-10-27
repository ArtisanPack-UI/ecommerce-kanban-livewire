{{--
    `x-artisanpack-ec-kb-empty`: an empty state (no boards, an empty column,
    no matches). Composed here until livewire-ui-components ships one (#111);
    the swap is this file.

    @package    ArtisanPack_UI
    @subpackage EcommerceKanbanLivewire

    @since      1.0.0
--}}
@props( [
    'title',
    'description' => null,
    'icon'        => null,
    'compact'     => false,
] )
<div {{ $attributes->class( [ 'flex flex-col items-center gap-2 text-center text-base-content/70', 'rounded-box border border-dashed border-base-300 p-8' => ! $compact, 'p-4 text-sm' => $compact ] ) }} role="status">
    @if ( null !== $icon )
        <x-artisanpack-icon :name="$icon" @class( [ 'size-10' => ! $compact, 'size-6' => $compact ] ) aria-hidden="true" />
    @endif

    <p @class( [ 'font-semibold text-base-content', 'text-lg' => ! $compact ] )>{{ $title }}</p>

    @if ( filled( $description ) )
        <p>{{ $description }}</p>
    @endif

    @if ( $slot->isNotEmpty() )
        <div class="mt-2 flex flex-wrap justify-center gap-2">{{ $slot }}</div>
    @endif
</div>
