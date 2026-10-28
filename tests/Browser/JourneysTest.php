<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Models\KanbanColumn;
use ArtisanPackUI\Ecommerce\Models\Order;
use ArtisanPackUI\Ecommerce\Models\OrderBoardAssignment;
use Tests\Browser\Support\JavaScript;
use Tests\Browser\Support\Locators;

/*
 * The board journeys from plan §15.4 and spec §13 (#23), end to end in a
 * browser against the seeded demo board (see BrowserTestCase).
 */

/**
 * The card selector for demo order `$number`.
 */
function card( string $number ): string
{
    return '[data-ec-kb-card="' . test()->orders[ $number ]->id . '"]';
}

/**
 * The card-list selector for the demo column labelled `$label`.
 */
function cards( string $label ): string
{
    return '[data-ec-kb-cards="' . test()->columns[ $label ]->id . '"]';
}

/**
 * JavaScript: the order numbers in the demo column labelled `$label`, top
 * to bottom, as a comma-separated string.
 */
function numbersIn( string $label ): string
{
    $ids = json_encode( array_flip( array_map( static fn ( Order $order ): int => (int) $order->id, test()->orders ) ) );

    return '( ( ids ) => Array.from( document.querySelectorAll( ' . json_encode( cards( $label ) . ' [data-ec-kb-card]' ) . ' ) ).map( ( card ) => ids[ card.dataset.ecKbCard ] ).join( "," ) )( ' . $ids . ' )';
}

/**
 * JavaScript: whether the focused element matches `$selector`.
 */
function focused( string $selector ): string
{
    return 'document.activeElement?.matches( ' . json_encode( $selector ) . ' ) === true';
}

/**
 * JavaScript: whether no card on the board is waiting on a move.
 */
function settled(): string
{
    return '! document.querySelector( \'[data-ec-kb-card][data-ec-kb-state="moving"]\' )';
}

/**
 * The column demo order `$number` is in, read from the engine.
 */
function columnOf( string $number ): string
{
    $assignment = OrderBoardAssignment::query()->where( 'order_id', test()->orders[ $number ]->id )->where( 'board_id', test()->board->id )->firstOrFail();

    return (string) collect( test()->columns )->search( static fn ( KanbanColumn $column ): bool => (int) $column->substatus_id === (int) $assignment->substatus_id );
}

/**
 * Opens the "Move to…" dialog for demo order `$number`.
 *
 * @param  object  $page  The page (a Pest browser webpage).
 *
 * @return object
 */
function openMoveDialog( object $page, string $number ): object
{
    return $page->click( Locators::role( 'Actions for order ' . $number ) )
        ->click( card( $number ) . ' [data-ec-kb-action="move"]' )
        ->assertVisible( '[data-ec-kb-move-submit]' );
}

it( 'drags a card to another column and runs the automation (plan §15.4)', function (): void {
    $page = visit( $this->boardUrl() )->assertPresent( card( '1001' ) );

    $page->drag( card( '1001' ), cards( 'Ready to ship' ) )
        ->assertPresent( cards( 'Ready to ship' ) . ' ' . card( '1001' ) )
        ->assertSee( '1 automation ran for order 1001' );

    expect( columnOf( '1001' ) )->toBe( 'Ready to ship' )
        ->and( $this->orders['1001']->refresh()->meta['priority'] ?? null )->toBe( 'high' );

    $page->click( Locators::role( 'Actions for order 1001' ) )
        ->click( card( '1001' ) . ' [data-ec-kb-action="open"]' )
        ->assertSeeIn( '[data-ec-kb-detail-timeline]', 'Moved to Ready to ship' );

    JavaScript::assertNoErrors( $page );
} );

it( 'reorders cards within a column by dragging', function (): void {
    $page = visit( $this->boardUrl() )->assertScript( numbersIn( 'To pack' ), '1001,1002,1003' );

    $page->drag( card( '1003' ), card( '1001' ) )
        ->assertScript( numbersIn( 'To pack' ), '1003,1001,1002' )
        ->assertScript( settled() );

    expect( OrderBoardAssignment::query()->where( 'board_id', $this->board->id )->where( 'substatus_id', $this->columns['To pack']->substatus_id )->orderBy( 'position' )->pluck( 'order_id' )->all() )
        ->toBe( [ $this->orders['1003']->id, $this->orders['1001']->id, $this->orders['1002']->id ] );

    JavaScript::assertNoErrors( $page );
} );

it( 'moves a card to another column with the keyboard', function (): void {
    $page = visit( $this->boardUrl() )->assertPresent( card( '1002' ) );

    // Right twice: Packing, then Ready to ship.
    $page->keys( card( '1002' ), [ 'Space', 'ArrowRight', 'ArrowRight', 'Space' ] )
        ->assertPresent( cards( 'Ready to ship' ) . ' ' . card( '1002' ) )
        ->assertSee( '1 automation ran for order 1002' );

    expect( columnOf( '1002' ) )->toBe( 'Ready to ship' );
} );

it( 'moves a card with the "Move to…" dialog', function (): void {
    $page = openMoveDialog( visit( $this->boardUrl() )->assertPresent( card( '1003' ) ), '1003' );

    $page->select( Locators::field( 'ec-kb-move-column' ), (string) $this->columns['Ready to ship']->id )
        ->click( '[data-ec-kb-move-submit]' )
        ->assertPresent( cards( 'Ready to ship' ) . ' ' . card( '1003' ) )
        ->assertScript( focused( card( '1003' ) ) );

    expect( columnOf( '1003' ) )->toBe( 'Ready to ship' );
} );

