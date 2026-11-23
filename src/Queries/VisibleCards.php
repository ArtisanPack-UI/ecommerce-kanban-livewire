<?php

/**
 * Visible cards query.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceKanbanLivewire
 *
 * @since      2.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceKanbanLivewire\Queries;

use ArtisanPackUI\Ecommerce\Kanban\KanbanCardFilters;
use ArtisanPackUI\Ecommerce\Kanban\KanbanCardQuery;
use ArtisanPackUI\Ecommerce\Models\KanbanBoard;
use ArtisanPackUI\Ecommerce\Models\OrderBoardAssignment;
use Illuminate\Database\Eloquent\Builder;

/**
 * The cards on a board that the engine's `ap.ecommerce.kanban.cardQuery`
 * filter leaves visible, with no filter-bar filters applied.
 *
 * The board treats that hook as a visibility boundary: a card it hides is
 * refused or left out on every path, not just the board's card lists. Moves,
 * "Move to…", removing a card, the slide-out (including `?card=`), the
 * customer, product, and tag filter options, the widget preview, and the
 * board picker's counts all read their cards through here.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceKanbanLivewire
 *
 * @since      2.0.0
 */
final class VisibleCards
{
    /**
     * The board's visible active cards, without eager loads.
     *
     * @since 2.0.0
     *
     * @param  KanbanBoard  $board  The board.
     *
     * @return Builder<OrderBoardAssignment>
     */
    public static function query( KanbanBoard $board ): Builder
    {
        return KanbanCardQuery::for( $board )->filter( KanbanCardFilters::none() )->builder()->setEagerLoads( [] );
    }

    /**
     * `$orderId`'s visible card on the board, or null when the order isn't on
     * it or the card is hidden.
     *
     * @since 2.0.0
     *
     * @param  KanbanBoard  $board    The board.
     * @param  int          $orderId  The order.
     *
     * @return OrderBoardAssignment|null
     */
    public static function assignment( KanbanBoard $board, int $orderId ): ?OrderBoardAssignment
    {
        $query = self::query( $board );

        return $query->where( $query->qualifyColumn( 'order_id' ), $orderId )->first();
    }

    /**
     * The order ids of the board's visible cards, as a subquery.
     *
     * @since 2.0.0
     *
     * @param  KanbanBoard  $board  The board.
     *
     * @return Builder<OrderBoardAssignment>
     */
    public static function orderIds( KanbanBoard $board ): Builder
    {
        $query = self::query( $board );

        return $query->select( $query->qualifyColumn( 'order_id' ) );
    }

    /**
     * The ids of the board's visible cards, as a subquery.
     *
     * @since 2.0.0
     *
     * @param  KanbanBoard  $board  The board.
     *
     * @return Builder<OrderBoardAssignment>
     */
    public static function ids( KanbanBoard $board ): Builder
    {
        $query = self::query( $board );

        return $query->select( $query->qualifyColumn( 'id' ) );
    }

    /**
     * How many visible cards the board has.
     *
     * @since 2.0.0
     *
     * @param  KanbanBoard  $board  The board.
     *
     * @return int
     */
    public static function count( KanbanBoard $board ): int
    {
        return self::query( $board )->count();
    }
}
