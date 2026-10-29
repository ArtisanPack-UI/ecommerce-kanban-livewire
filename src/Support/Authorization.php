<?php

/**
 * Engine ability checks.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceKanbanLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceKanbanLivewire\Support;

use ArtisanPackUI\Ecommerce\Auth\EcommerceAuthorizer;
use ArtisanPackUI\Ecommerce\Models\KanbanBoard;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Checks `{resource}.{action}` abilities through the engine's
 * `EcommerceAuthorizer` (spec §6).
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceKanbanLivewire
 *
 * @since      1.0.0
 */
final class Authorization
{
    /**
     * Whether `$user` holds `$ability`. A malformed ability is denied.
     *
     * @since 1.0.0
     *
     * @param  Authenticatable|null  $user     The user.
     * @param  string                $ability  `{resource}.{action}`, optionally prefixed with `ecommerce.`.
     * @param  mixed                 $subject  The model acted on, if any.
     *
     * @return bool
     */
    public static function allows( ?Authenticatable $user, string $ability, mixed $subject = null ): bool
    {
        $ability = str_starts_with( $ability, 'ecommerce.' ) ? substr( $ability, 10 ) : $ability;
        $parts   = explode( '.', $ability );

        if ( 2 !== count( $parts ) || '' === $parts[0] || '' === $parts[1] ) {
            return false;
        }

        return app( EcommerceAuthorizer::class )->allows( $user, $parts[0], $parts[1], $subject );
    }

    /**
     * Whether `$user` may open `$board`: they hold `kanbanBoard.view`, and a
     * switched-off board opens only for people who can edit it.
     *
     * @since 1.0.0
     *
     * @param  Authenticatable|null  $user   The user.
     * @param  KanbanBoard           $board  The board.
     *
     * @return bool
     */
    public static function canOpenBoard( ?Authenticatable $user, KanbanBoard $board ): bool
    {
        return self::allows( $user, 'kanbanBoard.view', $board )
            && ( (bool) $board->is_active || self::allows( $user, 'kanbanBoard.update', $board ) );
    }
}
