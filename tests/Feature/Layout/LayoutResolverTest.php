<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Registries\SatelliteRegistry;
use ArtisanPackUI\EcommerceKanbanLivewire\Support\LayoutResolver;

beforeEach( function (): void {
    useFixtureViews();
    LayoutResolver::fakeCms( false );
} );

/**
 * Registers the ecommerce admin as an active satellite.
 */
function activateAdminSatellite(): void
{
    app( SatelliteRegistry::class )->register( [
        'package_name'    => LayoutResolver::ADMIN_PACKAGE,
        'version'         => '1.0.0',
        'label'           => 'Admin (Livewire)',
        'migration_paths' => [],
        'config_keys'     => [],
        'meta_namespaces' => [],
        'tables'          => [],
        'columns'         => [],
        'product_types'   => [],
    ] );
}

it( 'falls back to the standalone layout', function (): void {
    expect( LayoutResolver::layout() )->toBe( 'ecommerce-kanban::layouts.app' )
        ->and( LayoutResolver::pushesAssets( LayoutResolver::layout() ) )->toBeFalse();
} );

it( 'uses the configured layout first', function (): void {
    config()->set( 'artisanpack.ecommerce-kanban-livewire.layout', 'host::layout' );
    LayoutResolver::fakeCms( true );
    activateAdminSatellite();

    expect( LayoutResolver::layout() )->toBe( 'host::layout' );
} );

it( 'ignores a configured layout that does not exist', function (): void {
    config()->set( 'artisanpack.ecommerce-kanban-livewire.layout', 'host::missing' );

    expect( LayoutResolver::layout() )->toBe( 'ecommerce-kanban::layouts.app' );
} );

it( 'prefers the cms-framework admin layout over the ecommerce admin', function (): void {
    LayoutResolver::fakeCms( true );
    activateAdminSatellite();

    expect( LayoutResolver::layout() )->toBe( LayoutResolver::CMS_LAYOUT )
        ->and( LayoutResolver::pushesAssets( LayoutResolver::CMS_LAYOUT ) )->toBeTrue();
} );

it( 'uses the ecommerce admin layout when that satellite is active', function (): void {
    activateAdminSatellite();

    expect( LayoutResolver::layout() )->toBe( LayoutResolver::ADMIN_LAYOUT );
} );

it( 'renders the board inside the resolved layout, pushing assets under cms-framework', function ( ?bool $cms, ?string $configured, string $expected, bool $pushes ): void {
    LayoutResolver::fakeCms( $cms );
    config()->set( 'artisanpack.ecommerce-kanban-livewire.layout', $configured );
    grantAbilities( [ 'kanbanBoard.viewAny', 'kanbanBoard.view' ] );
    [ $board ] = makeBoard( [ 0 ], [ 'name' => 'Production' ] );

    $response = $this->actingAs( makeUser() )
        ->get( route( 'artisanpack.ecommerce.kanban.boards.show', [ 'board' => $board->id ] ) )
        ->assertOk()
        ->assertSee( 'data-fixture-layout="' . $expected . '"', false )
        ->assertSee( '<h1>Production</h1>', false )
        ->assertSee( 'data-ecommerce-kanban-page', false );

    $pushes
        ? $response->assertSee( 'data-ecommerce-kanban-rate-limit', false )
        : $response->assertDontSee( 'data-ecommerce-kanban-rate-limit', false );
} )->with( [
    'cms-framework' => [ true, null, 'cms', true ],
    'configured'    => [ false, 'host::layout', 'host', false ],
] );
