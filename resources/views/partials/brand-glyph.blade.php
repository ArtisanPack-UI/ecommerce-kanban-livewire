{{--
    The kanban glyph for the standalone layout's brand tile (#59): three
    top-aligned bars of different lengths, like columns of cards. Neither the
    design system's icon set nor Heroicons has it, so it ships inline and
    needs no host build step.

    It strokes in `currentColor`, so the tile's inverse colors (and forced
    colors) apply, and it is decorative: the Icon root around it is
    aria-hidden and the brand link carries the "Kanban" name.

    @package    ArtisanPack_UI
    @subpackage EcommerceKanbanLivewire

    @since      2.0.0
--}}
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true" focusable="false" data-scope="icon" data-part="svg" data-ec-kb-brand-glyph><path d="M7.5 6.5v9M12 6.5v4.5M16.5 6.5v11"/></svg>
