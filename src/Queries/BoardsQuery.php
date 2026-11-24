<?php

/**
 * Board picker query.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceKanbanLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceKanbanLivewire\Queries;

use ArtisanPackUI\Ecommerce\Models\KanbanBoard;
use ArtisanPackUI\EcommerceKanbanLivewire\Support\Authorization;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Collection;

/**
 * The boards a user can open from the picker (spec §7.1).
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceKanbanLivewire
 *
 * @since      1.0.0
 */
final class BoardsQuery
{
    /**
     * Active boards `$user` may view, in `position` order. Inactive boards
     * are left out. With `$withCounts`, each has `cards_count`: the cards on
     * it that the `ap.ecommerce.kanban.cardQuery` filter leaves visible
     * ({@see VisibleCards}), one count query per board.
     *
     * @since 1.0.0
     * @since 2.0.0 Added `$withCounts`; `cards_count` leaves out hidden cards.
     *
     * @param  Authenticatable|null  $user        The user.
     * @param  bool                  $withCounts  Whether to count each board's cards (the picker does; the board switcher doesn't).
     *
     * @return Collection<int, KanbanBoard>
     */
    public static function visibleTo( ?Authenticatable $user, bool $withCounts = true ): Collection
    {
        $boards = KanbanBoard::query()
            ->where( 'is_active', true )
            ->orderBy( 'position' )
            ->orderBy( 'id' )
            ->get()
            ->filter( static fn ( KanbanBoard $board ): bool => Authorization::allows( $user, 'kanbanBoard.view', $board ) )
            ->values();

        if ( $withCounts ) {
            foreach ( $boards as $board ) {
                $board->setAttribute( 'cards_count', VisibleCards::count( $board ) );
            }
        }

        return $boards;
    }
}
