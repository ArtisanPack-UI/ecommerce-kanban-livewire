<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Models\Customer;
use ArtisanPackUI\Ecommerce\Models\OrderItem;
use ArtisanPackUI\Ecommerce\Models\OrderSubstatus;
use ArtisanPackUI\Ecommerce\Models\Product;
use Tests\Browser\Support\Accessibility;
use Tests\Browser\Support\CardMenu;
use Tests\Browser\Support\JavaScript;
use Tests\Browser\Support\Locators;

/*
 * Browser coverage the 2.0 release review asked for: right-to-left
 * keyboard moves (F1), axe on the dialogs and states the suite never
 * opened (the exit-column confirmation, "Remove column", "Add column", the
 * widget picker with rows, a moving card, a held card), the connection
 * status region (F6), and the remote filter comboboxes.
 */

/**
 * JavaScript: the text of livewire-drag-and-drop's live region.
 */
function reviewAnnouncement(): string
{
    return '( document.querySelector( "[data-drag-live-region]" )?.textContent.trim() ?? "" )';
}

/**
 * JavaScript: the column headings, in DOM order, comma-separated.
 */
function reviewHeadings(): string
{
    return 'Array.from( document.querySelectorAll( "[data-ec-kb-column] h2" ) ).map( ( heading ) => heading.textContent.trim() ).join( "," )';
}

/**
 * Switches the app to Arabic, a right-to-left locale.
 */
function reviewArabic(): void
{
    config()->set( 'app.locale', 'ar' );
    app()->setLocale( 'ar' );
}

it( 'moves a card toward the next column with ArrowLeft in right to left (F1)', function (): void {
    reviewArabic();

    $card = '[data-ec-kb-card="' . $this->orders['1002']->id . '"]';

    $page = visit( $this->boardUrl() )->assertPresent( $card )
        ->assertScript( 'getComputedStyle( document.querySelector( ".ec-kb-board" ) ).direction', 'rtl' );

    // The next column (Packing) sits to the left in RTL.
    $page->keys( $card, [ 'Space', 'ArrowLeft' ] )
        ->assertScript( reviewAnnouncement() . '.includes( "Packing" )' )
        ->keys( $card, [ 'ArrowLeft' ] )
        ->assertScript( reviewAnnouncement() . '.includes( "Ready to ship" )' )
        // ArrowRight goes back toward the start.
        ->keys( $card, [ 'ArrowRight' ] )
        ->assertScript( reviewAnnouncement() . '.includes( "Packing" )' )
        ->keys( $card, [ 'ArrowLeft', 'Space' ] )
        ->assertPresent( '[data-ec-kb-cards="' . $this->columns['Ready to ship']->id . '"] ' . $card );

    JavaScript::assertNoErrors( $page );
} );

it( 'moves a column toward the end with ArrowLeft in right-to-left edit mode (F1)', function (): void {
    reviewArabic();

    $column = '[data-ec-kb-column="' . $this->columns['To pack']->id . '"]';

    visit( $this->boardUrl() )
        ->click( '[data-ec-kb-edit-board]' )
        ->assertPresent( '[data-ec-kb-column-tools]' )
        ->assertScript( 'document.querySelector( ' . json_encode( $column ) . ' ).getAttribute( "role" )', 'region' )
        ->keys( $column, [ 'Space', 'ArrowLeft', 'Space' ] )
        ->assertScript( reviewHeadings(), 'Packing,To pack,Ready to ship,Cancelled' );
} );

it( 'still moves a card toward the next column with ArrowRight left to right (F1)', function (): void {
    $card = '[data-ec-kb-card="' . $this->orders['1002']->id . '"]';

    visit( $this->boardUrl() )->assertPresent( $card )
        ->keys( $card, [ 'Space', 'ArrowRight' ] )
        ->assertScript( reviewAnnouncement() . '.includes( "Packing" )' )
        ->keys( $card, [ 'Escape' ] );
} );

