<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Models\KanbanColumn;
use ArtisanPackUI\Ecommerce\Models\OrderSubstatus;
use ArtisanPackUI\EcommerceKanbanLivewire\Livewire\Board;
use Livewire\Livewire;

beforeEach( function (): void {
    grantAbilities( [ 'kanbanBoard.viewAny', 'kanbanBoard.view', 'kanbanCard.move', 'kanbanBoard.update' ] );
    $this->actingAs( makeUser() );
} );

/**
 * The column arrays the board rendered, keyed by column id.
 *
 * @return array<int, array<string, mixed>>
 */
function shownColumns( $component ): array
{
    return collect( $component->viewData( 'columns' ) )->keyBy( 'id' )->all();
}

it( 'draws skeletons that wait for the board before loading lazily', function (): void {
    [ $board ] = makeBoard( [ 1, 1 ] );

    Livewire::test( Board::class, [ 'board' => $board ] )
        ->assertSeeHtml( 'data-ec-kb-loading' )
        ->assertDontSeeHtml( 'data-ec-kb-lazy' );
} );

it( 'loads only the columns on screen and leaves the rest lazy', function (): void {
    [ $board, $columns ] = makeBoard( [ 1, 1, 1 ] );

    $component = Livewire::test( Board::class, [ 'board' => $board ] )->call( 'loadBoard', [], [ $columns[0]->id, 'x', 999999 ] );
    $shown     = shownColumns( $component );

    expect( $component->get( 'opened' ) )->toBe( [ $columns[0]->id ] )
        ->and( $shown[ $columns[0]->id ]['loaded'] )->toBeTrue()
        ->and( $shown[ $columns[0]->id ]['cards'] )->toHaveCount( 1 )
        ->and( $shown[ $columns[1]->id ]['loaded'] )->toBeFalse()
        ->and( $shown[ $columns[1]->id ]['lazy'] )->toBeTrue()
        ->and( $shown[ $columns[1]->id ]['cards'] )->toBe( [] )
        ->and( $shown[ $columns[1]->id ]['count'] )->toBe( 1 );

    $component->assertSeeHtml( 'data-ec-kb-lazy' )->assertSeeHtml( 'ec-kb-column-visible' );
} );

it( 'loads every column without a visible list', function (): void {
    [ $board, $columns ] = makeBoard( [ 1, 1 ] );

    expect( Livewire::test( Board::class, [ 'board' => $board ] )->call( 'loadBoard', [] )->get( 'opened' ) )->toBe( [ $columns[0]->id, $columns[1]->id ] );
} );

it( 'loads columns as they scroll into view without rendering the board', function (): void {
    [ $board, $columns ] = makeBoard( [ 1, [ 'cards' => 0 ], 1 ] );
    addCard( $columns[1], [ 'order_number' => 'LAZY-1' ] );
    [ , $otherColumns ]  = makeBoard( [ 1 ] );

    $component = Livewire::test( Board::class, [ 'board' => $board ] )->call( 'loadBoard', [], [ $columns[0]->id ] );
    $loaded    = $component->call( 'loadColumns', [ $columns[1]->id, $columns[1]->id, $columns[0]->id, $otherColumns[0]->id ] );
    $result    = $loaded->effects['returns'][0];

    expect( $loaded->effects['html'] ?? null )->toBeNull()
        ->and( $result['refresh'] )->toBeFalse()
        ->and( array_keys( $result['columns'] ) )->toBe( [ $columns[1]->id ] )
        ->and( $result['columns'][ $columns[1]->id ] )->toContain( 'Order LAZY-1' )
        ->and( $result['columns'][ $columns[1]->id ] )->not->toContain( '<x-artisanpack' )
        ->and( $component->get( 'opened' ) )->toBe( [ $columns[0]->id, $columns[1]->id ] );

    expect( $component->call( 'loadColumns', [ $columns[1]->id ] )->effects['returns'][0] )->toBe( [ 'refresh' => false, 'columns' => [] ] );
} );

it( 'loads nothing lazily before the board has loaded', function (): void {
    [ $board, $columns ] = makeBoard( [ 1 ] );

    expect( Livewire::test( Board::class, [ 'board' => $board ] )->call( 'loadColumns', [ $columns[0]->id ] )->effects['returns'][0] )->toBe( [ 'refresh' => false, 'columns' => [] ] );
} );

it( 'asks for a board render when a lazy column loads in edit mode', function (): void {
    [ $board, $columns ] = makeBoard( [ 1, 1 ] );

    $component = Livewire::test( Board::class, [ 'board' => $board ] )->call( 'loadBoard', [], [ $columns[0]->id ] )->call( 'startEditing' );

    expect( $component->call( 'loadColumns', [ $columns[1]->id ] )->effects['returns'][0]['refresh'] )->toBeTrue()
        ->and( $component->get( 'opened' ) )->toContain( $columns[1]->id );
} );

it( 'opens a column that was never loaded when it is expanded', function (): void {
    [ $board, $columns ] = makeBoard( [ 1, 1 ] );

    $component = Livewire::test( Board::class, [ 'board' => $board ] )->call( 'loadBoard', [ $columns[1]->id ], [ $columns[0]->id ] );

    expect( shownColumns( $component )[ $columns[1]->id ]['collapsed'] )->toBeTrue();

    $component->call( 'toggleColumn', $columns[1]->id );

    expect( shownColumns( $component )[ $columns[1]->id ]['cards'] )->toHaveCount( 1 );
} );

