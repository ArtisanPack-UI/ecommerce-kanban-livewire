<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Kanban\KanbanCardQuery;
use ArtisanPackUI\Ecommerce\Models\Customer;
use ArtisanPackUI\Ecommerce\Models\KanbanBoard;
use ArtisanPackUI\Ecommerce\Models\Order;
use ArtisanPackUI\Ecommerce\Models\OrderBoardAssignment;
use ArtisanPackUI\Ecommerce\Models\OrderItem;
use ArtisanPackUI\Ecommerce\Models\Product;
use ArtisanPackUI\EcommerceKanbanLivewire\Livewire\Board;
use ArtisanPackUI\EcommerceKanbanLivewire\Livewire\BoardPicker;
use ArtisanPackUI\EcommerceKanbanLivewire\Livewire\CardDetail;
use ArtisanPackUI\EcommerceKanbanLivewire\Livewire\WidgetPicker;
use ArtisanPackUI\EcommerceKanbanLivewire\Queries\BoardsQuery;
use ArtisanPackUI\EcommerceKanbanLivewire\Queries\ColumnCardsQuery;
use ArtisanPackUI\EcommerceKanbanLivewire\Queries\VisibleCards;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Livewire;

/*
 * B4: an `ap.ecommerce.kanban.cardQuery` filter is a visibility boundary. A
 * card it hides is refused or left out on every path, not just the board's
 * card lists.
 */

beforeEach( function (): void {
    grantAbilities( [ 'kanbanBoard.viewAny', 'kanbanBoard.view', 'kanbanBoard.update', 'kanbanCard.move', 'order.view' ] );
    $this->actingAs( makeUser() );
} );

afterEach( function (): void {
    removeAllFilters( KanbanCardQuery::HOOK );
} );

/**
 * A board with processing columns Printing and Packing and an exit column
 * Cancelled. Printing holds a visible order (SHOWN, customer Ada, product
 * Visible mug, tag `public`) and a hidden one (HIDDEN, customer Brook,
 * product Hidden mug, tag `secret`); a cardQuery filter hides HIDDEN.
 *
 * @return array{board: KanbanBoard, columns: Collection<int, ArtisanPackUI\Ecommerce\Models\KanbanColumn>, shown: Order, hidden: Order, shownCustomer: Customer, hiddenCustomer: Customer, shownProduct: Product, hiddenProduct: Product}
 */
function hiddenCardBoard(): array
{
    [ $board, $columns ] = makeBoard( [ 0, 0, 0 ] );

    $columns[0]->update( [ 'label_override' => 'Printing' ] );
    $columns[1]->update( [ 'label_override' => 'Packing' ] );
    $columns[0]->substatus->update( [ 'system_status' => 'processing' ] );
    $columns[1]->substatus->update( [ 'system_status' => 'processing' ] );
    $columns[2]->substatus->update( [ 'system_status' => 'cancelled', 'is_terminal' => true ] );

    $shownCustomer  = Customer::factory()->create( [ 'first_name' => 'Ada', 'last_name' => 'Shown', 'email' => 'ada@example.test' ] );
    $hiddenCustomer = Customer::factory()->create( [ 'first_name' => 'Brook', 'last_name' => 'Hidden', 'email' => 'brook@example.test' ] );
    $shownProduct   = Product::factory()->create( [ 'name' => 'Visible mug', 'sku' => 'MUG-V' ] );
    $hiddenProduct  = Product::factory()->create( [ 'name' => 'Hidden mug', 'sku' => 'MUG-H' ] );

    $shown  = addCard( $columns[0], [ 'order_number' => 'SHOWN', 'system_status' => 'processing', 'customer_id' => $shownCustomer->id, 'meta' => [ 'tags' => [ 'public' ] ] ], [ 'position' => 0 ] )->order;
    $hidden = addCard( $columns[0], [ 'order_number' => 'HIDDEN', 'system_status' => 'processing', 'customer_id' => $hiddenCustomer->id, 'meta' => [ 'tags' => [ 'secret' ] ] ], [ 'position' => 1 ] )->order;

    OrderItem::factory()->create( [ 'order_id' => $shown->id, 'product_id' => $shownProduct->id ] );
    OrderItem::factory()->create( [ 'order_id' => $hidden->id, 'product_id' => $hiddenProduct->id ] );

    hideOrder( (int) $hidden->id );

    return [
        'board'          => $board,
        'columns'        => $columns->map( static fn ( $column ) => $column->fresh( 'substatus' ) ),
        'shown'          => $shown,
        'hidden'         => $hidden,
        'shownCustomer'  => $shownCustomer,
        'hiddenCustomer' => $hiddenCustomer,
        'shownProduct'   => $shownProduct,
        'hiddenProduct'  => $hiddenProduct,
    ];
}