it( 'marks a card held from the keyboard and keeps the board accessible mid-drag, in dark mode too (F5)', function (): void {
    $card = '[data-ec-kb-card="' . $this->orders['1002']->id . '"]';

    $page = visit( $this->boardUrl() )->inDarkMode()->assertPresent( $card );

    $idle = $page->script( '() => getComputedStyle( document.querySelector( ' . json_encode( $card ) . ' ) ).borderColor' );

    $page->keys( $card, [ 'Space' ] )
        ->assertPresent( $card . '[data-dragging]' )
        ->assertScript( 'getComputedStyle( document.querySelector( ' . json_encode( $card ) . ' ) ).borderColor !== ' . json_encode( $idle ) )
        ->assertScript( 'getComputedStyle( document.querySelector( "[data-ec-kb-cards]" ) ).getPropertyValue( "--drag-indicator-color" ).trim() !== ""' );

    Accessibility::assertAccessible( $page );

    $page->keys( $card, [ 'Escape' ] );
} );

it( 'keeps a moving card readable: full opacity and no contrast failures (F8)', function ( string $mode ): void {
    $card = '[data-ec-kb-card="' . $this->orders['1001']->id . '"]';

    $page = visit( $this->boardUrl() );

    if ( 'dark' === $mode ) {
        $page->inDarkMode();
    }

    $page->assertPresent( $card )
        ->script( '() => window.dispatchEvent( new CustomEvent( "ec-kb-card-state", { detail: { orderId: ' . (int) $this->orders['1001']->id . ', state: "moving" } } ) )' );

    $page->assertPresent( $card . '[data-ec-kb-state="moving"]' )
        ->assertSeeIn( $card, 'Moving…' )
        ->assertScript( 'getComputedStyle( document.querySelector( ' . json_encode( $card ) . ' ) ).opacity', '1' );

    Accessibility::assertAccessible( $page );
} )->with( [ 'light', 'dark' ] );

it( 'has no accessibility violations in the exit-column confirmation', function (): void {
    $page = CardMenu::choose( visit( $this->boardUrl() )->assertPresent( '[data-ec-kb-card]' ), '1003', 'move' )
        ->assertVisible( '[data-ec-kb-move-submit]' )
        ->select( Locators::field( 'ec-kb-move-column' ), (string) $this->columns['Cancelled']->id )
        ->click( '[data-ec-kb-move-submit]' )
        ->assertVisible( '[data-ec-kb-confirm-move-button]' );

    Accessibility::assertAccessible( $page, Accessibility::OPEN_OVERLAY );
} );

it( 'has no accessibility violations in "Remove column" with the exit warning', function (): void {
    $page = visit( $this->boardUrl() )
        ->click( '[data-ec-kb-edit-board]' )
        ->click( '[data-ec-kb-column="' . $this->columns['Packing']->id . '"] [data-ec-kb-delete-column]' )
        ->assertVisible( '[data-ec-kb-delete-column-move-to]' )
        ->select( Locators::field( 'ec-kb-delete-column-move-to' ), (string) $this->columns['Cancelled']->id )
        ->assertVisible( '[data-ec-kb-delete-column-exit]' );

    Accessibility::assertAccessible( $page, Accessibility::OPEN_OVERLAY );
} );

it( 'has no accessibility violations in "Add column"', function (): void {
    OrderSubstatus::factory()->create( [ 'label' => 'On hold', 'system_status' => 'processing', 'position' => 9 ] );

    $page = visit( $this->boardUrl() )
        ->click( '[data-ec-kb-edit-board]' )
        ->click( '[data-ec-kb-add-column]' )
        ->assertVisible( '[data-ec-kb-add-column-submit]' );

    Accessibility::assertAccessible( $page, Accessibility::OPEN_OVERLAY );
} );

