<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Models\Order;
use ArtisanPackUI\Ecommerce\Models\OrderSubstatus;
use ArtisanPackUI\EcommerceKanbanLivewire\Livewire\Board;
use ArtisanPackUI\EcommerceKanbanLivewire\Livewire\BoardPicker;
use ArtisanPackUI\EcommerceKanbanLivewire\Support\CardActions;
use ArtisanPackUI\EcommerceKanbanLivewire\Support\LastBoard;
use Livewire\Livewire;

beforeEach( function (): void {
    grantAbilities( [ 'kanbanBoard.viewAny', 'kanbanBoard.view', 'kanbanBoard.update', 'kanbanCard.move', 'order.view' ] );
    $this->user = makeUser();
    $this->actingAs( $this->user );
} );

/**
 * The markup of one of the board's dialogs: from its host marker to the
 * next dialog host (or the end).
 */
function dialogHtml( string $html, string $marker ): string
{
    $from = (string) strstr( $html, $marker );
    $next = strpos( $from, '<div data-ec-kb-', 1 );

    return false === $next ? $from : substr( $from, 0, $next );
}

/**
 * A board with processing columns A and B and a Cancelled exit column.
 *
 * @return array{0: ArtisanPackUI\Ecommerce\Models\KanbanBoard, 1: Illuminate\Support\Collection<int, ArtisanPackUI\Ecommerce\Models\KanbanColumn>}
 */
function designBoard( array $cards = [ 0, 0, 0 ], array $attributes = [] ): array
{
    [ $board, $columns ] = makeBoard( [
        [ 'cards' => $cards[0], 'label_override' => 'A' ],
        [ 'cards' => $cards[1], 'label_override' => 'B' ],
        [ 'cards' => $cards[2], 'label_override' => 'Cancelled' ],
    ], $attributes );

    $columns[0]->substatus->update( [ 'system_status' => 'processing' ] );
    $columns[1]->substatus->update( [ 'system_status' => 'processing' ] );
    $columns[2]->substatus->update( [ 'system_status' => 'cancelled', 'is_terminal' => true ] );

    return [ $board, $columns ];
}

it( 'lists boards as linked cards under a page header with a description', function (): void {
    [ $board ] = makeBoard( [ 1 ], [ 'name' => 'Fulfillment', 'description' => 'Paid orders' ] );
    makeBoard( [ 0 ], [ 'name' => 'Returns' ] );
    LastBoard::remember( $this->user, $board );

    $html = Livewire::test( BoardPicker::class, [ 'pick' => true ] )->html();

    expect( $html )->toContain( 'apui-page-header' )
        ->toMatch( '/<h1[^>]*data-part="title"[^>]*>\s*Kanban boards/' )
        ->toContain( 'Pick a board to work orders through its columns.' )
        ->toMatch( '/<h2 class="apui-sr-only" id="ec-kb-picker-boards">Boards<\/h2>\s*<ul class="ec-kb-picker-grid" aria-labelledby="ec-kb-picker-boards">/' )
        ->toMatch( '/<h3[^>]*data-part="title"[^>]*>(<!--.*?-->)?<a href="[^"]+">Fulfillment<\/a>/' )
        ->toMatch( '/data-ec-kb-board="[^"]+"[^>]*data-interactive/s' )
        ->toMatch( '/data-ec-kb-last-board>.*Last opened/s' )
        ->not->toContain( 'class="grid' );
} );

it( 'leaves the description out of the empty picker', function (): void {
    Livewire::test( BoardPicker::class )
        ->assertSee( 'No boards yet' )
        ->assertDontSee( 'Pick a board to work orders through its columns.' );
} );

it( 'puts the board name, switcher and toolbar in a page header with Add order as the one solid action', function (): void {
    [ $board ] = designBoard( attributes: [ 'name' => 'Fulfillment', 'description' => 'Paid orders' ] );
    makeBoard( [ 0 ], [ 'name' => 'Wholesale' ] );

    $html = Livewire::test( Board::class, [ 'board' => $board ] )->call( 'loadBoard', [] )->html();

    expect( $html )->toMatch( '/<h1[^>]*data-part="title"[^>]*><span id="ec-kb-board-title">Fulfillment<\/span><\/h1>/' )
        ->toMatch( '/data-part="meta".*data-ec-kb-switcher/s' )
        ->toMatch( '/ec-kb-switcher-trigger.*<span class="apui-sr-only">Switch board<\/span>/s' )
        ->toMatch( '/data-part="description"[^>]*>Paid orders/' )
        ->toMatch( '/data-ec-kb-automations[^>]*data-variant="ghost"/' )
        ->toMatch( '/data-ec-kb-edit-board[^>]*data-variant="outline"/' )
        ->toMatch( '/data-ec-kb-add-order[^>]*data-variant="solid"/' )
        ->toContain( 'aria-labelledby="ec-kb-board-title"' );

    $automations = strpos( $html, 'data-ec-kb-automations' );
    $edit        = strpos( $html, 'data-ec-kb-edit-board' );
    $add         = strpos( $html, 'data-ec-kb-add-order' );

    expect( $automations )->toBeLessThan( $edit )
        ->and( $edit )->toBeLessThan( $add );
} );

