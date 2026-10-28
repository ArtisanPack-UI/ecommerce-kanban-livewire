<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Models\KanbanColumn;
use ArtisanPackUI\Ecommerce\Models\OrderBoardAssignment;
use ArtisanPackUI\Ecommerce\Models\OrderSubstatus;
use ArtisanPackUI\EcommerceKanbanLivewire\Livewire\Board;
use ArtisanPackUI\EcommerceKanbanLivewire\Livewire\CardDetail;
use Livewire\Livewire;

beforeEach( function (): void {
    grantAbilities( [ 'kanbanBoard.viewAny', 'kanbanBoard.view', 'kanbanCard.move', 'order.view' ] );
    $this->actingAs( makeUser() );
} );

it( 'tells the page to listen on the board channel when Echo can be used', function (): void {
    [ $board ] = makeBoard( [ 0 ] );
    config()->set( 'artisanpack.ecommerce.kanban.broadcast', true );

    $realtime = Livewire::test( Board::class, [ 'board' => $board ] )->viewData( 'realtime' );

    expect( $realtime )->toMatchArray( [
        'echo'    => true,
        'channel' => 'ecommerce.kanban.board.' . $board->id,
        'event'   => '.kanbanBoardEvent',
        'poll'    => 30,
        'user'    => auth()->id(),
    ] );
} );

it( 'falls back to polling without engine broadcasts, and turns both off when disabled', function (): void {
    [ $board ] = makeBoard( [ 0 ] );

    expect( Livewire::test( Board::class, [ 'board' => $board ] )->viewData( 'realtime' ) )->toMatchArray( [ 'echo' => false, 'poll' => 30 ] );

    config()->set( 'artisanpack.ecommerce-kanban-livewire.realtime.poll_interval', 0 );
    expect( Livewire::test( Board::class, [ 'board' => $board ] )->viewData( 'realtime' )['poll'] )->toBe( 0 );

    config()->set( 'artisanpack.ecommerce.kanban.broadcast', true );
    config()->set( 'artisanpack.ecommerce-kanban-livewire.realtime.enabled', false );
    expect( Livewire::test( Board::class, [ 'board' => $board ] )->viewData( 'realtime' ) )->toMatchArray( [ 'echo' => false, 'poll' => 0 ] );
} );

it( 'renders the connection indicator', function (): void {
    [ $board ] = makeBoard( [ 0 ] );

    Livewire::test( Board::class, [ 'board' => $board ] )
        ->assertSeeHtml( 'data-ec-kb-connection' )
        ->assertSee( 'Live' )
        ->assertSee( 'Updates every few seconds' )
        ->assertSee( 'Offline' );
} );

it( 'shows a card added elsewhere when a board event arrives', function (): void {
    [ $board, $columns ] = makeBoard( [ 0 ] );

    $component = Livewire::test( Board::class, [ 'board' => $board ] )->call( 'loadBoard', [] );
    $card      = addCard( $columns[0], [ 'order_number' => 'REMOTE-1' ] );

    $component->call( 'boardEvent', 'card-added' )->assertSee( 'Order REMOTE-1' );

    $card->update( [ 'removed_at' => now() ] );
    $component->call( 'boardEvent', 'card-removed' )->assertDontSee( 'Order REMOTE-1' );
} );

it( 'picks up a column added elsewhere on config-changed and drops state for deleted columns', function (): void {
    [ $board, $columns ] = makeBoard( [ 0, 0 ] );

    $component = Livewire::test( Board::class, [ 'board' => $board ] )
        ->call( 'loadBoard', [ $columns[1]->id ] )
        ->call( 'showMore', $columns[0]->id );

    KanbanColumn::factory()->create( [ 'board_id' => $board->id, 'substatus_id' => OrderSubstatus::factory()->create( [ 'label' => 'Quality check' ] )->id, 'position' => 9 ] );
    $columns[1]->delete();

    $component->call( 'boardEvent', 'config-changed' )
        ->assertSee( 'Quality check' )
        ->assertSet( 'collapsed', [] );
} );

it( 'ignores unknown event types without rendering', function (): void {
    [ $board ] = makeBoard( [ 0 ] );

    $component = Livewire::test( Board::class, [ 'board' => $board ] )->call( 'boardEvent', 'bogus' );

    expect( $component->effects['html'] ?? null )->toBeNull();
} );

