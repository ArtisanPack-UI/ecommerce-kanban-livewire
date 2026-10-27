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
     * Active boards `$user` may view, in `position` order, each with
     * `cards_count` (the cards currently on it). Inactive boards are left out.
     *
     * @since 1.0.0
     *
     * @param  Authenticatable|null  $user  The viewer.
     *
     * @return Collection<int, KanbanBoard>
     */
    public static function visibleTo( ?Authenticatable $user ): Collection
    {
        return KanbanBoard::query()
            ->where( 'is_active', true )
            ->withCount( [ 'activeAssignments as cards_count' ] )
            ->orderBy( 'position' )
            ->orderBy( 'id' )
            ->get()
            ->filter( static fn ( KanbanBoard $board ): bool => Authorization::allows( $user, 'kanbanBoard.view', $board ) )
            ->values();
    }
}
