<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Models\KanbanColumn;
use ArtisanPackUI\Ecommerce\Models\OrderBoardAssignment;
use ArtisanPackUI\EcommerceKanbanLivewire\Livewire\Board;
use ArtisanPackUI\EcommerceKanbanLivewire\Support\CardActions;
use Livewire\Livewire;

beforeEach( function (): void {
    grantAbilities( [ 'kanbanBoard.viewAny', 'kanbanBoard.view', 'kanbanCard.move' ] );
    $this->actingAs( makeUser() );
} );

/**
 * Printing (P1, P2, P3), Packing (K1), and Shipping (WIP limit 1, full).
 *
 * @return array{0: ArtisanPackUI\Ecommerce\Models\KanbanBoard, 1: array<string, KanbanColumn>, 2: array<string, ArtisanPackUI\Ecommerce\Models\Order>}
 */
function dialogBoard(): array
{
    [ $board, $made ] = makeBoard( [
        [ 'cards' => 0, 'label_override' => 'Printing' ],
        [ 'cards' => 0, 'label_override' => 'Packing' ],
        [ 'cards' => 0, 'label_override' => 'Shipping', 'wip_limit' => 1 ],
    ] );

    foreach ( $made as $column ) {
        $column->substatus->update( [ 'system_status' => 'processing' ] );
    }

    $orders = [];

    foreach ( [ 'P1' => 0, 'P2' => 0, 'P3' => 0, 'K1' => 1, 'S1' => 2 ] as $number => $index ) {
        $orders[ $number ] = addCard( $made[ $index ], [ 'order_number' => $number, 'system_status' => 'processing' ], [ 'position' => count( $orders ) ] )->order;
    }

    return [ $board, [ 'printing' => $made[0], 'packing' => $made[1], 'shipping' => $made[2] ], $orders ];
}

/**
 * Order numbers in `$column`, in display order.
 *
 * @return array<int, string>
 */
function dialogColumn( KanbanColumn $column ): array
{
    return OrderBoardAssignment::query()
        ->active()
        ->where( 'board_id', $column->board_id )
        ->where( 'substatus_id', $column->substatus_id )
        ->orderBy( 'position' )
        ->orderBy( 'id' )
        ->with( 'order' )
        ->get()
        ->map( fn ( OrderBoardAssignment $card ): string => $card->order->order_number )
        ->all();
}

it( 'opens from the card menu with the current column marked and full columns flagged', function (): void {
    [ $board, $columns, $orders ] = dialogBoard();

    Livewire::test( Board::class, [ 'board' => $board ] )
        ->call( 'loadBoard', [] )
        ->dispatch( CardActions::MOVE_EVENT, orderId: $orders['P2']->id, columnId: $columns['printing']->id )
        ->assertSet( 'moveDialog', true )
        ->assertSet( 'moveOrderId', $orders['P2']->id )
        ->assertSet( 'moveColumnId', $columns['printing']->id )
        ->assertSee( 'Move order P2' )
        ->assertSee( 'Printing (current column)' )
        ->assertSee( 'Shipping (at its WIP limit)' )
        ->assertSeeHtml( 'disabled' )
        ->assertSee( 'Top of the column' )
        ->assertSee( 'Bottom of the column' )
        ->assertSee( 'After a card' );
} );

it( 'moves to the top or bottom of another column', function (): void {
    [ $board, $columns, $orders ] = dialogBoard();

    Livewire::test( Board::class, [ 'board' => $board ] )
        ->call( 'loadBoard', [] )
        ->call( 'openMoveDialog', $orders['P1']->id )
        ->set( 'moveColumnId', $columns['packing']->id )
        ->set( 'movePosition', 'top' )
        ->call( 'submitMove' )
        ->assertHasNoErrors()
        ->assertSet( 'moveDialog', false )
        ->assertDispatched( 'ec-kb-focus-card', orderId: $orders['P1']->id )
        ->call( 'openMoveDialog', $orders['P2']->id )
        ->set( 'moveColumnId', $columns['packing']->id )
        ->set( 'movePosition', 'bottom' )
        ->call( 'submitMove' );

    expect( dialogColumn( $columns['packing'] ) )->toBe( [ 'P1', 'K1', 'P2' ] )
        ->and( dialogColumn( $columns['printing'] ) )->toBe( [ 'P3' ] );
} );

