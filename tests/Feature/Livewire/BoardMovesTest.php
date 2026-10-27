<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Events\KanbanCardMoved;
use ArtisanPackUI\Ecommerce\Events\KanbanCardReordered;
use ArtisanPackUI\Ecommerce\Models\KanbanColumn;
use ArtisanPackUI\Ecommerce\Models\Order;
use ArtisanPackUI\Ecommerce\Models\OrderBoardAssignment;
use ArtisanPackUI\Ecommerce\Models\OrderSubstatus;
use ArtisanPackUI\EcommerceKanbanLivewire\Livewire\Board;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;

beforeEach( function (): void {
    grantAbilities( [ 'kanbanBoard.viewAny', 'kanbanBoard.view', 'kanbanCard.move' ] );
    $this->actingAs( makeUser() );
} );

afterEach( function (): void {
    removeAllFilters( 'ap.ecommerce.kanban.cardMoving' );
} );

/**
 * A board with processing columns (Printing, Packing) and a Cancelled exit
 * column, with `$printing` processing orders numbered P1… in Printing.
 *
 * @return array{0: ArtisanPackUI\Ecommerce\Models\KanbanBoard, 1: array<string, KanbanColumn>, 2: array<int, Order>}
 */
function movesBoard( int $printing = 3 ): array
{
    [ $board, $made ] = makeBoard( [
        [ 'cards' => 0, 'label_override' => 'Printing' ],
        [ 'cards' => 0, 'label_override' => 'Packing' ],
        [ 'cards' => 0, 'label_override' => 'Cancelled' ],
    ] );

    $made[0]->substatus->update( [ 'system_status' => 'processing' ] );
    $made[1]->substatus->update( [ 'system_status' => 'processing' ] );
    $made[2]->substatus->update( [ 'system_status' => 'cancelled', 'is_terminal' => true ] );

    $orders = [];

    for ( $i = 0; $i < $printing; $i++ ) {
        $orders[] = addCard( $made[0], [ 'order_number' => 'P' . ( $i + 1 ), 'system_status' => 'processing' ], [ 'position' => $i ] )->order;
    }

    return [ $board, [ 'printing' => $made[0]->fresh( 'substatus' ), 'packing' => $made[1]->fresh( 'substatus' ), 'cancelled' => $made[2]->fresh( 'substatus' ) ], $orders ];
}

/**
 * Order numbers in `$column`, in display order.
 *
 * @return array<int, string>
 */
function orderIn( KanbanColumn $column ): array
{
    return OrderBoardAssignment::query()
        ->active()
        ->where( 'board_id', $column->board_id )
        ->where( 'substatus_id', $column->substatus_id )
        ->orderBy( 'position' )
        ->orderBy( 'assigned_at' )
        ->orderBy( 'id' )
        ->with( 'order' )
        ->get()
        ->map( fn ( OrderBoardAssignment $card ): string => $card->order->order_number )
        ->all();
}

it( 'makes columns connected drag contexts and cards drag items keyed by order id', function (): void {
    [ $board, $columns, $orders ] = movesBoard( 1 );

    Livewire::test( Board::class, [ 'board' => $board ] )
        ->call( 'loadBoard', [] )
        ->assertSeeHtml( 'x-drag-context' )
        ->assertSeeHtml( 'data-drag-group="cards"' )
        ->assertSeeHtml( 'data-drag-context-id="' . $columns['packing']->id . '"' )
        ->assertSeeHtml( 'data-drag-label="Packing"' )
        ->assertSeeHtml( 'x-drag-item="' . $orders[0]->id . '"' )
        ->assertSeeHtml( 'data-drag-label="Order P1"' )
        ->assertSeeHtml( 'wire:key="card-' . $orders[0]->id . '"' )
        ->assertSeeHtml( 'role="group"' );
} );

it( 'leaves cards undraggable without kanbanCard.move', function (): void {
    [ $board ] = movesBoard( 1 );
    denyAbilities( [ 'kanbanCard.move' ] );

    Livewire::test( Board::class, [ 'board' => $board ] )
        ->call( 'loadBoard', [] )
        ->assertDontSeeHtml( 'x-drag-context' )
        ->assertDontSeeHtml( 'x-drag-item' );
} );

