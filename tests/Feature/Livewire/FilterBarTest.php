<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Kanban\KanbanCardFilters;
use ArtisanPackUI\Ecommerce\Kanban\KanbanCardQuery;
use ArtisanPackUI\Ecommerce\Models\Customer;
use ArtisanPackUI\Ecommerce\Models\OrderItem;
use ArtisanPackUI\Ecommerce\Models\Product;
use ArtisanPackUI\EcommerceKanbanLivewire\Livewire\Board;
use ArtisanPackUI\EcommerceKanbanLivewire\Support\FilterControls;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

beforeEach( function (): void {
    grantAbilities( [ 'kanbanBoard.viewAny', 'kanbanBoard.view' ] );
    $this->actingAs( makeUser() );
} );

afterEach( function (): void {
    removeAllFilters( FilterControls::FILTER );
    removeAllFilters( KanbanCardQuery::HOOK );
    Carbon::setTestNow();
} );

/**
 * Two columns: Printing (W-1 wholesale, R-1 rush) and Packing (W-2 wholesale).
 *
 * @return array{0: ArtisanPackUI\Ecommerce\Models\KanbanBoard, 1: Illuminate\Support\Collection<int, ArtisanPackUI\Ecommerce\Models\KanbanColumn>}
 */
function filterBoard(): array
{
    [ $board, $columns ] = makeBoard( [ [ 'cards' => 0, 'wip_limit' => 2 ], 0 ] );

    addCard( $columns[0], [ 'order_number' => 'W-1', 'meta' => [ 'tags' => [ 'wholesale' ] ], 'payment_status' => 'paid' ], [ 'position' => 0 ] );
    addCard( $columns[0], [ 'order_number' => 'R-1', 'meta' => [ 'tags' => [ 'rush' ] ], 'email' => 'rush@example.test' ], [ 'position' => 1 ] );
    addCard( $columns[1], [ 'order_number' => 'W-2', 'meta' => [ 'tags' => [ 'wholesale' ] ], 'fulfillment_status' => 'partial' ] );

    return [ $board, $columns ];
}

/**
 * The cards a board component shows.
 *
 * @return array<int, string>
 */
function shownCards( $component ): array
{
    preg_match_all( '/Order ([A-Z]-\d)<\/p>/', $component->html(), $matches );

    return $matches[1];
}

it( 'renders the filter bar inline and in a drawer for small screens', function (): void {
    [ $board ] = filterBoard();

    Livewire::test( Board::class, [ 'board' => $board ] )
        ->assertSeeHtml( 'data-ec-kb-filters="ec-kb-filters"' )
        ->assertSeeHtml( 'data-ec-kb-filters="ec-kb-filters-drawer"' )
        ->assertSeeHtml( 'role="search"' )
        ->assertSeeHtml( 'data-ec-kb-filters-toggle' )
        ->assertSee( [ 'Search', 'Customer', 'Tags', 'Products', 'Placed from', 'Placed to', 'Payment status', 'Fulfillment status', 'Stale cards only' ] )
        ->assertDontSeeHtml( 'data-ec-kb-clear-filters' )
        ->assertSet( 'tagOptions', [] )
        ->call( 'loadBoard', [] )
        ->assertSet( 'tagOptions', [ [ 'id' => 'rush', 'name' => 'rush' ], [ 'id' => 'wholesale', 'name' => 'wholesale' ] ] )
        ->assertSee( [ 'rush', 'wholesale' ] );
} );

it( 'searches by order number and email', function (): void {
    [ $board ] = filterBoard();

    $component = Livewire::test( Board::class, [ 'board' => $board ] )->call( 'loadBoard', [] );

    expect( shownCards( $component ) )->toBe( [ 'W-1', 'R-1', 'W-2' ] );
    expect( shownCards( $component->set( 'search', 'w-2' ) ) )->toBe( [ 'W-2' ] );
    expect( shownCards( $component->set( 'search', 'rush@' ) ) )->toBe( [ 'R-1' ] );
} );

it( 'filters by tag, payment, and fulfillment status, and shows filtered and real counts', function (): void {
    [ $board ] = filterBoard();

    $component = Livewire::test( Board::class, [ 'board' => $board ] )
        ->call( 'loadBoard', [] )
        ->set( 'tags', [ 'wholesale' ] );

    expect( shownCards( $component ) )->toBe( [ 'W-1', 'W-2' ] );

    $component->assertSee( '1 shown' )
        ->assertSeeHtml( 'data-ec-kb-matching' )
        ->assertSeeHtml( '2 / 2' )
        ->assertSee( '2 of 2 cards, WIP limit reached' )
        ->assertSeeHtml( 'data-ec-kb-clear-filters' )
        ->assertSee( '1 filter active' );

    expect( shownCards( $component->set( 'tags', [] )->set( 'paymentStatus', [ 'paid' ] ) ) )->toBe( [ 'W-1' ] );
    expect( shownCards( $component->set( 'paymentStatus', [] )->set( 'fulfillmentStatus', [ 'partial' ] ) ) )->toBe( [ 'W-2' ] );
} );

