<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Events\KanbanBoardAssignmentAdded;
use ArtisanPackUI\Ecommerce\Events\KanbanBoardAssignmentRemoved;
use ArtisanPackUI\Ecommerce\Models\Order;
use ArtisanPackUI\Ecommerce\Models\OrderBoardAssignment;
use ArtisanPackUI\EcommerceKanbanLivewire\Livewire\Board;
use ArtisanPackUI\EcommerceKanbanLivewire\Livewire\CardDetail;
use ArtisanPackUI\EcommerceKanbanLivewire\Support\CardActions;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

beforeEach( function (): void {
    grantAbilities( [ 'kanbanBoard.viewAny', 'kanbanBoard.view', 'kanbanBoard.update', 'kanbanCard.move', 'order.view' ] );
    $this->actingAs( makeUser() );
} );

/**
 * A Production board with Printing and Packing columns (processing).
 *
 * @return array{0: ArtisanPackUI\Ecommerce\Models\KanbanBoard, 1: Illuminate\Support\Collection<int, ArtisanPackUI\Ecommerce\Models\KanbanColumn>}
 */
function assignmentBoard(): array
{
    [ $board, $columns ] = makeBoard( [ [ 'cards' => 0, 'label_override' => 'Printing' ], [ 'cards' => 0, 'label_override' => 'Packing' ] ], [ 'name' => 'Production' ] );

    foreach ( $columns as $column ) {
        $column->substatus->update( [ 'system_status' => 'processing' ] );
    }

    return [ $board, $columns ];
}

function onBoardIn( Order $order, $board ): ?int
{
    $substatus = OrderBoardAssignment::query()->active()->where( 'order_id', $order->id )->where( 'board_id', $board->id )->value( 'substatus_id' );

    return null === $substatus ? null : (int) $substatus;
}

it( 'shows "Add order" and "Edit board" only with kanbanBoard.update', function (): void {
    [ $board ] = assignmentBoard();

    Livewire::test( Board::class, [ 'board' => $board ] )->assertSeeHtml( 'data-ec-kb-add-order' );

    denyAbilities( [ 'kanbanBoard.update' ] );

    Livewire::test( Board::class, [ 'board' => $board ] )
        ->assertDontSeeHtml( 'data-ec-kb-add-order' )
        ->assertDontSeeHtml( 'data-ec-kb-edit-board' )
        ->call( 'openAddOrder' )
        ->assertForbidden();
} );

it( 'searches orders that are not on the board and adds one to the entry column', function (): void {
    Event::fake( [ KanbanBoardAssignmentAdded::class ] );
    [ $board, $columns ] = assignmentBoard();
    $reprint             = Order::factory()->create( [ 'order_number' => 'R-2001', 'system_status' => 'processing' ] );
    $already             = Order::factory()->create( [ 'order_number' => 'R-2002', 'system_status' => 'processing' ] );
    addCardFor( $already, $columns[0] );

    $component = Livewire::test( Board::class, [ 'board' => $board ] )
        ->call( 'loadBoard', [] )
        ->call( 'openAddOrder' )
        ->assertSet( 'addOrderDialog', true )
        ->set( 'addOrderSearch', 'r-20' )
        ->assertSee( 'Order R-2001' )
        ->assertDontSee( 'Order R-2002 —' )
        ->set( 'addOrderId', $reprint->id )
        ->call( 'submitAddOrder' )
        ->assertHasNoErrors()
        ->assertSet( 'addOrderDialog', false )
        ->assertDispatched( 'ec-kb-focus-card', orderId: $reprint->id );

    expect( onBoardIn( $reprint, $board ) )->toBe( $columns[0]->substatus_id )
        ->and( json_encode( $component->effects['xjs'] ?? [] ) )->toContain( 'Order R-2001 added to this board.' );
    Event::assertDispatched( KanbanBoardAssignmentAdded::class, fn ( KanbanBoardAssignmentAdded $event ): bool => auth()->id() === $event->actorUserId );
} );

it( 'adds an order to a chosen column', function (): void {
    [ $board, $columns ] = assignmentBoard();
    $order               = Order::factory()->create( [ 'system_status' => 'processing' ] );

    Livewire::test( Board::class, [ 'board' => $board ] )
        ->call( 'openAddOrder' )
        ->set( 'addOrderId', $order->id )
        ->set( 'addOrderColumnId', $columns[1]->id )
        ->call( 'submitAddOrder' )
        ->assertHasNoErrors();

    expect( onBoardIn( $order, $board ) )->toBe( $columns[1]->substatus_id );
} );