it( 'passes translated drag announcements and the WIP limit to the client', function (): void {
    [ $board, $columns ] = movesBoard( 2 );
    $columns['packing']->update( [ 'wip_limit' => 2 ] );

    app()->setLocale( 'de' );

    Livewire::test( Board::class, [ 'board' => $board ] )
        ->call( 'loadBoard', [] )
        ->assertSeeHtml( 'data-drag-messages=' )
        ->assertSeeHtml( 'data-ec-kb-limit="2"' )
        ->assertSeeHtml( 'data-ec-kb-total="0"' )
        ->assertSeeHtml( e( json_encode( __( '{item} moved to {container}, position {position} of {total}.' ) ) ) )
        ->assertSeeHtml( 'detail.reject(' );

    expect( __( '{item} moved to {container}, position {position} of {total}.' ) )->not->toBe( '{item} moved to {container}, position {position} of {total}.' );
} );

it( 'moves a card to another column between the cards it was dropped between', function (): void {
    [ $board, $columns, $orders ] = movesBoard( 3 );
    Event::fake( [ KanbanCardMoved::class ] );

    $component = Livewire::test( Board::class, [ 'board' => $board ] )->call( 'loadBoard', [] );

    expect( $component->call( 'moveCard', $orders[0]->id, $columns['packing']->id )->effects['returns'][0] )->toBe( 'moved' );
    expect( $component->call( 'moveCard', $orders[1]->id, $columns['packing']->id, $orders[0]->id, null )->effects['returns'][0] )->toBe( 'moved' );
    expect( $component->call( 'moveCard', $orders[2]->id, $columns['packing']->id, $orders[0]->id, $orders[1]->id )->effects['returns'][0] )->toBe( 'moved' );

    expect( orderIn( $columns['packing'] ) )->toBe( [ 'P2', 'P3', 'P1' ] )
        ->and( orderIn( $columns['printing'] ) )->toBe( [] );

    Event::assertDispatchedTimes( KanbanCardMoved::class, 3 );
    $component->assertDispatched( 'ec-kb-focus-card', orderId: $orders[2]->id )
        ->assertSeeInOrder( [ 'Order P2', 'Order P3', 'Order P1' ] );
} );

it( 'reorders a card within its column without moving it to another status', function (): void {
    [ $board, $columns, $orders ] = movesBoard( 3 );
    Event::fake( [ KanbanCardMoved::class, KanbanCardReordered::class ] );

    $component = Livewire::test( Board::class, [ 'board' => $board ] )->call( 'loadBoard', [] );

    expect( $component->call( 'moveCard', $orders[2]->id, $columns['printing']->id, $orders[0]->id, null )->effects['returns'][0] )->toBe( 'moved' );
    expect( orderIn( $columns['printing'] ) )->toBe( [ 'P3', 'P1', 'P2' ] );

    $component->call( 'moveCard', $orders[0]->id, $columns['printing']->id, null, $orders[1]->id );
    expect( orderIn( $columns['printing'] ) )->toBe( [ 'P3', 'P2', 'P1' ] );

    Event::assertDispatched( KanbanCardReordered::class );
    Event::assertNotDispatched( KanbanCardMoved::class );
    $component->assertSeeInOrder( [ 'Order P3', 'Order P2', 'Order P1' ] );
} );

it( 'converges on the server order when two people reorder the same column', function (): void {
    [ $board, $columns, $orders ] = movesBoard( 3 );

    $alice = Livewire::test( Board::class, [ 'board' => $board ] )->call( 'loadBoard', [] );
    $bob   = Livewire::test( Board::class, [ 'board' => $board ] )->call( 'loadBoard', [] );

    $alice->call( 'moveCard', $orders[2]->id, $columns['printing']->id, $orders[0]->id, null );
    $bob->call( 'moveCard', $orders[0]->id, $columns['printing']->id, null, $orders[1]->id );

    expect( orderIn( $columns['printing'] ) )->toBe( [ 'P3', 'P2', 'P1' ] );

    $bob->assertSeeInOrder( [ 'Order P3', 'Order P2', 'Order P1' ] );
    $alice->call( 'showMore', $columns['printing']->id )->assertSeeInOrder( [ 'Order P3', 'Order P2', 'Order P1' ] );
} );