it( 'polls the board from the database and returns only the columns that changed', function (): void {
    [ $board, $columns ] = makeBoard( [ 0, 1 ] );

    $component = Livewire::test( Board::class, [ 'board' => $board ] )->call( 'loadBoard', [] );
    addCard( $columns[0], [ 'order_number' => 'POLLED-1' ] );

    $polled = $component->call( 'poll' );
    $result = $polled->effects['returns'][0];

    expect( $polled->effects['html'] ?? null )->toBeNull()
        ->and( $result['refresh'] )->toBeFalse()
        ->and( array_keys( $result['columns'] ) )->toBe( [ $columns[0]->id ] )
        ->and( $result['columns'][ $columns[0]->id ] )->toContain( 'Order POLLED-1' )
        ->and( $result['columns'][ $columns[0]->id ] )->toContain( 'data-ec-kb-column="' . $columns[0]->id . '"' );

    $quiet = $component->call( 'poll' )->effects['returns'][0];

    expect( $quiet )->toBe( [ 'refresh' => false, 'columns' => [] ] );

    $this->travel( 5 )->seconds();
    OrderBoardAssignment::query()->where( 'substatus_id', $columns[1]->substatus_id )->first()->order->update( [ 'email' => 'changed@example.test' ] );

    expect( array_keys( $component->call( 'poll' )->effects['returns'][0]['columns'] ) )->toBe( [ $columns[1]->id ] );
} );

it( 'notices a column edit made in the same second on the next poll', function (): void {
    [ $board, $columns ] = makeBoard( [ 0 ] );
    $this->freezeSecond();

    $component = Livewire::test( Board::class, [ 'board' => $board ] )->call( 'loadBoard', [] );

    $columns[0]->update( [ 'label_override' => 'Renamed' ] );

    expect( $component->call( 'poll' )->effects['returns'][0] )->toBe( [ 'refresh' => true, 'columns' => [] ] );
} );

it( 'notices a reorder within a column on the next poll', function (): void {
    [ $board, $columns ] = makeBoard( [ 0, 0 ] );
    $first               = addCard( $columns[0], [], [ 'position' => 0 ] );
    $second              = addCard( $columns[0], [], [ 'position' => 1 ] );

    $component = Livewire::test( Board::class, [ 'board' => $board ] )->call( 'loadBoard', [] );

    $first->update( [ 'position' => 1 ] );
    $second->update( [ 'position' => 0 ] );

    expect( array_keys( $component->call( 'poll' )->effects['returns'][0]['columns'] ) )->toBe( [ $columns[0]->id ] );
} );

it( 'asks for a board render when a poll finds the setup changed', function (): void {
    [ $board, $columns ] = makeBoard( [ 0, 0 ] );

    $component = Livewire::test( Board::class, [ 'board' => $board ] )->call( 'loadBoard', [ $columns[1]->id ] );

    $columns[1]->delete();

    expect( $component->call( 'poll' )->effects['returns'][0] )->toBe( [ 'refresh' => true, 'columns' => [] ] );
    $component->assertSet( 'collapsed', [] );
} );

it( 'polls nothing before the board has loaded', function (): void {
    [ $board ] = makeBoard( [ 1 ] );

    expect( Livewire::test( Board::class, [ 'board' => $board ] )->call( 'poll' )->effects['returns'][0] )->toBe( [ 'refresh' => false, 'columns' => [] ] );
} );

it( 'needs kanbanBoard.view for every sync action', function (): void {
    [ $board ] = makeBoard( [ 0 ] );
    $component = Livewire::test( Board::class, [ 'board' => $board ] );

    denyAbilities( [ 'kanbanBoard.view' ] );

    $component->call( 'poll' )->assertForbidden();
} );

