<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Models\KanbanBoard;
use ArtisanPackUI\EcommerceKanbanLivewire\Livewire\Board;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

beforeEach( function (): void {
    grantAbilities( [ 'kanbanBoard.viewAny', 'kanbanBoard.view' ] );
    $this->actingAs( makeUser() );
} );

it( 'draws the column headers first and loads cards after', function (): void {
    [ $board ] = makeBoard( [ 2 ] );

    Livewire::test( Board::class, [ 'board' => $board ] )
        ->assertOk()
        ->assertSet( 'loaded', false )
        ->assertSeeHtml( 'aria-busy="true"' )
        ->assertSeeHtml( 'data-ec-kb-loading' )
        ->assertDontSeeHtml( 'data-ec-kb-card=' )
        ->call( 'loadBoard', [] )
        ->assertSet( 'loaded', true )
        ->assertSeeHtml( 'aria-busy="false"' )
        ->assertDontSeeHtml( 'data-ec-kb-loading' )
        ->assertSeeHtml( 'data-ec-kb-card=' );
} );

it( 'renders columns in position order as labelled regions with overrides, then sub-status values', function (): void {
    [ $board, $columns ] = makeBoard( [
        [ 'cards' => 0, 'position' => 1, 'label_override' => 'Packing', 'color_override' => '#112233', 'icon_override' => 'archive-box' ],
        [ 'cards' => 0, 'position' => 0 ],
    ] );
    $columns[1]->substatus->update( [ 'label' => 'Awaiting label', 'color' => '#445566', 'icon' => 'o-tag' ] );

    Livewire::test( Board::class, [ 'board' => $board ] )
        ->call( 'loadBoard', [] )
        ->assertSeeInOrder( [ 'Awaiting label', 'Packing' ] )
        ->assertSeeHtml( 'aria-labelledby="ec-kb-column-' . $columns[0]->id . '-title"' )
        ->assertSeeHtml( 'id="ec-kb-column-' . $columns[0]->id . '-title"' )
        ->assertSeeHtml( 'border-top-color: #112233' )
        ->assertSeeHtml( 'border-top-color: #445566' );
} );

it( 'drops unsafe colours', function (): void {
    [ $board ] = makeBoard( [ [ 'cards' => 0, 'color_override' => 'red;background:url(x)' ] ] );

    Livewire::test( Board::class, [ 'board' => $board ] )
        ->call( 'loadBoard', [] )
        ->assertDontSeeHtml( 'url(x)' )
        ->assertSeeHtml( 'border-top-color: transparent' );
} );

it( 'shows the count, and n / limit with a text equivalent when the column has a WIP limit', function (): void {
    [ $board ] = makeBoard( [
        [ 'cards' => 2, 'wip_limit' => 3 ],
        [ 'cards' => 3, 'wip_limit' => 3 ],
        [ 'cards' => 4 ],
    ] );

    $component = Livewire::test( Board::class, [ 'board' => $board ] )->call( 'loadBoard', [] );

    $component->assertSeeHtml( '2 / 3' )
        ->assertSee( '2 of 3 cards' )
        ->assertSeeHtml( '3 / 3' )
        ->assertSee( '3 of 3 cards, WIP limit reached' )
        ->assertSee( '4 cards' );

    expect( substr_count( $component->html(), 'data-ec-kb-wip-reached' ) )->toBe( 1 );
} );

it( 'shows an empty column state', function (): void {
    [ $board ] = makeBoard( [ 0 ] );

    Livewire::test( Board::class, [ 'board' => $board ] )
        ->call( 'loadBoard', [] )
        ->assertSee( 'No orders in this column' );
} );

it( 'shows an empty state for a board without columns', function (): void {
    $board = KanbanBoard::factory()->create();

    Livewire::test( Board::class, [ 'board' => $board ] )
        ->assertSee( 'This board has no columns' )
        ->assertDontSeeHtml( 'data-ec-kb-columns' );
} );

it( 'orders cards by their manual position and leaves out removed cards', function (): void {
    [ $board, $columns ] = makeBoard( [ 0 ] );
    addCard( $columns[0], [ 'order_number' => 'SECOND' ], [ 'position' => 1 ] );
    addCard( $columns[0], [ 'order_number' => 'FIRST' ], [ 'position' => 0 ] );
    addCard( $columns[0], [ 'order_number' => 'GONE' ], [ 'removed_at' => now() ] );

    Livewire::test( Board::class, [ 'board' => $board ] )
        ->call( 'loadBoard', [] )
        ->assertSeeInOrder( [ 'Order FIRST', 'Order SECOND' ] )
        ->assertDontSee( 'Order GONE' )
        ->assertSeeHtml( '2 cards' );
} );

it( 'pages a column with Show more', function (): void {
    config()->set( 'artisanpack.ecommerce-kanban-livewire.cards.per_column', 2 );
    [ $board, $columns ] = makeBoard( [ 5 ] );
    $columnId            = $columns[0]->id;
    $cards               = static fn ( $component ): int => substr_count( $component->html(), 'data-ec-kb-card=' );

    $component = Livewire::test( Board::class, [ 'board' => $board ] )->call( 'loadBoard', [] );

    expect( $cards( $component ) )->toBe( 2 );
    $component->assertSeeHtml( 'data-ec-kb-show-more' )->call( 'showMore', $columnId );
    expect( $cards( $component ) )->toBe( 4 );
    $component->call( 'showMore', $columnId );
    expect( $cards( $component ) )->toBe( 5 );
    $component->assertDontSeeHtml( 'data-ec-kb-show-more' );
} );

