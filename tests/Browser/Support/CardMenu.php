<?php

declare( strict_types=1 );

namespace Tests\Browser\Support;

/**
 * Opens a card's actions menu and chooses an item, for the browser suite.
 *
 * The browser plugin's clicks pass Playwright no timeout, so a click on an
 * element that never becomes stable waits forever. Clicking a menu item
 * the moment its menu opens raced the menu's open animation and, right
 * after a move, the card's re-render (the column morph and the toasts),
 * and hung the suite now and then. So this waits until no card is waiting
 * on a move, opens the menu, and waits for the item to show and for every
 * animation to finish before it clicks.
 */
final class CardMenu
{
    /**
     * JavaScript: whether no card on the board is waiting on a move.
     *
     * @var string
     */
    public const SETTLED = '! document.querySelector( \'[data-ec-kb-card][data-ec-kb-state="moving"]\' )';

    /**
     * Opens the actions menu of order `$number` and waits until it is open
     * and still.
     *
     * @param  object  $page    The page (a Pest browser webpage).
     * @param  string  $number  The order number.
     *
     * @return object
     */
    public static function open( object $page, string $number ): object
    {
        $page->assertScript( self::SETTLED )
            ->assertScript( Accessibility::ANIMATIONS_SETTLED )
            ->click( Locators::role( 'Actions for order ' . $number ) )
            ->assertVisible( Locators::menuItem( 'open' ) )
            ->assertScript( Accessibility::ANIMATIONS_SETTLED );

        return $page;
    }

    /**
     * Opens the actions menu of order `$number` and chooses action `$key`.
     *
     * @param  object  $page    The page (a Pest browser webpage).
     * @param  string  $number  The order number.
     * @param  string  $key     The action key (`open`, `move`, `remove`, …).
     *
     * @return object
     */
    public static function choose( object $page, string $number, string $key ): object
    {
        return self::open( $page, $number )->click( Locators::menuItem( $key ) );
    }
}
