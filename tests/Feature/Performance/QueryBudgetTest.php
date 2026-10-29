<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Models\OrderBoardAssignment;
use ArtisanPackUI\Ecommerce\Models\OrderItem;
use ArtisanPackUI\EcommerceKanbanLivewire\Livewire\Board;
use ArtisanPackUI\EcommerceKanbanLivewire\Livewire\CardDetail;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

/*
 * Query budgets (spec §12). Every read is fixed per column, so these counts
 * hold however many cards a column has. Loading a board costs
 * BOARD_LOAD_QUERIES plus PER_COLUMN_QUERIES for each open column;
 * collapsed columns and columns that haven't scrolled into view cost
 * nothing.
 */
const BOARD_LOAD_QUERIES    = 9;
const PER_COLUMN_QUERIES    = 3;
const COLUMN_PAGE_QUERIES   = 15;
const CARD_RERENDER_QUERIES = 6;
const IDLE_POLL_QUERIES     = 6;
const SLIDE_OUT_QUERIES     = 18;

beforeEach( function (): void {
    grantAbilities( [ 'kanbanBoard.viewAny', 'kanbanBoard.view', 'kanbanCard.move', 'kanbanBoard.update', 'order.view' ] );
    $this->actingAs( makeUser() );
} );

/**
 * How many queries `$callback` runs.
 */
function queriesDuring( callable $callback ): int
{
    $count = 0;

    DB::listen( function () use ( &$count ): void {
        $count++;
    } );

    $callback();

    return $count;
}

it( 'loads a board in a fixed number of queries per column', function ( int $cards ): void {
    [ $board ] = makeBoard( [ $cards, $cards, $cards ] );
    $component = Livewire::test( Board::class, [ 'board' => $board ] );

    expect( queriesDuring( fn () => $component->call( 'loadBoard', [] ) ) )->toBe( BOARD_LOAD_QUERIES + 3 * PER_COLUMN_QUERIES );
} )->with( [ 'two cards a column' => 2, 'a full page a column' => 25, 'more than a page' => 40 ] );

it( 'skips collapsed columns and columns off screen', function (): void {
    [ $board, $columns ] = makeBoard( [ 5, 5, 5, 5 ] );

    // The engine keeps the board's tag options and widget settings for the
    // rest of the request, so warm them before comparing loads.
    Livewire::test( Board::class, [ 'board' => $board ] )->call( 'loadBoard', [] );

    $every     = Livewire::test( Board::class, [ 'board' => $board ] );
    $collapsed = Livewire::test( Board::class, [ 'board' => $board ] );
    $onScreen  = Livewire::test( Board::class, [ 'board' => $board ] );

    $all = queriesDuring( fn () => $every->call( 'loadBoard', [] ) );

    expect( $all - queriesDuring( fn () => $collapsed->call( 'loadBoard', [ $columns[0]->id, $columns[1]->id ] ) ) )->toBe( 2 * PER_COLUMN_QUERIES )
        ->and( $all - queriesDuring( fn () => $onScreen->call( 'loadBoard', [], [ $columns[0]->id ] ) ) )->toBe( 3 * PER_COLUMN_QUERIES );
} );

it( 'pages a column in a fixed number of queries', function ( int $cards ): void {
    [ $board, $columns ] = makeBoard( [ $cards, $cards, $cards ] );
    $component           = Livewire::test( Board::class, [ 'board' => $board ] )->call( 'loadBoard', [] );

    expect( queriesDuring( fn () => $component->call( 'showMore', $columns[0]->id ) ) )->toBe( COLUMN_PAGE_QUERIES );
} )->with( [ 'two cards a column' => 2, 'two pages a column' => 50 ] );

it( 're-renders cards in a fixed number of queries', function ( int $count ): void {
    [ $board ] = makeBoard( [ 10, 10 ] );
    $component = Livewire::test( Board::class, [ 'board' => $board ] )->call( 'loadBoard', [] );
    $ids       = OrderBoardAssignment::query()->orderBy( 'id' )->limit( $count )->pluck( 'order_id' )->all();

    expect( queriesDuring( fn () => $component->call( 'refreshCards', $ids ) ) )->toBe( CARD_RERENDER_QUERIES );
} )->with( [ 'one card' => 1, 'many cards' => 15 ] );

it( 'polls an unchanged board in a fixed number of queries and renders nothing', function ( int $cards ): void {
    [ $board ] = makeBoard( [ $cards, $cards, $cards ] );
    $component = Livewire::test( Board::class, [ 'board' => $board ] )->call( 'loadBoard', [] );
    $polled    = null;

    expect( queriesDuring( function () use ( $component, &$polled ): void {
        $polled = $component->call( 'poll' );
    } ) )->toBe( IDLE_POLL_QUERIES )
        ->and( $polled->effects['returns'][0] )->toBe( [ 'refresh' => false, 'columns' => [] ] )
        ->and( $polled->effects['html'] ?? null )->toBeNull();
} )->with( [ 'two cards a column' => 2, 'thirty cards a column' => 30 ] );

it( 'opens the slide-out in a fixed number of queries', function ( int $lines ): void {
    [ $board, $columns ] = makeBoard( [ 0 ] );
    $card                = addCard( $columns[0] );

    OrderItem::factory()->count( $lines )->create( [ 'order_id' => $card->order_id ] );

    $detail = Livewire::test( CardDetail::class, [ 'boardId' => $board->id ] );

    expect( queriesDuring( fn () => $detail->call( 'show', $card->order_id ) ) )->toBe( SLIDE_OUT_QUERIES );
} )->with( [ 'one line' => 1, 'many lines' => 12 ] );

it( 'loads a 1,000-card board within the query budget', function (): void {
    [ $board ] = makeBoard( [ 200, 200, 200, 200, 200 ] );

    expect( OrderBoardAssignment::query()->count() )->toBe( 1000 );

    $component = Livewire::test( Board::class, [ 'board' => $board ] );
    $queries   = queriesDuring( fn () => $component->call( 'loadBoard', [] ) );

    expect( $queries )->toBe( BOARD_LOAD_QUERIES + 5 * PER_COLUMN_QUERIES )
        ->and( substr_count( $component->html(), 'data-ec-kb-card="' ) )->toBe( 5 * Board::perColumn() );
} );
