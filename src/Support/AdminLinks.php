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
     * The admin's board edit screen (routing, columns, automations).
     *
     * @since 1.0.0
     *
     * @var string
     */
    public const BOARD_EDIT_ROUTE = 'artisanpack.ecommerce.admin.kanban-boards.edit';

    /**
     * The id of the automations heading on the admin's board edit screen.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public const AUTOMATIONS_ANCHOR = 'board-automations-heading';

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
     * The admin's edit screen for a board — the "Advanced settings" link in
     * edit mode — when the admin is installed.
     *
     * @since 1.0.0
     *
     * @param  int  $boardId  The board id.
     *
     * @return string|null
     */
    public static function boardEditUrl( int $boardId ): ?string
    {
        return self::url( self::BOARD_EDIT_ROUTE, [ 'board' => $boardId ] );
    }

    /**
     * The admin's automations section for a board, when the admin is
     * installed: the board edit screen at its automations heading, with
     * `automation` set to the automation to edit or `new` to add one.
     *
     * @since 1.0.0
     *
     * @param  int       $boardId       The board id.
     * @param  int|null  $automationId  The automation to edit; null to add one.
     *
     * @return string|null
     */
    public static function automationUrl( int $boardId, ?int $automationId = null ): ?string
    {
        $url = self::url( self::BOARD_EDIT_ROUTE, [ 'board' => $boardId, 'automation' => $automationId ?? 'new' ] );

        return null === $url ? null : $url . '#' . self::AUTOMATIONS_ANCHOR;
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
