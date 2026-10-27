<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Models\Order;
use ArtisanPackUI\Ecommerce\Models\OrderBoardAssignment;
use ArtisanPackUI\Ecommerce\Models\OrderItem;
use ArtisanPackUI\Ecommerce\Models\OrderTimelineEntry;
use ArtisanPackUI\EcommerceKanbanLivewire\Livewire\Board;
use ArtisanPackUI\EcommerceKanbanLivewire\Livewire\CardDetail;
use ArtisanPackUI\EcommerceKanbanLivewire\Support\CardActions;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach( function (): void {
    grantAbilities( [ 'kanbanBoard.viewAny', 'kanbanBoard.view', 'order.view', 'kanbanCard.move' ] );
    $this->actingAs( makeUser() );
} );

/**
 * A Printing board with order 1042 on it (and on a Shipping board too).
 *
 * @return array{0: ArtisanPackUI\Ecommerce\Models\KanbanBoard, 1: Order}
 */
function detailOrder(): array
{
    [ $board, $columns ]    = makeBoard( [ [ 'cards' => 0, 'label_override' => 'Printing' ] ], [ 'name' => 'Production' ] );
    [ , $shippingColumns ]  = makeBoard( [ [ 'cards' => 0, 'label_override' => 'Awaiting label' ] ], [ 'name' => 'Shipping' ] );

    $order = Order::factory()->create( [
        'order_number'       => '1042',
        'email'              => 'jane@example.test',
        'phone'              => '+1 555 0100',
        'system_status'      => 'processing',
        'payment_status'     => 'paid',
        'fulfillment_status' => 'unfulfilled',
        'currency'           => 'USD',
        'subtotal_amount'    => 12_000,
        'shipping_amount'    => 400,
        'tax_amount'         => 0,
        'total_amount'       => 12_400,
        'total_currency'     => 'USD',
        'shipping_address'   => [ 'first_name' => 'Jane', 'last_name' => 'Doe', 'address1' => '1 Main St', 'city' => 'Springfield', 'region_code' => 'IL', 'postal_code' => '62701', 'country_code' => 'us' ],
        'billing_address'    => [ 'first_name' => 'Jane', 'last_name' => 'Doe' ],
    ] );

    OrderItem::factory()->create( [ 'order_id' => $order->id, 'product_snapshot' => [ 'name' => 'Tour shirt', 'sku' => 'SHIRT-L', 'variant_name' => 'Large' ], 'quantity' => 2, 'total_amount' => 12_000, 'total_currency' => 'USD' ] );
    OrderTimelineEntry::query()->create( [ 'order_id' => $order->id, 'event_type' => 'order.placed', 'payload' => [] ] );
    OrderTimelineEntry::query()->create( [ 'order_id' => $order->id, 'event_type' => 'order.substatus_changed', 'payload' => [ 'to_id' => $columns[0]->substatus_id, 'board_id' => $board->id ] ] );

    foreach ( [ $columns[0], $shippingColumns[0] ] as $column ) {
        OrderBoardAssignment::factory()->create( [ 'order_id' => $order->id, 'board_id' => $column->board_id, 'substatus_id' => $column->substatus_id ] );
    }

    return [ $board, $order ];
}

it( 'loads nothing until a card is opened', function (): void {
    [ $board ] = detailOrder();

    $component = Livewire::test( CardDetail::class, [ 'boardId' => $board->id ] )
        ->assertSet( 'open', false )
        ->assertDontSeeHtml( 'data-ec-kb-detail=' );

    DB::enableQueryLog();
    $component->call( '$refresh' );

    expect( collect( DB::getQueryLog() )->pluck( 'query' )->filter( fn ( string $sql ): bool => str_contains( $sql, 'ecommerce_orders' ) )->all() )->toBe( [] );
} );

it( 'opens on the card menu event with the order summary', function (): void {
    [ $board, $order ] = detailOrder();

    Livewire::test( CardDetail::class, [ 'boardId' => $board->id ] )
        ->dispatch( CardActions::OPEN_EVENT, orderId: $order->id )
        ->assertSet( 'open', true )
        ->assertSet( 'orderId', $order->id )
        ->assertSeeHtml( 'data-ec-kb-detail="' . $order->id . '"' )
        ->assertSee( 'Order 1042' )
        ->assertSee( [ 'Processing', 'Printing' ] )
        ->assertSee( [ 'Jane Doe', 'jane@example.test', '+1 555 0100' ] )
        ->assertSee( [ '1 Main St', 'Springfield, IL 62701', 'US' ] )
        ->assertSee( [ 'Tour shirt', 'Large', 'SHIRT-L', '$120.00' ] )
        ->assertSee( [ 'Subtotal', 'Shipping', '$4.00', 'Total', '$124.00' ] )
        ->assertSee( [ 'Paid', 'Unfulfilled' ] )
        ->assertSee( 'Shipping: Awaiting label' )
        ->assertSee( [ 'Moved to Printing', 'Order placed' ] );
} );

