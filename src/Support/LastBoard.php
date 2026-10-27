<?php

/**
 * Last viewed board.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceKanbanLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceKanbanLivewire\Support;

use ArtisanPackUI\Ecommerce\Models\KanbanBoard;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Remembers the board a user last opened, in their session (spec §7.1,
 * open question 2), so the board picker can send them straight back to it.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceKanbanLivewire
 *
 * @since      1.0.0
 */
final class LastBoard
{
    /**
     * Session key prefix; the user id is appended.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public const SESSION_KEY = 'artisanpack.ecommerce-kanban.last_board';

    /**
     * Records `$board` as the board `$user` last opened.
     *
     * @since 1.0.0
     *
     * @param  Authenticatable|null  $user   The viewer.
     * @param  KanbanBoard           $board  The board opened.
     *
     * @return void
     */
    public static function remember( ?Authenticatable $user, KanbanBoard $board ): void
    {
        if ( null === $user || ! app()->bound( 'session' ) ) {
            return;
        }

        session()->put( self::key( $user ), (int) $board->id );
    }

    /**
     * The id of the board `$user` last opened, if any.
     *
     * @since 1.0.0
     *
     * @param  Authenticatable|null  $user  The viewer.
     *
     * @return int|null
     */
    public static function id( ?Authenticatable $user ): ?int
    {
        if ( null === $user || ! app()->bound( 'session' ) ) {
            return null;
        }

        $id = session()->get( self::key( $user ) );

        return is_numeric( $id ) ? (int) $id : null;
    }

    /**
     * The session key for `$user`.
     *
     * @since 1.0.0
     *
     * @param  Authenticatable  $user  The viewer.
     *
     * @return string
     */
    private static function key( Authenticatable $user ): string
    {
        return self::SESSION_KEY . '.' . $user->getAuthIdentifier();
    }
}