it( 'filters by customer and product with server-searched options', function (): void {
    [ $board, $columns ] = filterBoard();
    $customer            = Customer::factory()->create( [ 'first_name' => 'Grace', 'last_name' => 'Hopper', 'email' => 'grace@example.test' ] );
    $shirt               = Product::factory()->create( [ 'name' => 'Tour shirt', 'sku' => 'SHIRT-1' ] );
    $mine                = addCard( $columns[1], [ 'order_number' => 'C-1', 'customer_id' => $customer->id ] );
    OrderItem::factory()->create( [ 'order_id' => $mine->order_id, 'product_id' => $shirt->id ] );

    $component = Livewire::test( Board::class, [ 'board' => $board ] )
        ->call( 'loadBoard', [] )
        ->call( 'searchCustomers', 'hopp' )
        ->assertSet( 'customerOptions', [ [ 'id' => $customer->id, 'name' => 'Grace Hopper (grace@example.test)' ] ] )
        ->set( 'customerId', $customer->id );

    expect( shownCards( $component ) )->toBe( [ 'C-1' ] );

    $component->set( 'customerId', null )
        ->call( 'searchProducts', 'shirt-1' )
        ->assertSet( 'productOptions', [ [ 'id' => $shirt->id, 'name' => 'Tour shirt (SHIRT-1)' ] ] )
        ->set( 'productIds', [ $shirt->id ] );

    expect( shownCards( $component ) )->toBe( [ 'C-1' ] );
} );

it( 'filters by placed date range and stale cards', function (): void {
    Carbon::setTestNow( '2026-10-20 12:00:00' );
    [ $board, $columns ] = filterBoard();
    addCard( $columns[0], [ 'order_number' => 'O-1', 'placed_at' => '2026-10-01 09:00:00' ], [ 'moved_at' => '2026-10-02 09:00:00', 'position' => 5 ] );

    $component = Livewire::test( Board::class, [ 'board' => $board ] )
        ->call( 'loadBoard', [] )
        ->set( 'placedFrom', '2026-09-30' )
        ->set( 'placedTo', '2026-10-01' );

    expect( shownCards( $component ) )->toBe( [ 'O-1' ] );
    expect( shownCards( $component->set( 'placedFrom', '' )->set( 'placedTo', '' )->set( 'stale', true ) ) )->toBe( [ 'O-1' ] );
} );

it( 'reads filters from the URL, ignores junk, and clears them', function (): void {
    [ $board ] = filterBoard();

    $component = Livewire::withQueryParams( [ 'tags' => [ 'rush' ], 'q' => [ 'not', 'a', 'string' ], 'customer' => 'abc', 'from' => 'yesterday' ] )
        ->test( Board::class, [ 'board' => $board ] )
        ->call( 'loadBoard', [] );

    expect( shownCards( $component ) )->toBe( [ 'R-1' ] );

    $component->call( 'clearFilters' )
        ->assertSet( 'tags', [] )
        ->assertSet( 'search', '' );

    expect( shownCards( $component ) )->toBe( [ 'W-1', 'R-1', 'W-2' ] );
} );

it( 'resets column paging when a filter changes', function (): void {
    config()->set( 'artisanpack.ecommerce-kanban-livewire.cards.per_column', 1 );
    [ $board, $columns ] = filterBoard();

    Livewire::test( Board::class, [ 'board' => $board ] )
        ->call( 'loadBoard', [] )
        ->call( 'showMore', $columns[0]->id )
        ->assertSet( 'limits', [ $columns[0]->id => 2 ] )
        ->set( 'search', 'w' )
        ->assertSet( 'limits', [] );
} );

it( 'shows a no-matches state for columns a filter empties', function (): void {
    [ $board ] = filterBoard();

    Livewire::test( Board::class, [ 'board' => $board ] )
        ->call( 'loadBoard', [] )
        ->set( 'search', 'R-1' )
        ->assertSee( 'No matching orders in this column' );
} );