/**
 * Registers a cardQuery filter that hides `$orderId`'s cards.
 */
function hideOrder( int $orderId ): void
{
    addFilter( KanbanCardQuery::HOOK, static function ( Builder $query ) use ( $orderId ): Builder {
        return $query->where( $query->qualifyColumn( 'order_id' ), '!=', $orderId );
    } );
}

it( 'finds only visible cards', function (): void {
    $setup = hiddenCardBoard();

    expect( VisibleCards::assignment( $setup['board'], (int) $setup['shown']->id ) )->toBeInstanceOf( OrderBoardAssignment::class )
        ->and( VisibleCards::assignment( $setup['board'], (int) $setup['hidden']->id ) )->toBeNull()
        ->and( VisibleCards::count( $setup['board'] ) )->toBe( 1 )
        ->and( VisibleCards::orderIds( $setup['board'] )->pluck( 'order_id' )->map( 'intval' )->all() )->toBe( [ (int) $setup['shown']->id ] );
} );

it( 'refuses to move a hidden card, and does not name it in an exit-column confirmation', function (): void {
    $setup     = hiddenCardBoard();
    $component = Livewire::test( Board::class, [ 'board' => $setup['board'] ] )->call( 'loadBoard', [] );

    expect( $component->call( 'moveCard', $setup['hidden']->id, $setup['columns'][1]->id )->effects['returns'][0] )->toBe( 'rejected' )
        ->and( toastsOf( $component ) )->toContain( 'This order is no longer on this board.' );

    expect( $component->call( 'moveCard', $setup['hidden']->id, $setup['columns'][2]->id )->effects['returns'][0] )->toBe( 'rejected' );

    $component->assertSet( 'pendingMove', null )
        ->assertSet( 'confirmingMove', false )
        ->assertDontSee( 'HIDDEN' );

    expect( OrderBoardAssignment::query()->active()->where( 'order_id', $setup['hidden']->id )->value( 'substatus_id' ) )->toBe( $setup['columns'][0]->substatus_id )
        ->and( $setup['hidden']->fresh()->system_status )->toBe( 'processing' );
} );

it( 'still moves a visible card', function (): void {
    $setup     = hiddenCardBoard();
    $component = Livewire::test( Board::class, [ 'board' => $setup['board'] ] )->call( 'loadBoard', [] );

    expect( $component->call( 'moveCard', $setup['shown']->id, $setup['columns'][1]->id )->effects['returns'][0] )->toBe( 'moved' );
} );

it( 'does not open "Move to…" or "Remove from board" for a hidden card', function (): void {
    $setup = hiddenCardBoard();

    Livewire::test( Board::class, [ 'board' => $setup['board'] ] )
        ->call( 'loadBoard', [] )
        ->call( 'openMoveDialog', $setup['hidden']->id )
        ->assertSet( 'moveDialog', false )
        ->assertSet( 'moveOrderId', null )
        ->call( 'confirmRemoveCard', $setup['hidden']->id )
        ->assertSet( 'confirmingRemove', false )
        ->assertSet( 'removingOrderId', null )
        ->assertDontSee( 'HIDDEN' );
} );

it( 'does not open a hidden card in the slide-out, by event or by ?card=', function (): void {
    $setup = hiddenCardBoard();

    Livewire::test( CardDetail::class, [ 'boardId' => $setup['board']->id ] )
        ->call( 'show', $setup['hidden']->id )
        ->assertSet( 'open', false )
        ->assertSet( 'orderId', null )
        ->assertDontSee( 'HIDDEN' );

    Livewire::withQueryParams( [ 'card' => $setup['hidden']->id ] )
        ->test( CardDetail::class, [ 'boardId' => $setup['board']->id ] )
        ->assertSet( 'open', false )
        ->assertDontSee( 'HIDDEN' );

    Livewire::withQueryParams( [ 'card' => $setup['shown']->id ] )
        ->test( CardDetail::class, [ 'boardId' => $setup['board']->id ] )
        ->assertSet( 'open', true );
} );

