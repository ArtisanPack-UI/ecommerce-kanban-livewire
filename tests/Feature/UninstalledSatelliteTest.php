<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Registries\AdminMenuRegistry;
use ArtisanPackUI\Ecommerce\Registries\SatelliteRegistry;
use ArtisanPackUI\EcommerceKanbanLivewire\EcommerceKanbanLivewireServiceProvider;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Tests\Concerns\BootsUninstalled;

uses( BootsUninstalled::class );

it( 'wires nothing when the satellite has been uninstalled', function (): void {
    // Simulate `ecommerce:satellite:uninstall`, then boot a fresh provider.
    Cache::forever( 'artisanpack.ecommerce.satellites.uninstalled', [ EcommerceKanbanLivewireServiceProvider::PACKAGE_NAME ] );

    $this->app->forgetInstance( SatelliteRegistry::class );
    $this->app->singleton( SatelliteRegistry::class, static fn ( $app ): SatelliteRegistry => new SatelliteRegistry( $app ) );
    $this->app['view']->getFinder()->replaceNamespace( 'ecommerce-kanban', [] );

    $provider = new EcommerceKanbanLivewireServiceProvider( $this->app );
    $provider->boot();

    expect( $this->app->make( SatelliteRegistry::class )->isActive( EcommerceKanbanLivewireServiceProvider::PACKAGE_NAME ) )->toBeFalse()
        ->and( $this->app['view']->getFinder()->getHints()['ecommerce-kanban'] )->toBe( [] );
} );

it( 'registers no routes, menu entry, components, or commands when booted uninstalled', function (): void {
    expect( Route::has( 'artisanpack.ecommerce.kanban.boards.index' ) )->toBeFalse()
        ->and( Route::has( 'artisanpack.ecommerce.kanban.boards.show' ) )->toBeFalse()
        ->and( app( AdminMenuRegistry::class )->has( EcommerceKanbanLivewireServiceProvider::MENU_KEY ) )->toBeFalse()
        ->and( array_keys( Blade::getClassComponentAliases() ) )->not->toContain( 'artisanpack-ec-kb-card' )
        ->and( array_keys( Artisan::all() ) )->not->toContain( 'ecommerce-kanban:install' );

    foreach ( array_keys( EcommerceKanbanLivewireServiceProvider::LIVEWIRE_COMPONENTS ) as $name ) {
        expect( fn () => Livewire::new( $name ) )->toThrow( Exception::class );
    }
} );
