<?php

declare( strict_types=1 );

use ArtisanPackUI\EcommerceKanbanLivewire\Livewire\AutomationsPanel;
use ArtisanPackUI\EcommerceKanbanLivewire\Livewire\CardDetail;
use ArtisanPackUI\EcommerceKanbanLivewire\Livewire\WidgetPicker;
use Livewire\Livewire;

/*
 * B8: like the board, the slide-out and the panels treat a switched-off board
 * as a 404 for anyone who can't edit it.
 */

beforeEach( function (): void {
    grantAbilities( [ 'kanbanBoard.viewAny', 'kanbanBoard.view', 'order.view' ] );
    denyAbilities( [ 'kanbanBoard.update' ] );
    $this->actingAs( makeUser() );
} );

dataset( 'board children', [
    'card slide-out'    => [ CardDetail::class, 'close' ],
    'automations panel' => [ AutomationsPanel::class, 'openFor' ],
    'widget picker'     => [ WidgetPicker::class, 'openFor' ],
] );

it( 'is a 404 on a switched-off board for a viewer who can\'t edit it', function ( string $component ): void {
    [ $board ] = makeBoard( [ 1 ], [ 'is_active' => false ] );

    Livewire::test( $component, [ 'boardId' => $board->id ] )->assertNotFound();
} )->with( 'board children' );

it( 'becomes a 404 once the board is switched off while open', function ( string $component, string $call ): void {
    [ $board ] = makeBoard( [ 1 ] );
    $open      = Livewire::test( $component, [ 'boardId' => $board->id ] )->assertOk();

    $board->update( [ 'is_active' => false ] );

    $open->call( $call )->assertNotFound();
} )->with( 'board children' );

it( 'still opens on a switched-off board for someone who can edit it', function ( string $component ): void {
    grantAbilities( [ 'kanbanBoard.update' ] );
    [ $board ] = makeBoard( [ 1 ], [ 'is_active' => false ] );

    Livewire::test( $component, [ 'boardId' => $board->id ] )->assertOk();
} )->with( 'board children' );