it( 'validates the order and column', function (): void {
    [ $board ]   = assignmentBoard();
    [ , $other ] = makeBoard( [ 0 ] );

    Livewire::test( Board::class, [ 'board' => $board ] )
        ->call( 'openAddOrder' )
        ->call( 'submitAddOrder' )
        ->assertHasErrors( [ 'addOrderId' => 'required' ] )
        ->set( 'addOrderId', 999999 )
        ->call( 'submitAddOrder' )
        ->assertHasErrors( 'addOrderId' )
        ->set( 'addOrderId', Order::factory()->create()->id )
        ->set( 'addOrderColumnId', $other[0]->id )
        ->call( 'submitAddOrder' )
        ->assertHasErrors( 'addOrderColumnId' );
} );

it( 'maps engine refusals to toasts', function (): void {
    [ $board, $columns ] = assignmentBoard();
    $pending             = Order::factory()->create( [ 'system_status' => 'cancelled' ] );

    $component = Livewire::test( Board::class, [ 'board' => $board ] )
        ->call( 'openAddOrder' )
        ->set( 'addOrderId', $pending->id )
        ->set( 'addOrderColumnId', $columns[1]->id )
        ->call( 'submitAddOrder' );

    expect( json_encode( $component->effects['xjs'] ?? [] ) )->toContain( 'Packing cannot hold this order at its current status.' )
        ->and( onBoardIn( $pending, $board ) )->toBeNull();

    [ $empty ]  = makeBoard( [] );
    $component  = Livewire::test( Board::class, [ 'board' => $empty ] )
        ->call( 'openAddOrder' )
        ->set( 'addOrderId', $pending->id )
        ->call( 'submitAddOrder' );

    expect( json_encode( $component->effects['xjs'] ?? [] ) )->toContain( 'This board has no column that can hold this order.' );
} );

it( 'removes a card after confirmation, once per token', function (): void {
    Event::fake( [ KanbanBoardAssignmentRemoved::class ] );
    [ $board, $columns ] = assignmentBoard();
    $card                = addCard( $columns[0], [ 'order_number' => 'D-1', 'system_status' => 'processing' ] );

    $component = Livewire::test( Board::class, [ 'board' => $board ] )
        ->call( 'loadBoard', [] )
        ->dispatch( CardActions::REMOVE_EVENT, orderId: $card->order_id )
        ->assertSet( 'confirmingRemove', true )
        ->assertSee( 'Remove order D-1 from this board?' );

    $token = $component->viewData( 'removeToken' );

    $component->call( 'removeCard', $token )->assertSet( 'confirmingRemove', false );

    expect( onBoardIn( $card->order, $board ) )->toBeNull()
        ->and( json_encode( $component->effects['xjs'] ?? [] ) )->toContain( 'Order D-1 removed from this board.' );
    Event::assertDispatchedTimes( KanbanBoardAssignmentRemoved::class, 1 );

    // A replayed token changes nothing.
    addCardFor( $card->order, $columns[0] );
    $component->call( 'confirmRemoveCard', $card->order_id )->assertSet( 'removingOrderId', $card->order_id );
    $replayed = $component->call( 'removeCard', $token );

    expect( onBoardIn( $card->order, $board ) )->not->toBeNull()
        ->and( json_encode( $replayed->effects['xjs'] ?? [] ) )->toContain( 'That action was already submitted.' );
} );

it( 'cancels a removal and refuses one for a card not on the board or without the ability', function (): void {
    [ $board, $columns ] = assignmentBoard();
    $card                = addCard( $columns[0] );

    Livewire::test( Board::class, [ 'board' => $board ] )
        ->call( 'confirmRemoveCard', $card->order_id )
        ->call( 'cancelRemoveCard' )
        ->assertSet( 'confirmingRemove', false )
        ->assertDispatched( 'ec-kb-focus-card', orderId: $card->order_id )
        ->call( 'confirmRemoveCard', Order::factory()->create()->id )
        ->assertSet( 'confirmingRemove', false );

    expect( onBoardIn( $card->order, $board ) )->not->toBeNull();

    denyAbilities( [ 'kanbanBoard.update' ] );

    Livewire::test( Board::class, [ 'board' => $board ] )->call( 'confirmRemoveCard', $card->order_id )->assertForbidden();
} );

it( 'adds the open order to another board from the slide-out', function (): void {
    [ $board, $columns ]    = assignmentBoard();
    [ $shipping, $targets ] = makeBoard( [ [ 'cards' => 0, 'label_override' => 'Awaiting label' ] ], [ 'name' => 'Shipping' ] );
    $targets[0]->substatus->update( [ 'system_status' => 'processing' ] );
    $card = addCard( $columns[0], [ 'order_number' => 'S-1', 'system_status' => 'processing' ] );

    $component = Livewire::test( CardDetail::class, [ 'boardId' => $board->id ] )
        ->call( 'show', $card->order_id )
        ->assertSeeHtml( 'data-ec-kb-detail-add-board' )
        ->assertSee( 'Shipping' )
        ->call( 'addToBoard' )
        ->assertHasErrors( 'addBoardId' )
        ->set( 'addBoardId', $shipping->id )
        ->assertSee( 'Entry column (automatic)' )
        ->call( 'addToBoard' );

    expect( onBoardIn( $card->order, $shipping ) )->toBe( $targets[0]->substatus_id )
        ->and( json_encode( $component->effects['xjs'] ?? [] ) )->toContain( 'Order S-1 added to Shipping.' );

    $component->assertDontSeeHtml( 'data-ec-kb-detail-add-board-select' );
} );