it( 'has no accessibility violations in the widget picker with rows, which stay list items (F3)', function (): void {
    $this->board->update( [ 'settings' => [ 'card_widgets' => [ 'total', [ 'key' => 'tags', 'config' => [ 'max_tags' => 2 ] ] ] ] ] );

    $page = visit( $this->boardUrl() )
        ->click( '[data-ec-kb-edit-board]' )
        ->click( '[data-ec-kb-board-widgets]' )
        ->assertVisible( '[data-ec-kb-widget-row="total"]' )
        ->assertVisible( '[data-ec-kb-widget-row="tags"] [data-ec-kb-widget-config]' );

    $page->assertScript( 'Array.from( document.querySelectorAll( "[data-ec-kb-widget-row]" ) ).every( ( row ) => "listitem" === row.getAttribute( "role" ) )' )
        ->assertScript( 'JSON.parse( document.querySelector( "[data-ec-kb-widget-list]" ).dataset.dragMessages ).item', 'Widget' )
        ->assertNotPresent( '[data-ec-kb-widget-preview] [data-ec-kb-card]' );

    Accessibility::assertAccessible( $page, Accessibility::OPEN_OVERLAY );
} );

it( 'says "Offline" in the connection status region when the network goes (F6)', function (): void {
    $page = visit( $this->boardUrl() )->assertPresent( '[data-ec-kb-card]' )
        ->assertScript( 'document.querySelector( "[data-ec-kb-connection-status]" ).textContent.trim()', 'Updates every 30 seconds' );

    $page->script( '() => { Object.defineProperty( navigator, "onLine", { get: () => false, configurable: true } ); window.dispatchEvent( new Event( "offline" ) ); }' );

    $page->assertScript( 'document.querySelector( "[data-ec-kb-connection-status]" ).textContent.trim()', 'Offline' )
        ->assertVisible( '[data-ec-kb-connection] .apui-badge[data-color="error"]' );
} );

it( 'keeps searching and picking in the customer filter after the first search', function (): void {
    $ada   = Customer::factory()->create( [ 'first_name' => 'Ada', 'last_name' => 'Lovelace', 'email' => 'ada@example.test' ] );
    $grace = Customer::factory()->create( [ 'first_name' => 'Grace', 'last_name' => 'Hopper', 'email' => 'grace@example.test' ] );
    $this->orders['1001']->update( [ 'customer_id' => $ada->id ] );
    $this->orders['1002']->update( [ 'customer_id' => $grace->id ] );

    $input   = '.ec-kb-from-md [data-ec-kb-filter="customer"] [data-part="control"]';
    $options = '[id^="ec-kb-filters-customer"][data-part="content"] [data-part="item"]:not([hidden])';

    $page = visit( $this->boardUrl() )->assertPresent( '[data-ec-kb-card]' );

    $page->type( $input, 'Ada' )
        ->assertScript( 'Array.from( document.querySelectorAll( ' . json_encode( $options ) . ' ) ).map( ( item ) => item.textContent.trim() ).join( "|" )', 'Ada Lovelace (ada@example.test)' )
        ->clear( $input )
        ->type( $input, 'Gra' )
        ->assertScript( 'Array.from( document.querySelectorAll( ' . json_encode( $options ) . ' ) ).map( ( item ) => item.textContent.trim() ).join( "|" )', 'Grace Hopper (grace@example.test)' )
        ->click( $options )
        ->assertQueryStringHas( 'customer', (string) $grace->id )
        ->assertPresent( '[data-ec-kb-card="' . $this->orders['1002']->id . '"]' )
        ->assertNotPresent( '[data-ec-kb-card="' . $this->orders['1001']->id . '"]' );

    JavaScript::assertNoErrors( $page );
} );

