<?php

/**
 * Links into the ecommerce admin.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceKanbanLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceKanbanLivewire\Support;

use Illuminate\Support\Facades\Route;

/**
 * URLs of the ecommerce admin's screens, or null when the admin is not
 * installed (spec §4.2, §5.4).
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceKanbanLivewire
 *
 * @since      1.0.0
 */
final class AdminLinks
{
    /**
     * The admin's kanban board list, where boards are created.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public const BOARDS_ROUTE = 'artisanpack.ecommerce.admin.kanban-boards.index';

    /**
     * The admin's order screen.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public const ORDER_ROUTE = 'artisanpack.ecommerce.admin.orders.show';

    /**
     * Where to create a board, when the admin is installed.
     *
     * @since 1.0.0
     *
     * @return string|null
     */
    public static function createBoardUrl(): ?string
    {
        return self::url( self::BOARDS_ROUTE );
    }

    /**
     * The admin screen for an order, when the admin is installed.
     *
     * @since 1.0.0
     *
     * @param  int  $orderId  The order id.
     *
     * @return string|null
     */
    public static function orderUrl( int $orderId ): ?string
    {
        return self::url( self::ORDER_ROUTE, [ 'order' => $orderId ] );
    }

    /**
     * A named admin route's URL, or null without the admin.
     *
     * @since 1.0.0
     *
     * @param  string                $name        Route name.
     * @param  array<string, mixed>  $parameters  Route parameters.
     *
     * @return string|null
     */
    private static function url( string $name, array $parameters = [] ): ?string
    {
        if ( ! LayoutResolver::adminInstalled() || ! Route::has( $name ) ) {
            return null;
        }

        return route( $name, $parameters );
    }
}