it( 'restores collapsed columns, which load no cards', function (): void {
    [ $board, $columns ] = makeBoard( [ [ 'cards' => 2, 'label_override' => 'Packing' ], [ 'cards' => 1, 'label_override' => 'Shipped' ] ] );

    $component = Livewire::test( Board::class, [ 'board' => $board ] )
        ->call( 'loadBoard', [ $columns[1]->id, 99999, 'junk' ] )
        ->assertSet( 'collapsed', [ $columns[1]->id ] )
        ->assertSeeHtml( 'data-ec-kb-collapsed' )
        ->assertSee( 'Expand Shipped' )
        ->assertSee( 'Collapse Packing' );

    expect( substr_count( $component->html(), 'data-ec-kb-card=' ) )->toBe( 2 );

    $component->call( 'toggleColumn', $columns[1]->id )
        ->assertSet( 'collapsed', [] );

    expect( substr_count( $component->html(), 'data-ec-kb-card=' ) )->toBe( 3 );

    $component->call( 'toggleColumn', $columns[0]->id )
        ->assertSet( 'collapsed', [ $columns[0]->id ] )
        ->assertSeeHtml( 'aria-expanded="false"' );
} );

it( 'ignores columns from other boards', function (): void {
    [ $board ]          = makeBoard( [ 1 ] );
    [ , $otherColumns ] = makeBoard( [ 1 ] );

    Livewire::test( Board::class, [ 'board' => $board ] )
        ->call( 'loadBoard', [] )
        ->call( 'toggleColumn', $otherColumns[0]->id )
        ->assertSet( 'collapsed', [] )
        ->call( 'showMore', $otherColumns[0]->id )
        ->assertSet( 'limits', [] );
} );

it( 'stores the collapsed columns in localStorage per board', function (): void {
    [ $board ] = makeBoard( [ 0 ] );

    Livewire::test( Board::class, [ 'board' => $board ] )
        ->assertSeeHtml( 'ecommerce-kanban.collapsed.' . $board->id )
        ->assertSeeHtml( '$wire.loadBoard' );
} );

it( 'scrolls horizontally with the keyboard and snaps on small screens', function (): void {
    [ $board ] = makeBoard( [ 0 ] );

    Livewire::test( Board::class, [ 'board' => $board ] )
        ->assertSeeHtml( 'overflow-x-auto' )
        ->assertSeeHtml( 'snap-x' )
        ->assertSeeHtml( 'tabindex="0"' )
        ->assertSeeHtml( 'aria-labelledby="ec-kb-board-title"' );
} );

it( 'renders a column of 25 cards in the same number of queries as a column of 5', function (): void {
    $queries = function ( int $cards ): int {
        [ $board ] = makeBoard( [ $cards ] );
        $component = Livewire::test( Board::class, [ 'board' => $board ] );

        DB::flushQueryLog();
        DB::enableQueryLog();
        $component->call( 'loadBoard', [] );
        $count = count( DB::getQueryLog() );
        DB::disableQueryLog();

        return $count;
    };

    // Warm the once-per-process lookups (the schema probe) first.
    $queries( 1 );
    $small = $queries( 5 );

    expect( $queries( 25 ) )->toBe( $small );
} );

it( 'lists the other boards in the header switcher and marks the current one', function (): void {
    [ $production ] = makeBoard( [ 0 ], [ 'name' => 'Production', 'position' => 0 ] );
    [ $shipping ]   = makeBoard( [ 0 ], [ 'name' => 'Shipping', 'position' => 1 ] );
    makeBoard( [ 0 ], [ 'name' => 'Archive', 'is_active' => false ] );

    Livewire::test( Board::class, [ 'board' => $production ] )
        ->assertSeeHtml( 'data-ec-kb-switcher' )
        ->assertSee( 'Switch board' )
        ->assertSeeInOrder( [ 'Production', 'Shipping', 'All boards' ] )
        ->assertSeeHtml( 'href="' . route( 'artisanpack.ecommerce.kanban.boards.show', [ 'board' => $shipping->id ] ) . '"' )
        ->assertSeeHtml( 'aria-current="page"' )
        ->assertSeeHtml( 'x-on:click.stop' )
        ->assertSeeHtml( route( 'artisanpack.ecommerce.kanban.boards.index', [ 'pick' => 1 ] ) )
        ->assertDontSee( 'Archive' );
} );

it( 'loads a board from its id and 404s for one that does not exist', function (): void {
    [ $board ] = makeBoard( [ 0 ], [ 'name' => 'Production' ] );

    Livewire::test( Board::class, [ 'board' => $board->id ] )->assertSee( 'Production' );
    Livewire::test( Board::class, [ 'board' => 99999 ] )->assertNotFound();
    Livewire::test( Board::class, [ 'board' => 'production' ] )->assertNotFound();
} );

it( 'is denied without kanbanBoard.view', function (): void {
    [ $board ] = makeBoard( [ 0 ] );
    denyAbilities( [ 'kanbanBoard.view' ] );

    Livewire::test( Board::class, [ 'board' => $board ] )->assertForbidden();
} );

it( 're-checks kanbanBoard.view on every update', function (): void {
    [ $board ] = makeBoard( [ 0 ] );
    $component = Livewire::test( Board::class, [ 'board' => $board ] );

    Gate::define( 'ecommerce.kanbanBoard.view', static fn (): bool => false );

    $component->call( 'loadBoard', [] )->assertForbidden();
} );