it( 'asks before moving a card to an exit column', function (): void {
    $page = openMoveDialog( visit( $this->boardUrl() )->assertPresent( card( '1003' ) ), '1003' );

    $page->select( Locators::field( 'ec-kb-move-column' ), (string) $this->columns['Cancelled']->id )
        ->click( '[data-ec-kb-move-submit]' )
        ->assertVisible( '[data-ec-kb-confirm-move-button]' )
        ->assertSee( 'Move order 1003 to Cancelled?' )
        ->click( '[data-ec-kb-confirm-move-button]' )
        ->assertPresent( cards( 'Cancelled' ) . ' ' . card( '1003' ) );

    expect( $this->orders['1003']->refresh()->system_status )->toBe( 'cancelled' );
} );

it( 'refuses a drop into a column at its WIP limit and puts the card back', function (): void {
    $page = visit( $this->boardUrl() )->assertPresent( '[data-ec-kb-column="' . $this->columns['Packing']->id . '"] [data-ec-kb-wip-reached]' );

    $page->drag( card( '1001' ), cards( 'Packing' ) )
        ->assertScript( numbersIn( 'To pack' ), '1001,1002,1003' )
        ->assertScript( numbersIn( 'Packing' ), '1004' );

    expect( columnOf( '1001' ) )->toBe( 'To pack' );
} );

it( 'reverts a move the engine refuses and says why', function (): void {
    $page = openMoveDialog( visit( $this->boardUrl() )->assertPresent( card( '1001' ) ), '1001' );

    $page->select( Locators::field( 'ec-kb-move-column' ), (string) $this->columns['Packing']->id )
        ->click( '[data-ec-kb-move-submit]' )
        ->assertSee( 'WIP limit' )
        ->assertScript( numbersIn( 'To pack' ), '1001,1002,1003' );

    expect( columnOf( '1001' ) )->toBe( 'To pack' );
} );

it( 'filters by tag and placed date, and keeps the filters in the URL', function (): void {
    $page = visit( $this->boardUrl() )->assertPresent( card( '1003' ) );

    $page->click( '[data-ec-kb-filters="ec-kb-filters"] [id$="ec-kb-filters-tags-1"]' )
        ->click( '[data-ec-kb-filters="ec-kb-filters"] [id^="option-"][id$="ec-kb-filters-tags-rush"]' )
        ->click( '[data-ec-kb-filters="ec-kb-filters"] [id^="option-"][id$="ec-kb-filters-tags-wholesale"]' )
        ->assertScript( numbersIn( 'To pack' ), '1001,1002' )
        ->type( '[data-ec-kb-filters="ec-kb-filters"] [data-ec-kb-filter="placed-from"]', '2026-03-05' )
        ->assertScript( numbersIn( 'To pack' ), '1002' )
        ->assertScript( numbersIn( 'Packing' ), '' )
        ->assertScript( 'new URLSearchParams( window.location.search ).get( "from" )', '2026-03-05' );

    $page->navigate( $page->url() )->assertScript( numbersIn( 'To pack' ), '1002' );

    $page->click( '[data-ec-kb-clear-filters]' )->assertScript( numbersIn( 'To pack' ), '1001,1002,1003' );

    JavaScript::assertNoErrors( $page );
} );

it( 'opens the slide-out from ?card=', function (): void {
    visit( $this->boardUrl( [ 'card' => $this->orders['1004']->id ] ) )
        ->assertVisible( '[data-ec-kb-detail="' . $this->orders['1004']->id . '"]' )
        ->assertSee( 'Order 1004' )
        ->assertSeeIn( '[data-ec-kb-detail-column]', 'Packing' )
        ->assertSee( 'buyer1004@example.test' );
} );

it( 'reorders columns in edit mode', function (): void {
    $page = visit( $this->boardUrl() )
        ->click( '[data-ec-kb-edit-board]' )
        ->assertPresent( '[data-ec-kb-column-tools]' )
        ->click( '[data-ec-kb-column="' . $this->columns['Ready to ship']->id . '"] [data-ec-kb-move-column="left"]' )
        ->assertScript( 'Array.from( document.querySelectorAll( "[data-ec-kb-column] h2" ) ).map( ( heading ) => heading.textContent.trim() ).join( "," )', 'To pack,Ready to ship,Packing,Cancelled' )
        ->assertScript( focused( '[data-ec-kb-column="' . $this->columns['Ready to ship']->id . '"] [data-ec-kb-move-column="left"]' ) );

    expect( KanbanColumn::query()->where( 'board_id', $this->board->id )->orderBy( 'position' )->pluck( 'id' )->all() )
        ->toBe( [ $this->columns['To pack']->id, $this->columns['Ready to ship']->id, $this->columns['Packing']->id, $this->columns['Cancelled']->id ] );

    $page->click( '[data-ec-kb-stop-editing]' )->assertNotPresent( '[data-ec-kb-column-tools]' );

    JavaScript::assertNoErrors( $page );
} );

it( 'shows a move made in another browser (polling fallback)', function (): void {
    config()->set( 'artisanpack.ecommerce-kanban-livewire.realtime.poll_interval', 1 );

    $watcher = visit( $this->boardUrl() )
        ->assertPresent( card( '1003' ) )
        ->assertPresent( '[data-ec-kb-connection-state="polling"]' );

    $mover = openMoveDialog( visit( $this->boardUrl() )->assertPresent( card( '1003' ) ), '1003' );

    $mover->select( Locators::field( 'ec-kb-move-column' ), (string) $this->columns['Ready to ship']->id )
        ->click( '[data-ec-kb-move-submit]' )
        ->assertPresent( cards( 'Ready to ship' ) . ' ' . card( '1003' ) );

    $watcher->assertPresent( cards( 'Ready to ship' ) . ' ' . card( '1003' ) )
        ->assertScript( numbersIn( 'To pack' ), '1001,1002' );

    JavaScript::assertNoErrors( $watcher );
} );