it( 'shows every connection state as a badge in a status region', function (): void {
    [ $board ] = designBoard();

    $html = Livewire::test( Board::class, [ 'board' => $board ] )->html();

    expect( $html )->toMatch( '/role="status"\s+data-ec-kb-connection/' );

    foreach ( [ 'success' => 'Live', 'warning' => 'Updates every', 'error' => 'Offline', 'neutral' => 'Not updating automatically' ] as $color => $label ) {
        expect( $html )->toMatch( '/class="apui-badge"[^>]*data-variant="ghost"[^>]*data-color="' . $color . '"[^>]*>.*?' . $label . '/s' );
    }
} );

it( 'titles the inactive and edit-mode notices, marks edit mode and shows the column grip', function (): void {
    [ $board ] = designBoard( attributes: [ 'is_active' => false ] );

    $component = Livewire::test( Board::class, [ 'board' => $board ] )->call( 'loadBoard', [] );

    $component->assertSee( 'This board is switched off' )
        ->assertSee( 'Only people who can edit it see it.' )
        ->assertDontSeeHtml( 'data-ec-kb-editing ' )
        ->assertDontSeeHtml( 'data-ec-kb-column-grip' )
        ->call( 'startEditing' )
        ->assertSeeHtml( 'data-ec-kb-editing' )
        ->assertSeeHtml( 'data-ec-kb-column-grip' )
        ->assertSee( 'Editing the board' )
        ->assertSee( 'Drag column headers or use the arrows to reorder columns. Changes save as you make them.' )
        ->assertSeeHtml( 'ec-kb-add-column-button' );

    $html = $component->html();

    expect( strpos( $html, 'data-ec-kb-board-widgets' ) )->toBeLessThan( strpos( $html, 'data-ec-kb-stop-editing' ) )
        ->and( $html )->not->toContain( 'data-ec-kb-automations' );
} );

it( 'describes the Move to dialog and puts its buttons in the footer', function (): void {
    [ $board, $columns ] = designBoard();
    $card                = addCard( $columns[0], [ 'order_number' => 'P1', 'system_status' => 'processing' ] );

    $html = Livewire::test( Board::class, [ 'board' => $board ] )
        ->call( 'loadBoard', [] )
        ->call( 'openMoveDialog', $card->order_id )
        ->html();

    $dialog = dialogHtml( $html, 'data-ec-kb-move-dialog' );

    expect( $dialog )->toContain( 'The keyboard and single-pointer way to move a card.' )
        ->toContain( 'id="ec-kb-move-form"' )
        ->toMatch( '/data-part="footer".*form="ec-kb-move-form"/s' );
} );

it( 'confirms an exit-column move in a warning alertdialog', function (): void {
    [ $board, $columns ] = designBoard();
    $card                = addCard( $columns[0], [ 'order_number' => 'P1', 'system_status' => 'processing' ] );

    $component = Livewire::test( Board::class, [ 'board' => $board ] )->call( 'loadBoard', [] );
    $component->call( 'moveCard', $card->order_id, $columns[2]->id );

    $dialog = dialogHtml( $component->html(), 'data-ec-kb-confirm-move' );

    expect( $dialog )->toMatch( '/role="alertdialog"/' )
        ->toContain( 'data-color="warning"' )
        ->toMatch( '/data-part="description"[^>]*>This changes the whole order&#039;s status to/' )
        ->toContain( 'data-part="icon"' );
} );

