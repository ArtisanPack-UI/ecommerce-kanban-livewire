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
}
