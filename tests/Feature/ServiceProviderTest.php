<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Registries\SatelliteRegistry;
use ArtisanPackUI\EcommerceKanbanLivewire\EcommerceKanbanLivewireServiceProvider;
use Illuminate\Support\ServiceProvider;

it( 'boots alongside the engine', function (): void {
    expect( $this->app->getProvider( EcommerceKanbanLivewireServiceProvider::class ) )
        ->toBeInstanceOf( EcommerceKanbanLivewireServiceProvider::class );
} );

it( 'merges the config under the artisanpack key', function (): void {
    expect( config( 'artisanpack.ecommerce-kanban-livewire.board' ) )->toBe( [
        'routes_enabled' => true,
        'route_prefix'   => 'ecommerce-kanban',
        'middleware'     => [ 'web', 'auth' ],
    ] )
        ->and( config( 'artisanpack.ecommerce-kanban-livewire.realtime' ) )->toBe( [
            'enabled'       => true,
            'poll_interval' => 30,
        ] );
} );

it( 'registers itself with the satellite registry', function (): void {
    $registry = $this->app->make( SatelliteRegistry::class );

    expect( $registry->has( EcommerceKanbanLivewireServiceProvider::PACKAGE_NAME ) )->toBeTrue()
        ->and( $registry->isActive( EcommerceKanbanLivewireServiceProvider::PACKAGE_NAME ) )->toBeTrue();

    $descriptor = $registry->get( EcommerceKanbanLivewireServiceProvider::PACKAGE_NAME );

    expect( $descriptor->version )->toBe( EcommerceKanbanLivewireServiceProvider::VERSION )
        ->and( $descriptor->configKeys )->toBe( [ 'artisanpack.ecommerce-kanban-livewire' ] )
        ->and( $descriptor->tables )->toBe( [] )
        ->and( $descriptor->migrationPaths )->toBe( [] );
} );

it( 'registers the view namespace', function (): void {
    expect( $this->app['view']->getFinder()->getHints() )->toHaveKey( 'ecommerce-kanban' );
} );

it( 'registers the publish tags', function ( string $tag ): void {
    expect( ServiceProvider::pathsToPublish( EcommerceKanbanLivewireServiceProvider::class, $tag ) )->not->toBeEmpty();
} )->with( [ 'ecommerce-kanban-config', 'ecommerce-kanban-views', 'ecommerce-kanban-lang' ] );
