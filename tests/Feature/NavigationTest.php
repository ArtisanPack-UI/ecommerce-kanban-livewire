<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Registries\AdminMenuRegistry;
use ArtisanPackUI\EcommerceKanbanLivewire\EcommerceKanbanLivewireServiceProvider;

it( 'adds Kanban to the engine admin menu', function (): void {
    $entry = app( AdminMenuRegistry::class )->get( EcommerceKanbanLivewireServiceProvider::MENU_KEY );

    expect( $entry )
        ->label->toBe( 'Kanban' )
        ->route->toBe( 'artisanpack.ecommerce.kanban.boards.index' )
        ->url->toEndWith( '/ecommerce-kanban' )
        ->section->toBe( 'orders' )
        ->permission->toBe( 'kanbanBoard.viewAny' )
        ->satellite->toBe( EcommerceKanbanLivewireServiceProvider::PACKAGE_NAME );
} );

it( 'shows the entry only to users with kanbanBoard.viewAny', function (): void {
    $user = makeUser();
    $keys = static fn (): array => array_column( app( AdminMenuRegistry::class )->visibleTo( $user ), 'key' );

    expect( $keys() )->not->toContain( 'kanban' );

    grantAbilities( [ 'kanbanBoard.viewAny' ] );

    expect( $keys() )->toContain( 'kanban' );
} );
