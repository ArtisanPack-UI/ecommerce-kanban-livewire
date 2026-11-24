{{--
    `x-artisanpack-ec-kb-empty`: an empty state in two sizes (#45).

    Full (the default) is the design system's EmptyState inside a Card, for
    whole-page empties (no boards yet, a board with no columns): an icon
    tile, the title, the description and, from the default slot, the
    actions. The tile is primary with `first-use` (nothing has been made
    yet) and neutral otherwise.

    `compact` is the in-column form: a dashed, token-only box with a small
    icon and one muted line. Its dashed edge doubles as the drop-target hint,
    and the column hides it as soon as a card lands.

    Pass `live` to make it a status message. Only the page-level empty
    states do: a board re-renders many columns at once, and an empty
    column each announcing itself would be noise (the board announces how
    many orders match the filters instead).

    @package    ArtisanPack_UI
    @subpackage EcommerceKanbanLivewire

    @since      1.0.0
--}}
@props( [
    'title',
    'description' => null,
    'icon'        => null,
    'compact'     => false,
    'live'        => false,
    'firstUse'    => false,
] )
@if ( $compact )
    <div {{ $attributes->class( [ 'ec-kb-empty' ] ) }} data-ec-kb-compact @if ( $live ) role="status" @endif>
        @if ( null !== $icon )
            <x-artisanpack-icon :name="$icon" size="lg" />
        @endif

        <p>{{ $title }}</p>

        @if ( filled( $description ) )
            <p>{{ $description }}</p>
        @endif

        @if ( $slot->isNotEmpty() )
            <div>{{ $slot }}</div>
        @endif
    </div>
@else
    <x-artisanpack-card {{ $attributes->class( [ 'ec-kb-empty-card' ] ) }}>
        <x-artisanpack-empty-state
            class="ec-kb-empty"
            :variant="$firstUse ? 'first-use' : 'no-results'"
            :icon="$icon"
            :title="$title"
            :description="filled( $description ) ? $description : null"
            :heading-level="2"
            :role="$live ? 'status' : null"
        >
            @if ( $slot->isNotEmpty() )
                <x-slot:actions>{{ $slot }}</x-slot:actions>
            @endif
        </x-artisanpack-empty-state>
    </x-artisanpack-card>
@endif
