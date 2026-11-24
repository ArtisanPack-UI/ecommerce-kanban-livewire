<?php

/**
 * The board's own stylesheet.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceKanbanLivewire
 *
 * @since      2.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceKanbanLivewire\Support;

/**
 * Reads `resources/css/kanban.css`, the token-only styles the design
 * system has no component for (the card, the column, the filter bar),
 * so the layouts can inline it without a build step.
 *
 * The file is the package's own and never holds user input; a `</style>`
 * sequence is still escaped so the contents can't close the element early.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceKanbanLivewire
 *
 * @since      2.0.0
 */
final class KanbanStyles
{

    /**
     * The base language codes written right to left.
     *
     * @since 2.0.0
     *
     * @var array<int, string>
     */
    public const RTL_LANGUAGES = [ 'ar', 'ckb', 'dv', 'fa', 'he', 'ps', 'sd', 'ug', 'ur', 'yi' ];

    /**
     * The stylesheet, once read.
     *
     * @since 2.0.0
     *
     * @var string|null
     */
    private static ?string $css = null;

    /**
     * The text direction for a locale: `rtl` for a right-to-left language
     * (`ar`, `he`, `fa`, …), `ltr` otherwise.
     *
     * The standalone layout sets it on `<html>`, and the board sets it on
     * its own root for a right-to-left locale, so the board's layout, its
     * mirrored icons, its visual "left / right" labels, and its keyboard
     * drag direction stay right even in a host layout that doesn't set
     * `dir`.
     *
     * @since 2.0.0
     *
     * @param  string|null  $locale  The locale; the app's current one when null.
     *
     * @return string `rtl` or `ltr`.
     */
    public static function textDirection( ?string $locale = null ): string
    {
        $language = strtolower( (string) strtok( $locale ?? app()->getLocale(), '_-' ) );

        return in_array( $language, self::RTL_LANGUAGES, true ) ? 'rtl' : 'ltr';
    }

    /**
     * The stylesheet's path.
     *
     * @since 2.0.0
     *
     * @return string
     */
    public static function path(): string
    {
        return dirname( __DIR__, 2 ) . '/resources/css/kanban.css';
    }

    /**
     * The stylesheet's contents, safe to print inside a `<style>` element.
     *
     * @since 2.0.0
     *
     * @return string
     */
    public static function css(): string
    {
        if ( null === self::$css ) {
            $contents  = is_file( self::path() ) ? (string) file_get_contents( self::path() ) : '';
            self::$css = str_ireplace( '</style', '<\/style', $contents );
        }

        return self::$css;
    }
}