it( 're-renders just the changed cards without rendering the board', function (): void {
    [ $board, $columns ] = makeBoard( [ [ 'cards' => 0, 'card_widgets' => [ 'total' ] ] ] );
    $card                = addCard( $columns[0], [ 'order_number' => 'C-1', 'total_amount' => 1_000, 'total_currency' => 'USD', 'currency' => 'USD' ] );
    $other               = addCard( $columns[0], [ 'order_number' => 'C-2' ] );

    $component = Livewire::test( Board::class, [ 'board' => $board ] )->call( 'loadBoard', [] );

    $card->order->update( [ 'total_amount' => 5_000 ] );

    $refreshed = $component->call( 'refreshCards', [ $card->order_id, $card->order_id, 'x' ] );
    $result    = $refreshed->effects['returns'][0];

    expect( $refreshed->effects['html'] ?? null )->toBeNull()
        ->and( $result['refresh'] )->toBeFalse()
        ->and( array_keys( $result['cards'] ) )->toBe( [ $card->order_id ] )
        ->and( $result['cards'][ $card->order_id ] )->toContain( 'data-ec-kb-card="' . $card->order_id . '"' )
        ->and( $result['cards'][ $card->order_id ] )->toContain( '$50.00' )
        ->and( $result['cards'][ $card->order_id ] )->toContain( 'x-drag-item' )
        ->and( $result['cards'] )->not->toHaveKey( $other->order_id );

    $refreshed->assertDispatched( 'ec-kb-card-updated', orderId: $card->order_id );
} );

it( 'asks for a full render when filters are set, and skips hidden or foreign cards', function (): void {
    [ $board, $columns ] = makeBoard( [ 0, 0 ] );
    [ , $elsewhere ]     = makeBoard( [ 0 ] );
    $hidden              = addCard( $columns[1] );
    $foreign             = addCard( $elsewhere[0] );

    $component = Livewire::test( Board::class, [ 'board' => $board ] )->call( 'loadBoard', [ $columns[1]->id ] );

    expect( $component->call( 'refreshCards', [ $hidden->order_id, $foreign->order_id ] )->effects['returns'][0] )->toBe( [ 'refresh' => false, 'cards' => [] ] );

    $component->set( 'search', 'anything' );

    expect( $component->call( 'refreshCards', [ $hidden->order_id ] )->effects['returns'][0]['refresh'] )->toBeTrue();
} );

it( 'refreshes the slide-out only when it shows the changed card', function (): void {
    [ $board, $columns ] = makeBoard( [ 0 ] );
    $card                = addCard( $columns[0], [ 'payment_status' => 'pending' ] );

    $detail = Livewire::test( CardDetail::class, [ 'boardId' => $board->id ] )->call( 'show', $card->order_id );

    $card->order->update( [ 'payment_status' => 'paid' ] );

    expect( $detail->call( 'cardUpdated', 999999 )->effects['html'] ?? null )->toBeNull();

    $detail->call( 'cardUpdated', $card->order_id )->assertSee( 'Paid' );
} );

it( 'refreshes an open slide-out after a poll or board event, and skips a closed one', function (): void {
    [ $board, $columns ] = makeBoard( [ 0 ] );
    $card                = addCard( $columns[0], [ 'payment_status' => 'pending' ] );

    Livewire::test( Board::class, [ 'board' => $board ] )->call( 'poll' )->assertDispatched( 'ec-kb-board-refreshed' );
    Livewire::test( Board::class, [ 'board' => $board ] )->call( 'boardEvent', 'card-moved' )->assertDispatched( 'ec-kb-board-refreshed' );

    $closed = Livewire::test( CardDetail::class, [ 'boardId' => $board->id ] )->call( 'boardRefreshed' );
    expect( $closed->effects['html'] ?? null )->toBeNull();

    $detail = Livewire::test( CardDetail::class, [ 'boardId' => $board->id ] )->call( 'show', $card->order_id );
    $card->order->update( [ 'payment_status' => 'paid' ] );
    $detail->call( 'boardRefreshed' )->assertSee( 'Paid' );
} );

it( 'applies the card-query hook to targeted refreshes', function (): void {
    [ $board, $columns ] = makeBoard( [ 0 ] );
    $visible             = addCard( $columns[0] );
    $hidden              = addCard( $columns[0] );

    addFilter( 'ap.ecommerce.kanban.cardQuery', function ( $query ) use ( $hidden ) {
        $query->where( $query->qualifyColumn( 'order_id' ), '!=', $hidden->order_id );

        return $query;
    } );

    $result = Livewire::test( Board::class, [ 'board' => $board ] )
        ->call( 'loadBoard', [] )
        ->call( 'refreshCards', [ $visible->order_id, $hidden->order_id ] )
        ->effects['returns'][0];

    expect( array_keys( $result['cards'] ) )->toBe( [ $visible->order_id ] );
} );