it( 'refuses a move into a full column with a toast and leaves the client to revert it', function (): void {
    [ $board, $columns, $orders ] = movesBoard( 2 );
    $columns['packing']->update( [ 'wip_limit' => 1 ] );

    $component = Livewire::test( Board::class, [ 'board' => $board ] )->call( 'loadBoard', [] );
    $component->call( 'moveCard', $orders[0]->id, $columns['packing']->id );

    $refused = $component->call( 'moveCard', $orders[1]->id, $columns['packing']->id );

    expect( $refused->effects['returns'][0] )->toBe( 'rejected' )
        ->and( $refused->effects['html'] ?? null )->toBeNull()
        ->and( json_encode( $refused->effects['xjs'] ?? [] ) )->toContain( 'Packing is at its WIP limit.' )
        ->and( orderIn( $columns['printing'] ) )->toBe( [ 'P2' ] );
} );

it( 'maps each engine refusal to a translated message', function ( string $code, string $message ): void {
    expect( ArtisanPackUI\EcommerceKanbanLivewire\Support\MoveErrors::message( $code, 'Packing' ) )->toBe( $message );
} )->with( [
    'not on board'       => [ 'not-on-board', 'This order is no longer on this board.' ],
    'wip limit'          => [ 'wip-limit-reached', 'Packing is at its WIP limit.' ],
    'hook veto'          => [ 'move-rejected', 'This move was blocked.' ],
    'column elsewhere'   => [ 'column-not-on-board', 'That column is not on this board.' ],
    'status transition'  => [ 'invalid-status-transition', 'The order cannot move to Packing from its current status.' ],
    'incompatible'       => [ 'incompatible-column', 'Packing cannot hold this order at its current status.' ],
    'no entry column'    => [ 'no-entry-column', 'This board has no column that can hold this order.' ],
    'anything else'      => [ 'mystery', 'The card could not be moved.' ],
] );

it( 'shows "This move was blocked" when a cardMoving hook vetoes the move', function (): void {
    [ $board, $columns, $orders ] = movesBoard( 1 );
    addFilter( 'ap.ecommerce.kanban.cardMoving', fn (): ?array => null );

    $result = Livewire::test( Board::class, [ 'board' => $board ] )
        ->call( 'loadBoard', [] )
        ->call( 'moveCard', $orders[0]->id, $columns['packing']->id );

    expect( $result->effects['returns'][0] )->toBe( 'rejected' )
        ->and( json_encode( $result->effects['xjs'] ?? [] ) )->toContain( 'This move was blocked.' )
        ->and( orderIn( $columns['printing'] ) )->toBe( [ 'P1' ] );
} );

it( 'refuses a status move the order machine does not allow', function (): void {
    [ $board, $columns ] = movesBoard( 0 );
    $new                 = OrderSubstatus::factory()->forSystemStatus( 'pending' )->create( [ 'label' => 'New' ] );
    $newColumn           = KanbanColumn::factory()->create( [ 'board_id' => $board->id, 'substatus_id' => $new->id, 'position' => 9 ] );
    $complete            = addCard( $columns['printing'], [ 'order_number' => 'DONE', 'system_status' => 'complete' ] )->order;

    $result = Livewire::test( Board::class, [ 'board' => $board ] )
        ->call( 'loadBoard', [] )
        ->call( 'moveCard', $complete->id, $newColumn->id );

    expect( $result->effects['returns'][0] )->toBe( 'rejected' )
        ->and( json_encode( $result->effects['xjs'] ?? [] ) )->toContain( 'The order cannot move to New from its current status.' )
        ->and( $complete->fresh()->system_status )->toBe( 'complete' );
} );