it( 'opens from ?card= on the board URL', function (): void {
    [ $board, $order ] = detailOrder();

    Livewire::withQueryParams( [ 'card' => $order->id ] )
        ->test( CardDetail::class, [ 'boardId' => $board->id ] )
        ->assertSet( 'open', true )
        ->assertSee( 'Order 1042' );

    $this->get( route( 'artisanpack.ecommerce.kanban.boards.show', [ 'board' => $board->id, 'card' => $order->id ] ) )
        ->assertOk()
        ->assertSeeLivewire( CardDetail::class )
        ->assertSee( 'Jane Doe' );
} );

it( 'is rendered by the board, and its Open action dispatches the open event with a link fallback', function (): void {
    [ $board, $order ] = detailOrder();

    $component = Livewire::test( Board::class, [ 'board' => $board ] )
        ->call( 'loadBoard', [] )
        ->assertSeeHtml( CardActions::OPEN_EVENT )
        ->assertSeeHtml( 'card=' . $order->id );

    expect( $component->snapshot['memo']['children'] )->toHaveKey( 'card-detail-' . $board->id );
} );

it( 'needs order.view and the order on this board', function (): void {
    [ $board, $order ] = detailOrder();
    $stranger          = Order::factory()->create();

    $component = Livewire::test( CardDetail::class, [ 'boardId' => $board->id ] )
        ->call( 'show', $stranger->id )
        ->assertSet( 'open', false )
        ->assertSet( 'orderId', null );

    expect( json_encode( $component->effects['xjs'] ?? [] ) )->toContain( 'This order is not on this board.' );

    denyAbilities( [ 'order.view' ] );

    $component->call( 'show', $order->id )->assertSet( 'open', false );

    expect( json_encode( $component->effects['xjs'] ?? [] ) )->toContain( 'You are not allowed to view this order.' );

    Livewire::withQueryParams( [ 'card' => $order->id ] )
        ->test( CardDetail::class, [ 'boardId' => $board->id ] )
        ->assertOk()
        ->assertSet( 'open', false )
        ->assertDontSee( 'Jane Doe' );
} );

it( 'closes, clears the URL, and hands focus back to the card', function (): void {
    [ $board, $order ] = detailOrder();

    Livewire::test( CardDetail::class, [ 'boardId' => $board->id ] )
        ->call( 'show', $order->id )
        ->set( 'open', false )
        ->assertSet( 'orderId', null )
        ->assertDispatched( 'ec-kb-focus-card', orderId: $order->id )
        ->assertDontSeeHtml( 'data-ec-kb-detail=' );
} );

it( 'traps focus and closes on Escape', function (): void {
    [ $board, $order ] = detailOrder();

    Livewire::test( CardDetail::class, [ 'boardId' => $board->id ] )
        ->call( 'show', $order->id )
        ->assertSeeHtml( 'x-trap="open"' )
        ->assertSeeHtml( '@keydown.window.escape="close()"' );
} );

it( 'hands off to the board\'s Move to… dialog', function (): void {
    [ $board, $order ] = detailOrder();

    Livewire::test( CardDetail::class, [ 'boardId' => $board->id ] )
        ->call( 'show', $order->id )
        ->assertSeeHtml( 'data-ec-kb-detail-move' )
        ->call( 'moveTo' )
        ->assertSet( 'open', false )
        ->assertDispatched( CardActions::MOVE_EVENT, orderId: $order->id );

    denyAbilities( [ 'kanbanCard.move' ] );

    Livewire::test( CardDetail::class, [ 'boardId' => $board->id ] )
        ->call( 'show', $order->id )
        ->assertDontSeeHtml( 'data-ec-kb-detail-move' );
} );

it( 'links to the admin order screen only when the admin is installed', function (): void {
    [ $board, $order ] = detailOrder();

    Livewire::test( CardDetail::class, [ 'boardId' => $board->id ] )
        ->call( 'show', $order->id )
        ->assertDontSeeHtml( 'data-ec-kb-detail-admin' );
} );

it( 'is denied without kanbanBoard.view', function (): void {
    [ $board ] = detailOrder();
    denyAbilities( [ 'kanbanBoard.view' ] );

    Livewire::test( CardDetail::class, [ 'boardId' => $board->id ] )->assertForbidden();
} );

it( 're-checks board access on every update', function (): void {
    [ $board, $order ] = detailOrder();
    $component         = Livewire::test( CardDetail::class, [ 'boardId' => $board->id ] );

    denyAbilities( [ 'kanbanBoard.view' ] );

    $component->call( 'show', $order->id )->assertForbidden();
} );
