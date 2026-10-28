<?php

declare( strict_types=1 );

use Tests\Browser\Support\Locators;

/*
 * Keyboard-only use of the board (#22): every action is reachable without
 * a pointer, drag moves are announced by name, focus goes back where it
 * came from, and a focused control never hides under the sticky header.
 */

/**
 * JavaScript: whether the focused element matches `$selector`.
 */
function keyboardFocused( string $selector ): string
{
    return 'document.activeElement?.matches( ' . json_encode( $selector ) . ' ) === true';
}

/**
 * JavaScript: the text of livewire-drag-and-drop's live region.
 */
function dragAnnouncement(): string
{
    return '( document.querySelector( "[data-drag-live-region]" )?.textContent.trim() ?? "" )';
}

it( 'starts with a skip link to the board', function (): void {
    visit( $this->boardUrl() )
        ->assertPresent( '[data-ec-kb-card]' )
        ->keys( 'html > body', 'Tab' )
        ->assertScript( keyboardFocused( 'a[href="#ecommerce-kanban-content"]' ) )
        ->assertSeeIn( 'a[href="#ecommerce-kanban-content"]', 'Skip to content' );
} );

it( 'names each card and column for screen readers', function (): void {
    visit( $this->boardUrl() )
        ->assertPresent( '[data-ec-kb-card]' )
        ->assertAttribute( '[data-ec-kb-card="' . $this->orders['1001']->id . '"]', 'role', 'group' )
        ->assertAttributeContains( '[data-ec-kb-card="' . $this->orders['1001']->id . '"]', 'aria-label', 'Order 1001' )
        ->assertAttribute( '[data-ec-kb-column="' . $this->columns['Packing']->id . '"]', 'role', 'region' )
        ->assertScript( 'document.getElementById( document.querySelector( \'[data-ec-kb-column="' . $this->columns['Packing']->id . '"]\' ).getAttribute( "aria-labelledby" ) ).textContent.trim()', 'Packing' )
        ->assertSeeIn( '[data-ec-kb-column="' . $this->columns['Packing']->id . '"] [data-ec-kb-wip]', '1 of 1 cards, WIP limit reached' );
} );

it( 'announces a keyboard move by order, column, and position', function (): void {
    $card = '[data-ec-kb-card="' . $this->orders['1002']->id . '"]';

    $page = visit( $this->boardUrl() )->assertPresent( $card );

    $page->keys( $card, [ 'Space' ] )
        ->assertScript( dragAnnouncement() . '.includes( "Order 1002" )' )
        ->keys( $card, [ 'ArrowRight' ] )
        ->assertScript( dragAnnouncement() . '.includes( "Packing" )' )
        ->keys( $card, [ 'ArrowRight' ] )
        ->assertScript( dragAnnouncement() . '.includes( "Ready to ship" )' )
        ->keys( $card, [ 'Escape' ] )
        ->assertPresent( '[data-ec-kb-cards="' . $this->columns['To pack']->id . '"] ' . $card );
} );

it( 'opens a card\'s actions and the "Move to…" dialog from the keyboard', function (): void {
    $trigger = Locators::role( 'Actions for order 1001' );

    visit( $this->boardUrl() )
        ->assertPresent( '[data-ec-kb-card]' )
        ->keys( $trigger, [ 'Enter' ] )
        ->assertVisible( '[data-ec-kb-card="' . $this->orders['1001']->id . '"] [data-ec-kb-action="move"]' )
        ->keys( '[data-ec-kb-card="' . $this->orders['1001']->id . '"] [data-ec-kb-action="move"]', [ 'Enter' ] )
        ->assertVisible( '[data-ec-kb-move-submit]' );
} );

it( 'returns focus to the card when the slide-out closes', function (): void {
    $card = '[data-ec-kb-card="' . $this->orders['1004']->id . '"]';

    visit( $this->boardUrl( [ 'card' => $this->orders['1004']->id ] ) )
        ->assertVisible( '[data-ec-kb-detail="' . $this->orders['1004']->id . '"]' )
        ->keys( 'html > body', 'Escape' )
        ->assertNotPresent( '[data-ec-kb-detail]' )
        ->assertScript( keyboardFocused( $card ) );
} );

it( 'moves columns with the keyboard in edit mode and keeps focus on the control', function (): void {
    $right = '[data-ec-kb-column="' . $this->columns['To pack']->id . '"] [data-ec-kb-move-column="right"]';

    visit( $this->boardUrl() )
        ->click( '[data-ec-kb-edit-board]' )
        ->assertPresent( $right )
        ->keys( $right, [ 'Enter' ] )
        ->assertScript( 'Array.from( document.querySelectorAll( "[data-ec-kb-column] h2" ) ).map( ( heading ) => heading.textContent.trim() ).join( "," )', 'Packing,To pack,Ready to ship,Cancelled' )
        ->assertScript( keyboardFocused( '[data-ec-kb-column="' . $this->columns['To pack']->id . '"] [data-ec-kb-move-column]' ) );
} );

it( 'keeps focused controls clear of the sticky header (SC 2.4.11)', function (): void {
    visit( $this->boardUrl() )
        ->resize( 1280, 400 )
        ->assertPresent( '[data-ec-kb-card]' )
        ->assertScript( 'parseFloat( getComputedStyle( document.documentElement ).scrollPaddingTop ) >= document.querySelector( "header" ).getBoundingClientRect().height' )
        ->assertScript( <<<'JS'
            ( () => {
                window.scrollTo( 0, document.documentElement.scrollHeight );
                document.querySelector( '[data-ec-kb-edit-board]' ).focus();

                return window.scrollY > 0 || document.querySelector( '[data-ec-kb-edit-board]' ).getBoundingClientRect().top >= document.querySelector( 'header' ).getBoundingClientRect().bottom;
            } )()
            JS )
        ->assertScript( 'document.querySelector( "[data-ec-kb-edit-board]" ).getBoundingClientRect().top >= document.querySelector( "header" ).getBoundingClientRect().bottom' );
} );