it( 'hands "Remove from board" in the slide-out to the board', function (): void {
    [ $board, $columns ] = assignmentBoard();
    $card                = addCard( $columns[0] );

    Livewire::test( CardDetail::class, [ 'boardId' => $board->id ] )
        ->call( 'show', $card->order_id )
        ->assertSeeHtml( 'data-ec-kb-detail-remove' )
        ->call( 'removeFromBoard' )
        ->assertSet( 'open', false )
        ->assertDispatched( CardActions::REMOVE_EVENT, orderId: $card->order_id );

    denyAbilities( [ 'kanbanBoard.update' ] );

    Livewire::test( CardDetail::class, [ 'boardId' => $board->id ] )
        ->call( 'show', $card->order_id )
        ->assertDontSeeHtml( 'data-ec-kb-detail-remove' )
        ->assertDontSeeHtml( 'data-ec-kb-detail-add-board' );
} );

it( 'refuses to add to a board the user cannot update', function (): void {
    [ $board, $columns ] = assignmentBoard();
    [ $other ]           = makeBoard( [ 0 ] );
    $card                = addCard( $columns[0] );

    Gate::define( 'ecommerce.kanbanBoard.update', fn ( $user, $subject = null ): bool => ! ( $subject instanceof ArtisanPackUI\Ecommerce\Models\KanbanBoard && $subject->is( $other ) ) );

    Livewire::test( CardDetail::class, [ 'boardId' => $board->id ] )
        ->call( 'show', $card->order_id )
        ->set( 'addBoardId', $other->id )
        ->call( 'addToBoard' )
        ->assertForbidden();
} );

/**
 * Puts `$order` in `$column`.
 */
function addCardFor( Order $order, $column ): OrderBoardAssignment
{
    return OrderBoardAssignment::query()->updateOrCreate(
        [ 'order_id' => $order->id, 'board_id' => $column->board_id ],
        [ 'substatus_id' => $column->substatus_id, 'removed_at' => null, 'assigned_at' => now(), 'moved_at' => now() ],
    );
}

it( 'only finds and adds orders the user may view', function (): void {
    [ $board ] = assignmentBoard();
    $visible   = Order::factory()->create( [ 'order_number' => 'V-1', 'system_status' => 'processing' ] );
    $hidden    = Order::factory()->create( [ 'order_number' => 'V-2', 'system_status' => 'processing' ] );

    Gate::define( 'ecommerce.order.view', fn ( $user, $order = null ): bool => ! ( $order instanceof Order && $order->is( $hidden ) ) );

    Livewire::test( Board::class, [ 'board' => $board ] )
        ->call( 'openAddOrder' )
        ->set( 'addOrderSearch', 'V-' )
        ->assertSee( 'Order V-1' )
        ->assertDontSee( 'Order V-2' )
        ->set( 'addOrderId', $hidden->id )
        ->call( 'submitAddOrder' )
        ->assertHasErrors( 'addOrderId' );

    expect( onBoardIn( $hidden, $board ) )->toBeNull();
} );

it( 'lists columns only for boards the user may add the order to', function (): void {
    [ $board, $columns ] = assignmentBoard();
    [ $inactive ]        = makeBoard( [ [ 'cards' => 0, 'label_override' => 'Secret column' ] ], [ 'is_active' => false ] );
    $card                = addCard( $columns[0] );

    Livewire::test( CardDetail::class, [ 'boardId' => $board->id ] )
        ->call( 'show', $card->order_id )
        ->set( 'addBoardId', $inactive->id )
        ->assertDontSee( 'Secret column' );
} );

it( 'hands focus back to "Add order" when the dialog is closed without adding', function (): void {
    [ $board ] = makeBoard( [ 0 ] );

    Livewire::test( Board::class, [ 'board' => $board ] )
        ->call( 'openAddOrder' )
        ->set( 'addOrderSearch', '10' )
        ->call( 'cancelAddOrder' )
        ->assertSet( 'addOrderDialog', false )
        ->assertSet( 'addOrderSearch', '' )
        ->assertDispatched( 'ec-kb-focus', selector: '[data-ec-kb-add-order]' );
} );