it( 'adds satellite filter controls paired with the engine card query', function (): void {
    [ $board, $columns ] = filterBoard();
    addCard( $columns[0], [ 'order_number' => 'G-1', 'meta' => [ 'gift' => true ] ] );

    addFilter( FilterControls::FILTER, fn ( array $controls ): array => [
        ...$controls,
        [ 'key' => 'gift', 'label' => 'Gift orders', 'type' => 'toggle' ],
        [ 'key' => 'search', 'label' => 'Shadows a core filter', 'type' => 'text' ],
        [ 'key' => 'Bad Key', 'label' => 'Bad key', 'type' => 'text' ],
        [ 'key' => 'channel', 'label' => 'Channel', 'type' => 'select' ],
    ] );
    addFilter( KanbanCardQuery::HOOK, function ( Builder $query, KanbanCardFilters $filters ): Builder {
        if ( filter_var( $filters->extra['gift'] ?? false, FILTER_VALIDATE_BOOLEAN ) ) {
            $query->whereHas( 'order', fn ( Builder $orders ) => $orders->where( 'meta->gift', true ) );
        }

        return $query;
    } );

    $component = Livewire::test( Board::class, [ 'board' => $board ] )
        ->call( 'loadBoard', [] )
        ->assertSee( 'Gift orders' )
        ->assertDontSee( 'Shadows a core filter' )
        ->assertDontSee( 'Bad key' )
        ->assertDontSee( 'Channel' )
        ->set( 'extraFilters.gift', true );

    expect( shownCards( $component ) )->toBe( [ 'G-1' ] );
} );

it( 'only offers customers and products from orders on this board', function (): void {
    [ $board, $columns ] = filterBoard();
    [ , $otherColumns ]  = makeBoard( [ 0 ] );
    $here                = Customer::factory()->create( [ 'first_name' => 'Ada', 'last_name' => 'Here' ] );
    $elsewhere           = Customer::factory()->create( [ 'first_name' => 'Ada', 'last_name' => 'Elsewhere' ] );
    $nowhere             = Customer::factory()->create( [ 'first_name' => 'Ada', 'last_name' => 'Nowhere' ] );
    $onBoard             = Product::factory()->create( [ 'name' => 'Board mug' ] );
    $offBoard            = Product::factory()->create( [ 'name' => 'Other mug' ] );

    OrderItem::factory()->create( [ 'order_id' => addCard( $columns[0], [ 'customer_id' => $here->id ] )->order_id, 'product_id' => $onBoard->id ] );
    OrderItem::factory()->create( [ 'order_id' => addCard( $otherColumns[0], [ 'customer_id' => $elsewhere->id ] )->order_id, 'product_id' => $offBoard->id ] );

    $component = Livewire::test( Board::class, [ 'board' => $board ] )
        ->call( 'searchCustomers', 'ada' )
        ->call( 'searchProducts', 'mug' );

    expect( array_column( $component->get( 'customerOptions' ), 'id' ) )->toBe( [ $here->id ] )
        ->and( array_column( $component->get( 'productOptions' ), 'id' ) )->toBe( [ $onBoard->id ] );

    // A customer id typed into the URL doesn't reveal a customer from elsewhere.
    $component->set( 'customerId', $nowhere->id )->call( 'searchCustomers', '' );

    expect( array_column( $component->get( 'customerOptions' ), 'id' ) )->not->toContain( $nowhere->id );

    // Nor does a product id typed into the URL.
    $component->set( 'productIds', [ $offBoard->id ] )->call( 'searchProducts', '' );

    expect( array_column( $component->get( 'productOptions' ), 'id' ) )->not->toContain( $offBoard->id );

    Livewire::withQueryParams( [ 'products' => [ $offBoard->id ] ] )
        ->test( Board::class, [ 'board' => $board ] )
        ->assertDontSee( 'Other mug' );
} );

it( 'keeps the customer and product options server-side', function ( string $property ): void {
    [ $board ] = filterBoard();

    Livewire::test( Board::class, [ 'board' => $board ] )->set( $property, [ [ 'id' => 1, 'name' => 'Spoofed' ] ] );
} )->with( [ 'customerOptions', 'productOptions' ] )->throws( CannotUpdateLockedPropertyException::class );

it( 'labels the date inputs, hides the filters toggle on wide screens, and replaces the tags control when its options change', function (): void {
    [ $board ] = filterBoard();

    $component = Livewire::test( Board::class, [ 'board' => $board ] );
    $before    = $component->html();
    $after     = $component->call( 'loadBoard', [] )->html();

    $tagsKey = static fn ( string $html ): string => preg_match( '/wire:key="(ec-kb-filters-tags-[0-9a-f]{32})"/', $html, $matches ) ? $matches[1] : '';

    expect( $after )->toContain( 'aria-label="Placed from"' )
        ->and( $after )->toContain( 'aria-label="Placed to"' )
        ->and( $after )->toMatch( '/<div class="md:hidden">\s*(<!--\[if BLOCK\]><!\[endif\]-->)?\s*<button[^>]*data-ec-kb-filters-toggle/' )
        ->and( $tagsKey( $before ) )->not->toBe( '' )
        ->and( $tagsKey( $after ) )->not->toBe( $tagsKey( $before ) );
} );
