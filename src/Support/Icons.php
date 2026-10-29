<?php

/**
 * Icon names.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceKanbanLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceKanbanLivewire\Support;

use BladeUI\Icons\Factory;
use Throwable;

/**
 * Turns the icon names the engine stores (sub-status icons, column overrides,
 * widget payloads) into names `x-artisanpack-icon` can render.
 *
 * The engine's widgets use bare Heroicon names (`tag`, `truck`); the
 * component library expects a style prefix (`o-tag`). A name that doesn't
 * resolve to an SVG is dropped, because rendering it would throw and blank
 * the card.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceKanbanLivewire
 *
 * @since      1.0.0
 */
final class Icons
{
    /**
     * Resolved names, keyed by the raw name (`false` when missing).
     *
     * @since 1.0.0
     *
     * @var array<string, false|string>
     */
    private static array $resolved = [];

    /**
     * The `x-artisanpack-icon` name for `$name`, or null when there is none.
     *
     * @since 1.0.0
     *
     * @param  string|null  $name  Raw icon name.
     *
     * @return string|null
     */
    public static function name( ?string $name ): ?string
    {
        $name = trim( (string) $name );

        if ( '' === $name ) {
            return null;
        }

        if ( ! array_key_exists( $name, self::$resolved ) ) {
            $candidate               = str_contains( $name, '.' ) || 1 === preg_match( '/^[osmc]-/', $name ) ? $name : 'o-' . $name;
            self::$resolved[ $name ] = self::exists( $candidate ) ? $candidate : false;
        }

        return false === self::$resolved[ $name ] ? null : self::$resolved[ $name ];
    }

    /**
     * Forgets the resolved names (tests).
     *
     * @since 1.0.0
     *
     * @return void
     */
    public static function flush(): void
    {
        self::$resolved = [];
    }

    /**
     * Whether blade-icons can find the SVG behind a component icon name.
     *
     * @since 1.0.0
     *
     * @param  string  $name  Component icon name.
     *
     * @return bool
     */
    private static function exists( string $name ): bool
    {
        $svg = str_contains( $name, '.' ) ? str_replace( '.', '-', $name ) : 'heroicon-' . $name;

        try {
            app( Factory::class )->svg( $svg );

            return true;
        } catch ( Throwable ) {
            return false;
        }
    }
}