it( 'refuses columns from other boards and orders that are not on the board', function (): void {
    [ $board, $columns ]      = movesBoard( 0 );
    [ , $otherColumns ]       = makeBoard( [ 0 ] );
    $stranger                 = Order::factory()->create( [ 'system_status' => 'processing' ] );

    $component = Livewire::test( Board::class, [ 'board' => $board ] )->call( 'loadBoard', [] );

    expect( $component->call( 'moveCard', $stranger->id, $otherColumns[0]->id )->effects['returns'][0] )->toBe( 'rejected' );
    expect( json_encode( $component->effects['xjs'] ?? [] ) )->toContain( 'That column is not on this board.' );

    expect( $component->call( 'moveCard', $stranger->id, $columns['packing']->id )->effects['returns'][0] )->toBe( 'rejected' );
    expect( json_encode( $component->effects['xjs'] ?? [] ) )->toContain( 'This order is no longer on this board.' );

    expect( $component->call( 'moveCard', 999999, $columns['packing']->id )->effects['returns'][0] )->toBe( 'rejected' );
} );

it( 'asks before moving a card into an exit column, then moves it on confirmation', function (): void {
    [ $board, $columns, $orders ] = movesBoard( 1 );

    $component = Livewire::test( Board::class, [ 'board' => $board ] )->call( 'loadBoard', [] );
    $result    = $component->call( 'moveCard', $orders[0]->id, $columns['cancelled']->id );

    expect( $result->effects['returns'][0] )->toBe( 'confirm' )
        ->and( orderIn( $columns['printing'] ) )->toBe( [ 'P1' ] );

    $component->assertSet( 'confirmingMove', true )
        ->assertSee( 'Move order P1 to Cancelled?' )
        ->assertSee( 'Cancelled, on every board it is on.' )
        ->call( 'confirmMove' )
        ->assertSet( 'confirmingMove', false )
        ->assertSet( 'pendingMove', null );

    expect( orderIn( $columns['cancelled'] ) )->toBe( [ 'P1' ] )
        ->and( $orders[0]->fresh()->system_status )->toBe( 'cancelled' );
} );

it( 'cancels an exit-column move and hands focus back to the card', function (): void {
    [ $board, $columns, $orders ] = movesBoard( 1 );

    Livewire::test( Board::class, [ 'board' => $board ] )
        ->call( 'loadBoard', [] )
        ->call( 'moveCard', $orders[0]->id, $columns['cancelled']->id )
        ->call( 'cancelMove' )
        ->assertSet( 'pendingMove', null )
        ->assertDispatched( 'ec-kb-focus-card', orderId: $orders[0]->id );

    expect( orderIn( $columns['printing'] ) )->toBe( [ 'P1' ] )
        ->and( $orders[0]->fresh()->system_status )->toBe( 'processing' );
} );

it( 'requires kanbanCard.move to move a card', function (): void {
    [ $board, $columns, $orders ] = movesBoard( 1 );
    $component                    = Livewire::test( Board::class, [ 'board' => $board ] )->call( 'loadBoard', [] );

    denyAbilities( [ 'kanbanCard.move' ] );

    $component->call( 'moveCard', $orders[0]->id, $columns['packing']->id )->assertForbidden();

    expect( orderIn( $columns['printing'] ) )->toBe( [ 'P1' ] );
} );

it( 'escapes column labels in refusal toasts, which render as HTML', function (): void {
    [ $board, $columns, $orders ] = movesBoard( 2 );
    $columns['packing']->update( [ 'wip_limit' => 1, 'label_override' => '<img src=x onerror=alert(1)>' ] );

    $component = Livewire::test( Board::class, [ 'board' => $board ] )->call( 'loadBoard', [] );
    $component->call( 'moveCard', $orders[0]->id, $columns['packing']->id );
    $component->call( 'moveCard', $orders[1]->id, $columns['packing']->id );

    $toast = json_encode( $component->effects['xjs'] ?? [] );

    expect( $toast )->toContain( '&lt;img src=x onerror=alert(1)&gt; is at its WIP limit.' )
        ->and( $toast )->not->toContain( '<img src=x' );
} );
