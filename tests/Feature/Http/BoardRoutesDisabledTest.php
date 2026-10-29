<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Registries\AdminMenuRegistry;
use ArtisanPackUI\EcommerceKanbanLivewire\EcommerceKanbanLivewireServiceProvider;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Tests\Concerns\DisablesBoardRoutes;

uses( DisablesBoardRoutes::class );

it( 'registers no routes and no menu entry when routes are off', function (): void {
    expect( Route::has( 'artisanpack.ecommerce.kanban.boards.index' ) )->toBeFalse()
        ->and( Route::has( 'artisanpack.ecommerce.kanban.boards.show' ) )->toBeFalse()
        ->and( app( AdminMenuRegistry::class )->has( EcommerceKanbanLivewireServiceProvider::MENU_KEY ) )->toBeFalse();
} );

it( 'still registers the components for a host to embed', function (): void {
    grantAbilities( [ 'kanbanBoard.view' ] );
    [ $board ] = makeBoard( [ 0 ], [ 'name' => 'Production' ] );

    $this->actingAs( makeUser() );

    Livewire::test( 'artisanpack-ecommerce-kanban-board', [ 'board' => $board->id ] )
        ->assertOk()
        ->assertSee( 'Production' );
} );
