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

use ArtisanPackUI\Ecommerce\Models\KanbanBoard;
use ArtisanPackUI\Ecommerce\Models\KanbanColumn;
use ArtisanPackUI\Ecommerce\Models\OrderBoardAssignment;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * The cards in a board's columns (spec §7.2).
 *
 * This is the fallback over `OrderBoardAssignment::active()` until the
 * engine's filterable `KanbanCardQuery` ships (ArtisanPack-UI/ecommerce#199);
 * the board reads cards only through this class, so the swap is one file.
 *
 * Cards are ordered by their manual position once the engine stores one
 * (ArtisanPack-UI/ecommerce#198), then by when they reached the column. The
 * orders' lines and the card's own assignment are loaded with the cards, so
 * the default widgets render without a query per card.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceKanbanLivewire
 *
 * @since      1.0.0
 */
final class ColumnCardsQuery
{
    /**
     * Whether the assignments table has a `position` column, once per process.
     *
     * @since 1.0.0
     *
     * @var bool|null
     */
    private static ?bool $hasPosition = null;

    /**
     * Card counts per column on `$board`, keyed by column id.
     *
     * @since 1.0.0
     *
     * @param  KanbanBoard                         $board    The board.
     * @param  Collection<int, KanbanColumn>  $columns  The board's columns.
     *
     * @return array<int, int>
     */
    public static function counts( KanbanBoard $board, Collection $columns ): array
    {
        $bySubstatus = OrderBoardAssignment::query()
            ->active()
            ->where( 'board_id', $board->id )
            ->selectRaw( 'substatus_id, count(*) as aggregate' )
            ->groupBy( 'substatus_id' )
            ->pluck( 'aggregate', 'substatus_id' );

        $counts = [];

        foreach ( $columns as $column ) {
            $counts[ (int) $column->id ] = (int) ( $bySubstatus[ $column->substatus_id ] ?? 0 );
        }

        return $counts;
    }

    /**
     * The first `$limit` cards in `$column`.
     *
     * @since 1.0.0
     *
     * @param  KanbanColumn  $column  The column (with its `board` relation set).
     * @param  int           $limit   How many cards to load.
     *
     * @return Collection<int, OrderBoardAssignment>
     */
    public static function cards( KanbanColumn $column, int $limit ): Collection
    {
        $query = OrderBoardAssignment::query()
            ->active()
            ->where( 'board_id', $column->board_id )
            ->where( 'substatus_id', $column->substatus_id )
            ->with( [ 'order.items' ] );

        if ( self::hasPosition() ) {
            $query->orderBy( 'position' );
        }

        $cards = $query
            ->orderByRaw( 'COALESCE(moved_at, assigned_at) asc' )
            ->orderBy( 'id' )
            ->limit( max( 1, $limit ) )
            ->get()
            ->filter( static fn ( OrderBoardAssignment $card ): bool => null !== $card->order )
            ->values();

        // Widgets such as "days in column" read the card's assignment from
        // the order; hand it over instead of letting each card query it.
        foreach ( $cards as $card ) {
            $card->order->setRelation( 'boardAssignments', new Collection( [ $card ] ) );
        }

        return $cards;
    }

    /**
     * Whether the engine stores a manual card position yet.
     *
     * @since 1.0.0
     *
     * @return bool
     */
    private static function hasPosition(): bool
    {
        return self::$hasPosition ??= Schema::hasColumn( ( new OrderBoardAssignment() )->getTable(), 'position' );
    }
}