it( 'keeps searching and picking in the products filter after the first search', function (): void {
    $shirt = Product::factory()->create( [ 'name' => 'Tour shirt', 'sku' => 'SHIRT-1' ] );
    $mug   = Product::factory()->create( [ 'name' => 'Coffee mug', 'sku' => 'MUG-1' ] );
    OrderItem::factory()->create( [ 'order_id' => $this->orders['1001']->id, 'product_id' => $shirt->id ] );
    OrderItem::factory()->create( [ 'order_id' => $this->orders['1003']->id, 'product_id' => $mug->id ] );

    $input   = '.ec-kb-from-md [data-ec-kb-filter="products"] [data-part="control"]';
    $options = '[id^="ec-kb-filters-products"][data-part="content"] [data-part="item"]:not([hidden])';

    $page = visit( $this->boardUrl() )->assertPresent( '[data-ec-kb-card]' );

    $page->type( $input, 'shirt' )
        ->assertScript( 'Array.from( document.querySelectorAll( ' . json_encode( $options ) . ' ) ).map( ( item ) => item.textContent.trim() ).join( "|" ).includes( "Tour shirt" )' )
        ->clear( $input )
        ->type( $input, 'mug' )
        ->assertScript( 'Array.from( document.querySelectorAll( ' . json_encode( $options ) . ' ) ).map( ( item ) => item.textContent.trim() ).join( "|" ).includes( "Coffee mug" )' )
        ->click( $options )
        ->assertPresent( '[data-ec-kb-card="' . $this->orders['1003']->id . '"]' )
        ->assertNotPresent( '[data-ec-kb-card="' . $this->orders['1001']->id . '"]' );

    JavaScript::assertNoErrors( $page );
} );

it( 'puts an automation toggle back when its change is refused (F12)', function (): void {
    config()->set( 'artisanpack.ecommerce.rate_limits.admin.mutate.per_user', 1 );

    $toggle = '[data-ec-kb-automation] [data-ec-kb-automation-toggle]';
    $state  = '[data-ec-kb-automation] [data-ec-kb-automation-state]';

    $page = visit( $this->boardUrl() )
        ->click( '[data-ec-kb-automations]' )
        ->assertVisible( $toggle )
        ->assertScript( 'document.querySelector( ' . json_encode( $toggle ) . ' ).checked', true );

    // The one change allowed: switch it off.
    $page->click( $toggle )
        ->assertSeeIn( $state, 'Off' )
        ->assertScript( 'document.querySelector( ' . json_encode( $toggle ) . ' ).checked', false );

    // Rate limited: the switch goes back to match the badge.
    $page->click( $toggle )
        ->wait( 1 )
        ->assertSeeIn( $state, 'Off' )
        ->assertScript( 'document.querySelector( ' . json_encode( $toggle ) . ' ).checked', false );
} );

it( 'opens a card in a new tab on a Shift-click of "Open", and in the slide-out on a plain click (B7)', function (): void {
    $page = visit( $this->boardUrl() )->assertPresent( '[data-ec-kb-card]' );

    $page->script( '() => { window.__opened = []; window.open = ( url ) => { window.__opened.push( url ); return null; }; }' );

    CardMenu::open( $page, '1001' )
        ->withKeyDown( 'Shift', fn ( $page ) => $page->click( Locators::menuItem( 'open' ) ) );

    $page->assertScript( 'window.__opened.length', 1 )
        ->assertScript( 'window.__opened[ 0 ].includes( "card=' . $this->orders['1001']->id . '" )' )
        ->assertNotPresent( '[data-ec-kb-detail]' );

    CardMenu::choose( $page, '1001', 'open' )
        ->assertPresent( '[data-ec-kb-detail="' . $this->orders['1001']->id . '"]' )
        ->assertScript( 'window.__opened.length', 1 );
} );

it( 'keeps every target at 24px or more at compact density (F17)', function (): void {
    $page = visit( $this->boardUrl() )->assertPresent( '[data-ec-kb-card]' );

    $page->script( '() => { document.documentElement.dataset.density = "compact"; }' );

    $page->assertScript( 'document.querySelector( ".ec-kb-card-actions-trigger" ).getBoundingClientRect().height >= 24' )
        ->assertScript( 'document.querySelector( ".ec-kb-card-actions-trigger" ).getBoundingClientRect().width >= 24' );

    Accessibility::assertAccessible( $page );
} );