it( 're-renders only the two columns a drop changed', function (): void {
    [ $board, $columns ] = makeBoard( [ [ 'cards' => 0, 'label_override' => 'Packing' ], [ 'cards' => 0, 'label_override' => 'Shipped' ], [ 'cards' => 0, 'label_override' => 'Done' ] ] );

    foreach ( $columns as $column ) {
        $column->substatus->update( [ 'system_status' => 'processing' ] );
    }

    $order = addCard( $columns[0], [ 'order_number' => 'D-1', 'system_status' => 'processing' ] )->order;

    $moved = Livewire::test( Board::class, [ 'board' => $board ] )->call( 'loadBoard', [] )->call( 'moveCard', $order->id, $columns[1]->id );

    expect( $moved->effects['returns'][0] )->toBe( 'moved' )
        ->and( $moved->effects['html'] ?? null )->toBeNull();

    $moved->assertDispatched( 'ec-kb-columns-rendered', function ( string $event, array $params ) use ( $columns ): bool {
        return array_keys( $params['columns'] ) === [ $columns[0]->id, $columns[1]->id ]
            && str_contains( $params['columns'][ $columns[1]->id ], 'Order D-1' )
            && ! str_contains( $params['columns'][ $columns[0]->id ], 'Order D-1' );
    } );
} );

it( 'renders the board after a move from the dialog', function (): void {
    [ $board, $columns ] = makeBoard( [ 0, 0 ] );

    foreach ( $columns as $column ) {
        $column->substatus->update( [ 'system_status' => 'processing' ] );
    }

    $order = addCard( $columns[0], [ 'order_number' => 'M-1', 'system_status' => 'processing' ] )->order;

    $moved = Livewire::test( Board::class, [ 'board' => $board ] )
        ->call( 'loadBoard', [] )
        ->call( 'openMoveDialog', $order->id )
        ->set( 'moveColumnId', $columns[1]->id )
        ->call( 'submitMove' );

    expect( $moved->effects['html'] ?? null )->not->toBeNull();
    $moved->assertNotDispatched( 'ec-kb-columns-rendered' );
} );

it( 're-renders the columns a remote card event names', function (): void {
    [ $board, $columns ] = makeBoard( [ 0, 0 ] );
    $component           = Livewire::test( Board::class, [ 'board' => $board ] )->call( 'loadBoard', [] );

    addCard( $columns[1], [ 'order_number' => 'R-1' ] );

    $refreshed = $component->call( 'refreshColumns', [ $columns[1]->id, (string) $columns[1]->id, null ] );
    $result    = $refreshed->effects['returns'][0];

    expect( $refreshed->effects['html'] ?? null )->toBeNull()
        ->and( array_keys( $result['columns'] ) )->toBe( [ $columns[1]->id ] )
        ->and( $result['columns'][ $columns[1]->id ] )->toContain( 'Order R-1' );

    $refreshed->assertDispatched( 'ec-kb-board-refreshed' );
} );

it( 'asks for a board render when a remote event names a column that is gone', function (): void {
    [ $board, $columns ] = makeBoard( [ 0, 0 ] );
    $component           = Livewire::test( Board::class, [ 'board' => $board ] )->call( 'loadBoard', [] );

    $columns[1]->delete();

    expect( $component->call( 'refreshColumns', [ $columns[0]->id, $columns[1]->id ] )->effects['returns'][0] )->toBe( [ 'refresh' => true, 'columns' => [] ] )
        ->and( $component->call( 'refreshColumns', [] )->effects['returns'][0] )->toBe( [ 'refresh' => false, 'columns' => [] ] );
} );

it( 'renders a column added elsewhere, which the page then shows with a board render', function (): void {
    [ $board ] = makeBoard( [ 0 ] );
    $component = Livewire::test( Board::class, [ 'board' => $board ] )->call( 'loadBoard', [] );
    $added     = KanbanColumn::factory()->create( [ 'board_id' => $board->id, 'substatus_id' => OrderSubstatus::factory()->create()->id, 'position' => 5 ] );

    expect( array_keys( $component->call( 'refreshColumns', [ $added->id ] )->effects['returns'][0]['columns'] ) )->toBe( [ $added->id ] );
} );

it( 'keeps filters when re-rendering a column', function (): void {
    [ $board, $columns ] = makeBoard( [ 0 ] );
    addCard( $columns[0], [ 'order_number' => 'KEEP-1' ] );
    addCard( $columns[0], [ 'order_number' => 'HIDE-1' ] );

    $component = Livewire::test( Board::class, [ 'board' => $board ] )->call( 'loadBoard', [] )->set( 'search', 'KEEP' );
    $html      = $component->call( 'refreshColumns', [ $columns[0]->id ] )->effects['returns'][0]['columns'][ $columns[0]->id ];

    expect( $html )->toContain( 'Order KEEP-1' )->not->toContain( 'Order HIDE-1' );
} );

it( 'names a read-only board\'s card lists as groups', function (): void {
    denyAbilities( [ 'kanbanCard.move' ] );
    [ $board, $columns ] = makeBoard( [ 1 ] );

    Livewire::test( Board::class, [ 'board' => $board ] )
        ->call( 'loadBoard', [] )
        ->assertDontSeeHtml( 'x-drag-context' )
        ->assertSeeHtml( 'role="group"' . "\n" . '                    aria-label="Orders in ' );
} );

it( 'doesn\'t make each empty column a live region, and announces how many cards match the filters', function (): void {
    [ $board, $columns ] = makeBoard( [ 0, 0 ] );

    $component = Livewire::test( Board::class, [ 'board' => $board ] )->call( 'loadBoard', [] );

    expect( $component->html() )->toContain( 'data-ec-kb-empty-column' )
        ->and( $component->html() )->not->toMatch( '/role="status"[^>]*data-ec-kb-empty-column|data-ec-kb-empty-column[^>]*role="status"/' );

    addCard( $columns[0], [ 'order_number' => 'MATCH-1' ] );

    $component->set( 'search', 'MATCH' )->assertSee( '1 order matches the filters.' );
} );
