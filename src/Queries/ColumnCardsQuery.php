<?php

/**
 * Column cards query.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceKanbanLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceKanbanLivewire\Queries;

use ArtisanPackUI\Ecommerce\Kanban\KanbanCardFilters;
use ArtisanPackUI\Ecommerce\Kanban\KanbanCardQuery;
use ArtisanPackUI\Ecommerce\Models\KanbanBoard;
use ArtisanPackUI\Ecommerce\Models\KanbanColumn;
use ArtisanPackUI\Ecommerce\Models\Order;
use ArtisanPackUI\Ecommerce\Models\OrderBoardAssignment;
use Illuminate\Database\Eloquent\Collection;

/**
 * The cards in a board's columns (spec §7.2, §7.5), read through the
 * engine's {@see KanbanCardQuery}: in their manual order, narrowed by the
 * filter bar, with what the default widgets read loaded alongside.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceKanbanLivewire
 *
 * @since      1.0.0
 */
final class ColumnCardsQuery
{
    /**
     * Card counts per column on `$board`, keyed by column id: `count` is
     * what the filters leave, `total` every card (what the WIP limit is
     * checked against).
     *
     * @since 1.0.0
     *
     * @param  KanbanBoard                    $board    The board.
     * @param  Collection<int, KanbanColumn>  $columns  The board's columns.
     * @param  KanbanCardFilters|null         $filters  The filter bar's filters.
     *
     * @return array<int, array{count: int, total: int}>
     */
    public static function counts( KanbanBoard $board, Collection $columns, ?KanbanCardFilters $filters = null ): array
    {
        return KanbanCardQuery::for( $board )->filter( $filters ?? KanbanCardFilters::none() )->countsByColumn( $columns );
    }

    /**
     * The first `$limit` cards in `$column` that match `$filters`.
     *
     * @since 1.0.0
     *
     * @param  KanbanColumn            $column   The column (with its `board` relation set).
     * @param  int                     $limit    How many cards to load.
     * @param  KanbanCardFilters|null  $filters  The filter bar's filters.
     *
     * @return Collection<int, OrderBoardAssignment>
     */
    public static function cards( KanbanColumn $column, int $limit, ?KanbanCardFilters $filters = null ): Collection
    {
        return KanbanCardQuery::for( $column->board )
            ->filter( $filters ?? KanbanCardFilters::none() )
            ->column( $column )
            ->get( $limit );
    }

    /**
     * A fingerprint of each column's cards on `$board`, keyed by column id,
     * so the polling fallback can tell which columns changed without
     * rendering them (spec §12). It hashes each column's cards in display
     * order (position, then `assigned_at` and id, as the board orders
     * them), so it changes when a card joins, leaves, or moves within the
     * column (a reorder included), or when one of its orders is updated.
     * One query of a few narrow columns per card; columns without cards get
     * an empty string.
     *
     * @since 1.0.0
     *
     * @param  KanbanBoard                     $board    The board.
     * @param  Collection<int, KanbanColumn>  $columns  Its columns.
     *
     * @return array<int, string>
     */
    public static function signatures( KanbanBoard $board, Collection $columns ): array
    {
        $assignments = ( new OrderBoardAssignment() )->getTable();
        $orders      = ( new Order() )->getTable();
        $sequences   = [];

        $rows = OrderBoardAssignment::query()
            ->active()
            ->where( $assignments . '.board_id', $board->id )
            ->join( $orders, $orders . '.id', '=', $assignments . '.order_id' )
            ->orderBy( $assignments . '.position' )
            ->orderBy( $assignments . '.assigned_at' )
            ->orderBy( $assignments . '.id' )
            ->toBase()
            ->get( [
                $assignments . '.substatus_id as substatus_id',
                $assignments . '.order_id as order_id',
                $assignments . '.position as position',
                $assignments . '.moved_at as moved_at',
                $orders . '.updated_at as order_updated_at',
            ] );

        foreach ( $rows as $row ) {
            $sequences[ (int) $row->substatus_id ][] = implode( ':', [ $row->order_id, $row->position, $row->moved_at, $row->order_updated_at ] );
        }

        $signatures = [];

        foreach ( $columns as $column ) {
            $sequence = $sequences[ (int) $column->substatus_id ] ?? [];

            $signatures[ (int) $column->id ] = [] === $sequence ? '' : md5( implode( '|', $sequence ) );
        }

        return $signatures;
    }
}
