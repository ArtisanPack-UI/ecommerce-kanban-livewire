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
     * checked against). `count` never includes a card the
     * `ap.ecommerce.kanban.cardQuery` filter hides, even with no filter set
     * (the engine then reports every card), so "Show more" isn't offered for
     * hidden cards.
     *
     * @since 1.0.0
     * @since 2.0.0 `count` leaves out hidden cards when no filter is set.
     *
     * @param  KanbanBoard                    $board    The board.
     * @param  Collection<int, KanbanColumn>  $columns  The board's columns.
     * @param  KanbanCardFilters|null         $filters  The filter bar's filters.
     *
     * @return array<int, array{count: int, total: int}>
     */
    public static function counts( KanbanBoard $board, Collection $columns, ?KanbanCardFilters $filters = null ): array
    {
        $filters ??= KanbanCardFilters::none();

        if ( ! $filters->isEmpty() ) {
            return KanbanCardQuery::for( $board )->filter( $filters )->countsByColumn( $columns );
        }

        // With no filter set the engine reports every card as matching, so
        // count the visible ones alongside the total in the same query.
        $query   = OrderBoardAssignment::query()->active()->where( 'board_id', $board->id );
        $visible = VisibleCards::ids( $board )->toBase();
        $rows    = $query
            ->select( $query->qualifyColumn( 'substatus_id' ) )
            ->selectRaw( 'COUNT(*) as total' )
            ->selectRaw( 'SUM(CASE WHEN ' . $query->getQuery()->getGrammar()->wrap( $query->qualifyColumn( 'id' ) ) . ' IN (' . $visible->toSql() . ') THEN 1 ELSE 0 END) as visible', $visible->getBindings() )
            ->groupBy( $query->qualifyColumn( 'substatus_id' ) )
            ->toBase()
            ->get()
            ->keyBy( 'substatus_id' );
        $counts  = [];

        foreach ( $columns as $column ) {
            $row = $rows[ $column->substatus_id ] ?? null;

            $counts[ (int) $column->id ] = [
                'count' => (int) ( $row->visible ?? 0 ),
                'total' => (int) ( $row->total ?? 0 ),
            ];
        }

        return $counts;
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
     * an empty string. Cards the `ap.ecommerce.kanban.cardQuery` filter
     * hides are left out ({@see VisibleCards}).
     *
     * @since 1.0.0
     * @since 2.0.0 Leaves out hidden cards.
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
            ->whereIn( $assignments . '.id', VisibleCards::ids( $board ) )
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