it( 'reorders within the current column, after a chosen card', function (): void {
    [ $board, $columns, $orders ] = dialogBoard();

    $component = Livewire::test( Board::class, [ 'board' => $board ] )
        ->call( 'loadBoard', [] )
        ->call( 'openMoveDialog', $orders['P1']->id )
        ->set( 'movePosition', 'after' );

    $component->assertSeeHtml( 'data-ec-kb-move-after' )
        ->assertSee( 'Order P3' )
        ->set( 'moveAfterOrderId', $orders['P3']->id )
        ->call( 'submitMove' )
        ->assertHasNoErrors();

    expect( dialogColumn( $columns['printing'] ) )->toBe( [ 'P2', 'P3', 'P1' ] );

    $component->call( 'openMoveDialog', $orders['P1']->id )->set( 'movePosition', 'top' )->call( 'submitMove' );

    expect( dialogColumn( $columns['printing'] ) )->toBe( [ 'P1', 'P2', 'P3' ] );
} );

it( 'validates the column, the position, and the card to place after', function (): void {
    [ $board, , $orders ] = dialogBoard();
    [ , $otherColumns ]   = makeBoard( [ 0 ] );

    Livewire::test( Board::class, [ 'board' => $board ] )
        ->call( 'loadBoard', [] )
        ->call( 'openMoveDialog', $orders['P1']->id )
        ->set( 'moveColumnId', $otherColumns[0]->id )
        ->set( 'movePosition', 'sideways' )
        ->call( 'submitMove' )
        ->assertHasErrors( [ 'moveColumnId', 'movePosition' ] )
        ->set( 'moveColumnId', null )
        ->call( 'openMoveDialog', $orders['P1']->id )
        ->set( 'movePosition', 'after' )
        ->call( 'submitMove' )
        ->assertHasErrors( [ 'moveAfterOrderId' ] )
        ->assertSet( 'moveDialog', true );
} );

it( 'keeps the dialog open and explains a refused move', function (): void {
    [ $board, $columns, $orders ] = dialogBoard();

    $component = Livewire::test( Board::class, [ 'board' => $board ] )
        ->call( 'loadBoard', [] )
        ->call( 'openMoveDialog', $orders['P1']->id )
        ->set( 'moveColumnId', $columns['shipping']->id )
        ->call( 'submitMove' );

    expect( json_encode( $component->effects['xjs'] ?? [] ) )->toContain( 'Shipping is at its WIP limit.' )
        ->and( dialogColumn( $columns['printing'] ) )->toBe( [ 'P1', 'P2', 'P3' ] );

    $component->assertSet( 'moveDialog', true );
} );

it( 'resets the chosen card when the column changes', function (): void {
    [ $board, $columns, $orders ] = dialogBoard();

    Livewire::test( Board::class, [ 'board' => $board ] )
        ->call( 'openMoveDialog', $orders['P1']->id )
        ->set( 'movePosition', 'after' )
        ->set( 'moveAfterOrderId', $orders['P2']->id )
        ->set( 'moveColumnId', $columns['packing']->id )
        ->assertSet( 'moveAfterOrderId', null );
} );

it( 'does not open for a card that is not on the board, or without kanbanCard.move', function (): void {
    [ $board, , $orders ] = dialogBoard();
    $component            = Livewire::test( Board::class, [ 'board' => $board ] );

    $component->call( 'openMoveDialog', 999999 )->assertSet( 'moveDialog', false );

    expect( json_encode( $component->effects['xjs'] ?? [] ) )->toContain( 'This order is no longer on this board.' );

    denyAbilities( [ 'kanbanCard.move' ] );

    $component->call( 'openMoveDialog', $orders['P1']->id )->assertForbidden();
} );

it( 'describes the keyboard model in translated instructions for every card', function (): void {
    [ $board ] = dialogBoard();

    Livewire::test( Board::class, [ 'board' => $board ] )
        ->call( 'loadBoard', [] )
        ->assertSeeHtml( e( json_encode( 'Press Space or Enter to pick up the card. While holding it, use the arrow keys to move it within a column or between columns, Home or End to jump to the top or bottom, Space or Enter to drop, and Escape to cancel.' ) ) )
        ->assertSeeHtml( 'focus-visible:outline-primary' );
} );