it( 'confirms removing a card in an error alertdialog', function (): void {
    [ $board, $columns ] = designBoard();
    $card                = addCard( $columns[0], [ 'order_number' => 'D-1', 'system_status' => 'processing' ] );

    $html = Livewire::test( Board::class, [ 'board' => $board ] )
        ->call( 'loadBoard', [] )
        ->dispatch( CardActions::REMOVE_EVENT, orderId: $card->order_id )
        ->html();

    $dialog = dialogHtml( $html, 'data-ec-kb-confirm-remove' );

    expect( $dialog )->toMatch( '/role="alertdialog"/' )
        ->toContain( 'data-color="error"' )
        ->toMatch( '/data-color="error"[^>]*data-ec-kb-remove-button|data-ec-kb-remove-button/' );
} );

it( 'lists add-order results with the customer, total and date, and disables Add to board without a match', function (): void {
    [ $board ] = designBoard();
    Order::factory()->create( [
        'order_number'    => 'R-2001',
        'email'           => 'jonah@example.test',
        'system_status'   => 'processing',
        'billing_address' => [ 'first_name' => 'Jonah', 'last_name' => 'Reyes' ],
    ] );

    $component = Livewire::test( Board::class, [ 'board' => $board ] )
        ->call( 'loadBoard', [] )
        ->call( 'openAddOrder' );

    $results = $component->set( 'addOrderSearch', 'r-20' )->viewData( 'addOrderResults' );

    expect( $results )->toHaveCount( 1 )
        ->and( $results[0]['name'] )->toBe( 'Order R-2001 · Jonah Reyes' )
        ->and( $results[0]['description'] )->toContain( ' · placed ' );

    Order::factory()->create( [ 'order_number' => 'R-2002', 'system_status' => 'processing', 'placed_at' => null ] );

    $unplaced = collect( $component->set( 'addOrderSearch', 'r-2002' )->viewData( 'addOrderResults' ) )->first();

    expect( $unplaced['description'] )->not->toContain( 'placed' )->not->toBe( '' );

    $component->set( 'addOrderSearch', 'r-2001' );

    $dialog = dialogHtml( $component->html(), 'data-ec-kb-add-order-dialog' );

    expect( $dialog )->toContain( 'Only orders that aren&#039;t on this board are listed.' )
        ->toContain( 'data-part="item-description"' )
        ->not->toMatch( '/<button[^>]*\\sdisabled[\\s>=][^>]*data-ec-kb-add-order-submit/' );

    $none = dialogHtml( $component->set( 'addOrderSearch', 'nothing-matches' )->html(), 'data-ec-kb-add-order-dialog' );

    expect( $none )->toContain( 'data-ec-kb-add-order-none' )
        ->toMatch( '/<button[^>]*\\sdisabled[\\s>=][^>]*data-ec-kb-add-order-submit/' )
        ->not->toContain( 'data-ec-kb-add-order-column' );
} );

it( 'offers only Close when every sub-status has a column', function (): void {
    [ $board ] = designBoard();
    OrderSubstatus::query()->whereNotIn( 'id', $board->columns()->pluck( 'substatus_id' ) )->delete();

    $html = Livewire::test( Board::class, [ 'board' => $board ] )
        ->call( 'startEditing' )
        ->call( 'openAddColumn' )
        ->html();

    $dialog = dialogHtml( $html, 'data-ec-kb-add-column-dialog' );

    expect( $dialog )->toMatch( '/data-part="description"[^>]*>Every sub-status already has a column on this board./' )
        ->toContain( 'data-ec-kb-no-substatuses' )
        ->not->toContain( 'data-ec-kb-add-column-submit' );
} );

it( 'keeps Remove column disabled until an exit-column move is acknowledged', function (): void {
    [ $board, $columns ] = designBoard( [ 2, 0, 0 ] );

    $component = Livewire::test( Board::class, [ 'board' => $board ] )
        ->call( 'loadBoard', [] )
        ->call( 'startEditing' )
        ->call( 'confirmDeleteColumn', $columns[0]->id )
        ->set( 'deleteMoveTo', $columns[2]->id );

    $dialog = dialogHtml( $component->html(), 'data-ec-kb-confirm-delete-column' );

    expect( $dialog )->toMatch( '/role="alertdialog"/' )
        ->toContain( 'data-color="error"' )
        ->toContain( 'This column has 2 cards.' )
        ->toContain( 'Automations that move cards into this column are removed with it.' )
        ->toMatch( '/<button[^>]*\\sdisabled[\\s>=][^>]*data-ec-kb-delete-column-button/' );

    $acknowledged = dialogHtml( $component->set( 'confirmDeleteExit', true )->html(), 'data-ec-kb-confirm-delete-column' );

    expect( $acknowledged )->not->toMatch( '/<button[^>]*\\sdisabled[\\s>=][^>]*data-ec-kb-delete-column-button/' );
} );