it( 'leaves a hidden card\'s customer, products, and tags out of the filter options', function (): void {
    $setup     = hiddenCardBoard();
    $component = Livewire::test( Board::class, [ 'board' => $setup['board'] ] )
        ->call( 'loadBoard', [] )
        ->call( 'searchCustomers', '' )
        ->call( 'searchProducts', '' );

    expect( array_column( $component->get( 'customerOptions' ), 'id' ) )->toBe( [ $setup['shownCustomer']->id ] )
        ->and( array_column( $component->get( 'productOptions' ), 'id' ) )->toBe( [ $setup['shownProduct']->id ] )
        ->and( array_column( $component->get( 'tagOptions' ), 'id' ) )->toBe( [ 'public' ] );

    $component->call( 'searchCustomers', 'brook' )->assertSet( 'customerOptions', [] );
} );

it( 'does not offer a hidden card\'s customer for an id typed into the URL', function (): void {
    $setup = hiddenCardBoard();

    $component = Livewire::withQueryParams( [ 'customer' => $setup['hiddenCustomer']->id ] )
        ->test( Board::class, [ 'board' => $setup['board'] ] );

    expect( array_column( $component->get( 'customerOptions' ), 'id' ) )->not->toContain( $setup['hiddenCustomer']->id );
} );

it( 'previews only a visible card in the widget picker', function (): void {
    $setup = hiddenCardBoard();

    Livewire::test( WidgetPicker::class, [ 'boardId' => $setup['board']->id ] )
        ->call( 'openFor', $setup['columns'][0]->id )
        ->assertSee( 'SHOWN' )
        ->assertDontSee( 'HIDDEN' );

    hideOrder( (int) $setup['shown']->id );

    Livewire::test( WidgetPicker::class, [ 'boardId' => $setup['board']->id ] )
        ->call( 'openFor', $setup['columns'][0]->id )
        ->assertDontSee( 'SHOWN' )
        ->assertDontSee( 'HIDDEN' )
        ->assertSee( 'Put an order on this board to preview its card.' );
} );

it( 'counts only visible cards in the board picker', function (): void {
    $setup = hiddenCardBoard();

    expect( (int) BoardsQuery::visibleTo( auth()->user() )->firstWhere( 'id', $setup['board']->id )->cards_count )->toBe( 1 );

    Livewire::test( BoardPicker::class, [ 'pick' => true ] )
        ->assertSee( '1 card' )
        ->assertDontSee( '2 cards' );
} );

it( 'counts only visible cards as matching, while the WIP total keeps every card', function (): void {
    $setup  = hiddenCardBoard();
    $counts = ColumnCardsQuery::counts( $setup['board'], $setup['board']->columns()->get() );

    expect( $counts[ (int) $setup['columns'][0]->id ] )->toBe( [ 'count' => 1, 'total' => 2 ] );

    config()->set( 'artisanpack.ecommerce-kanban-livewire.cards.per_column', 1 );

    // The one visible card fills the page, so there is no "Show more" for the hidden one.
    Livewire::test( Board::class, [ 'board' => $setup['board'] ] )
        ->call( 'loadBoard', [] )
        ->assertSee( 'SHOWN' )
        ->assertDontSee( 'HIDDEN' )
        ->assertDontSeeHtml( 'data-ec-kb-show-more' );
} );

it( 'leaves hidden cards out of the polling signatures', function (): void {
    $setup   = hiddenCardBoard();
    $columns = $setup['board']->columns()->get();
    $before  = ColumnCardsQuery::signatures( $setup['board'], $columns );

    OrderBoardAssignment::query()->where( 'order_id', $setup['hidden']->id )->update( [ 'position' => 99 ] );

    expect( ColumnCardsQuery::signatures( $setup['board'], $columns ) )->toBe( $before );
} );
