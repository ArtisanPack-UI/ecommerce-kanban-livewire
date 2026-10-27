<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Models\KanbanBoard;
use ArtisanPackUI\Ecommerce\Registries\SatelliteRegistry;
use ArtisanPackUI\EcommerceKanbanLivewire\Livewire\BoardPicker;
use ArtisanPackUI\EcommerceKanbanLivewire\Support\LastBoard;
use ArtisanPackUI\EcommerceKanbanLivewire\Support\LayoutResolver;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;

beforeEach( function (): void {
    grantAbilities( [ 'kanbanBoard.viewAny', 'kanbanBoard.view' ] );
    $this->user = makeUser();
    $this->actingAs( $this->user );
} );

it( 'lists active boards in position order with descriptions and card counts', function (): void {
    makeBoard( [ 1, 2 ], [ 'name' => 'Shipping', 'description' => 'Ready to ship', 'position' => 2 ] );
    makeBoard( [ 1 ], [ 'name' => 'Production', 'position' => 1 ] );
    makeBoard( [ 4 ], [ 'name' => 'Returns', 'position' => 0, 'is_active' => false ] );

    Livewire::test( BoardPicker::class, [ 'pick' => true ] )
        ->assertOk()
        ->assertSeeInOrder( [ 'Production', '1 card', 'Shipping', '3 cards', 'Ready to ship' ] )
        ->assertDontSee( 'Returns' )
        ->assertSeeHtml( route( 'artisanpack.ecommerce.kanban.boards.show', [ 'board' => KanbanBoard::query()->where( 'name', 'Shipping' )->value( 'id' ) ] ) );
} );

it( 'hides boards the user may not view', function (): void {
    makeBoard( [ 0 ], [ 'name' => 'Production' ] );
    makeBoard( [ 0 ], [ 'name' => 'Payroll' ] );
    Gate::define( 'ecommerce.kanbanBoard.view', static fn ( $user, KanbanBoard $board ): bool => 'Payroll' !== $board->name );

    Livewire::test( BoardPicker::class, [ 'pick' => true ] )
        ->assertSee( 'Production' )
        ->assertDontSee( 'Payroll' );
} );

it( 'redirects to the last viewed board unless asked to pick', function (): void {
    makeBoard( [ 0 ], [ 'name' => 'Production' ] );
    [ $shipping ] = makeBoard( [ 0 ], [ 'name' => 'Shipping' ] );
    LastBoard::remember( $this->user, $shipping );

    Livewire::test( BoardPicker::class )
        ->assertRedirect( route( 'artisanpack.ecommerce.kanban.boards.show', [ 'board' => $shipping->id ] ) );

    Livewire::test( BoardPicker::class, [ 'pick' => true ] )
        ->assertNoRedirect()
        ->assertSee( 'Last opened' );
} );

it( 'ignores a last board that is now inactive or hidden', function (): void {
    makeBoard( [ 0 ], [ 'name' => 'Production' ] );
    makeBoard( [ 0 ], [ 'name' => 'Packing' ] );
    [ $shipping ] = makeBoard( [ 0 ], [ 'name' => 'Shipping' ] );
    LastBoard::remember( $this->user, $shipping );
    $shipping->update( [ 'is_active' => false ] );

    Livewire::test( BoardPicker::class )
        ->assertNoRedirect()
        ->assertSee( 'Production' );
} );

it( 'goes straight to the only board', function (): void {
    [ $board ] = makeBoard( [ 0 ] );

    Livewire::test( BoardPicker::class )
        ->assertRedirect( route( 'artisanpack.ecommerce.kanban.boards.show', [ 'board' => $board->id ] ) );
} );

it( 'remembers the board a user opens, per user', function (): void {
    makeBoard( [ 0 ], [ 'name' => 'Production' ] );
    [ $shipping ] = makeBoard( [ 0 ], [ 'name' => 'Shipping' ] );

    $this->get( route( 'artisanpack.ecommerce.kanban.boards.show', [ 'board' => $shipping->id ] ) )->assertOk();

    $this->get( route( 'artisanpack.ecommerce.kanban.boards.index' ) )
        ->assertRedirect( route( 'artisanpack.ecommerce.kanban.boards.show', [ 'board' => $shipping->id ] ) );

    $this->get( route( 'artisanpack.ecommerce.kanban.boards.index', [ 'pick' => 1 ] ) )->assertOk()->assertSee( 'Production' );

    expect( LastBoard::id( makeUser() ) )->toBeNull();
} );

it( 'shows an empty state without boards', function (): void {
    Livewire::test( BoardPicker::class )
        ->assertOk()
        ->assertSee( 'No boards yet' )
        ->assertDontSeeHtml( 'data-ec-kb-create-board' );
} );

it( 'links the empty state to the admin when it is installed', function (): void {
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
    Route::get( 'admin/kanban-boards', static fn (): string => 'boards' )->name( 'artisanpack.ecommerce.admin.kanban-boards.index' );
    Route::getRoutes()->refreshNameLookups();

    Livewire::test( BoardPicker::class )
        ->assertSeeHtml( 'data-ec-kb-create-board' )
        ->assertSee( 'Create board' )
        ->assertSeeHtml( route( 'artisanpack.ecommerce.admin.kanban-boards.index' ) );
} );

it( 'is denied without kanbanBoard.viewAny', function (): void {
    denyAbilities( [ 'kanbanBoard.viewAny' ] );

    Livewire::test( BoardPicker::class )->assertForbidden();
} );

it( 'is denied for guests', function (): void {
    auth()->logout();

    Livewire::test( BoardPicker::class )->assertForbidden();
} );
