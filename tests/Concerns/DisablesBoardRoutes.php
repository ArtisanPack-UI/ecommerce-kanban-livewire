<?php

declare( strict_types=1 );

namespace Tests\Concerns;

/**
 * Boots the package with `board.routes_enabled` off.
 */
trait DisablesBoardRoutes
{
    /**
     * Turns the board routes off before the providers boot.
     *
     * @param  \Illuminate\Foundation\Application  $app  The application instance.
     */
    protected function defineEnvironment( $app ): void
    {
        parent::defineEnvironment( $app );

        $app['config']->set( 'artisanpack.ecommerce-kanban-livewire.board.routes_enabled', false );
    }
}
