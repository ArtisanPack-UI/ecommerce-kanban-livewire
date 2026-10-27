<?php

/**
 * Board URLs.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceKanbanLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceKanbanLivewire\Support;

use ArtisanPackUI\Ecommerce\Models\KanbanBoard;
use Illuminate\Support\Facades\Route;

/**
 * URLs of the board screens.
 *
 * With `board.routes_enabled` off the package registers no routes, so a host
 * that embeds the components supplies its own URLs through the
 * `ap.ecommerceKanbanLivewire.boardUrl` and `ap.ecommerceKanbanLivewire.pickerUrl`
 * filters.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceKanbanLivewire
 *
 * @since      1.0.0
 */
final class BoardUrls
{
    /**
     * The board picker route.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public const INDEX_ROUTE = 'artisanpack.ecommerce.kanban.boards.index';

    /**
     * The board route (a fixed name; the ecommerce admin links to it).
     *
     * @since 1.0.0
     *
     * @var string
     */
    public const SHOW_ROUTE = 'artisanpack.ecommerce.kanban.boards.show';

    /**
     * A board's URL, if it has one.
     *
     * @since 1.0.0
     *
     * @param  KanbanBoard           $board  The board.
     * @param  array<string, mixed>  $query  Query-string parameters.
     *
     * @return string|null
     */
    public static function show( KanbanBoard $board, array $query = [] ): ?string
    {
        $url = Route::has( self::SHOW_ROUTE ) ? route( self::SHOW_ROUTE, [ 'board' => $board->id, ...$query ] ) : null;
        $url = applyFilters( 'ap.ecommerceKanbanLivewire.boardUrl', $url, $board, $query );

        return is_string( $url ) && '' !== $url ? $url : null;
    }

    /**
     * The board picker's URL (always listing, never redirecting), if any.
     *
     * @since 1.0.0
     *
     * @return string|null
     */
    public static function picker(): ?string
    {
        $url = Route::has( self::INDEX_ROUTE ) ? route( self::INDEX_ROUTE, [ 'pick' => 1 ] ) : null;
        $url = applyFilters( 'ap.ecommerceKanbanLivewire.pickerUrl', $url );

        return is_string( $url ) && '' !== $url ? $url : null;
    }
}
