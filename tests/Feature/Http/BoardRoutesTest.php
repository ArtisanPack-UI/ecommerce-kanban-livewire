<?php

declare( strict_types=1 );

use ArtisanPackUI\EcommerceKanbanLivewire\EcommerceKanbanLivewireServiceProvider;
use ArtisanPackUI\EcommerceKanbanLivewire\Http\Controllers\BoardScreenController;
use ArtisanPackUI\EcommerceKanbanLivewire\Http\Middleware\ThrottleKanbanMutations;
use Illuminate\Support\Facades\Route;
use Livewire\Mechanisms\PersistentMiddleware\PersistentMiddleware;

it( 'registers the board routes under the prefix with controller actions', function (): void {
    $index = Route::getRoutes()->getByName( 'artisanpack.ecommerce.kanban.boards.index' );
    $show  = Route::getRoutes()->getByName( 'artisanpack.ecommerce.kanban.boards.show' );

    expect( $index )->not->toBeNull()
        ->and( $index->uri() )->toBe( 'ecommerce-kanban' )
        ->and( $index->getActionName() )->toBe( BoardScreenController::class . '@index' )
        ->and( $show->uri() )->toBe( 'ecommerce-kanban/boards/{board}' )
        ->and( $show->getActionName() )->toBe( BoardScreenController::class . '@show' )
        ->and( $show->wheres )->toBe( [ 'board' => '[0-9]+' ] )
        ->and( $show->gatherMiddleware() )->toContain( 'web', 'auth', ThrottleKanbanMutations::ALIAS );
} );

it( 'builds the fixed board URL the admin links to', function (): void {
    expect( route( 'artisanpack.ecommerce.kanban.boards.show', [ 'board' => 7 ] ) )->toEndWith( '/ecommerce-kanban/boards/7' );
} );

it( 'registers the limiter as Livewire persistent middleware', function (): void {
    expect( app( PersistentMiddleware::class )->getPersistentMiddleware() )->toContain( ThrottleKanbanMutations::class );
} );

it( 'makes the host board middleware persistent, without groups', function (): void {
    config()->set( 'artisanpack.ecommerce-kanban-livewire.board.middleware', [ 'web', 'auth', 'throttle:60,1', 'missing-alias' ] );

    expect( EcommerceKanbanLivewireServiceProvider::hostPersistentMiddleware() )->toBe( [
        Illuminate\Auth\Middleware\Authenticate::class,
        Illuminate\Routing\Middleware\ThrottleRequests::class,
    ] );
} );

it( 'sends guests to authenticate', function (): void {
    $this->getJson( route( 'artisanpack.ecommerce.kanban.boards.index' ) )->assertUnauthorized();
} );

it( 'answers 403 on the picker without kanbanBoard.viewAny', function (): void {
    $this->actingAs( makeUser() )->get( route( 'artisanpack.ecommerce.kanban.boards.index' ) )->assertForbidden();
} );

it( 'answers 403 on a board without kanbanBoard.view', function (): void {
    grantAbilities( [ 'kanbanBoard.viewAny' ] );
    [ $board ] = makeBoard();

    $this->actingAs( makeUser() )->get( route( 'artisanpack.ecommerce.kanban.boards.show', [ 'board' => $board->id ] ) )->assertForbidden();
} );

it( 'answers 404 for a board that does not exist or a non-numeric id', function (): void {
    grantAbilities( [ 'kanbanBoard.viewAny', 'kanbanBoard.view' ] );
    $this->actingAs( makeUser() );

    $this->get( '/ecommerce-kanban/boards/999' )->assertNotFound();
    $this->get( '/ecommerce-kanban/boards/production' )->assertNotFound();
} );

it( 'renders a board page in the standalone layout', function (): void {
    grantAbilities( [ 'kanbanBoard.viewAny', 'kanbanBoard.view' ] );
    [ $board ] = makeBoard( [ 0 ], [ 'name' => 'Production' ] );

    $this->actingAs( makeUser() )
        ->get( route( 'artisanpack.ecommerce.kanban.boards.show', [ 'board' => $board->id ] ) )
        ->assertOk()
        ->assertSee( '<title>Production', false )
        ->assertSee( 'Skip to content' )
        ->assertSee( 'id="ecommerce-kanban-content"', false )
        ->assertSee( 'data-ecommerce-kanban-rate-limit', false )
        ->assertSeeLivewire( 'artisanpack-ecommerce-kanban-board' );
} );

it( 'renders the picker page', function (): void {
    grantAbilities( [ 'kanbanBoard.viewAny', 'kanbanBoard.view' ] );
    makeBoard( [ 0 ], [ 'name' => 'Production' ] );
    makeBoard( [ 0 ], [ 'name' => 'Shipping' ] );

    $this->actingAs( makeUser() )
        ->get( route( 'artisanpack.ecommerce.kanban.boards.index' ) )
        ->assertOk()
        ->assertSee( 'Kanban boards' )
        ->assertSeeLivewire( 'artisanpack-ecommerce-kanban-board-picker' );
} );
