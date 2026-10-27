<?php

declare( strict_types=1 );

namespace Tests\Concerns;

use ArtisanPackUI\EcommerceKanbanLivewire\EcommerceKanbanLivewireServiceProvider;

/**
 * Boots the app with the satellite already uninstalled, as after
 * `ecommerce:satellite:uninstall`.
 */
trait BootsUninstalled
{
    /**
     * Marks the satellite uninstalled before the providers boot.
     *
     * @param  \Illuminate\Foundation\Application  $app  The application instance.
     */
    protected function defineEnvironment( $app ): void
    {
        parent::defineEnvironment( $app );

        $app['config']->set( 'cache.default', 'array' );
        $app['cache']->forever( 'artisanpack.ecommerce.satellites.uninstalled', [ EcommerceKanbanLivewireServiceProvider::PACKAGE_NAME ] );
    }
}
