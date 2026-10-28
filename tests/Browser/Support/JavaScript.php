<?php

declare( strict_types=1 );

namespace Tests\Browser\Support;

use PHPUnit\Framework\Assert;

/**
 * JavaScript error checks for the browser suite.
 *
 * Fails on any uncaught error the page has logged, except the ones listed
 * in {@see self::LIBRARY_ERRORS}: errors thrown by livewire-ui-components
 * markup this package cannot change. Remove an entry once the library
 * fixes it, and the suite enforces it here.
 */
final class JavaScript
{
    /**
     * Known library errors, as patterns matched against the message.
     *
     * @var array<int, string>
     */
    public const LIBRARY_ERRORS = [
        // ArtisanPack-UI/livewire-ui-components#135: `x-artisanpack-choices`
        // reads `selection.length` while a single select is empty (null),
        // and a Livewire morph that changes a choices or choices-offline
        // control's options drops its inner Alpine scope, so its bindings
        // throw (the board's customer and product filters; the tags filter
        // is keyed around it).
        "/^Uncaught TypeError: Cannot read properties of (null|undefined) \\(reading '(length|map)'\\)$/",
        '/^Uncaught ReferenceError: (isActive|noResults|isSelectionEmpty|isRequired|search|id) is not defined$/',
    ];

    /**
     * Asserts the page has logged no JavaScript errors beyond the known
     * library ones.
     *
     * @param  object  $page  The page (a Pest browser webpage).
     *
     * @return object
     */
    public static function assertNoErrors( object $page ): object
    {
        $errors = array_values( array_filter(
            array_map( static fn ( mixed $error ): string => (string) ( $error['message'] ?? $error ), (array) $page->script( '() => window.__pestBrowser?.jsErrors ?? []' ) ),
            static fn ( string $message ): bool => [] === array_filter( self::LIBRARY_ERRORS, static fn ( string $pattern ): bool => 1 === preg_match( $pattern, $message ) ),
        ) );

        Assert::assertSame( [], $errors, "JavaScript errors:\n- " . implode( "\n- ", $errors ) );

        return $page;
    }
}
