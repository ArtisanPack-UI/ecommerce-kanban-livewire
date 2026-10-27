<?php

/**
 * Link URL guard.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceKanbanLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceKanbanLivewire\Support;

/**
 * Keeps links that come from widget payloads and satellite card actions to
 * `http(s)`, `mailto`, and relative URLs, so a payload can't inject a
 * `javascript:` or `data:` link into the board.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceKanbanLivewire
 *
 * @since      1.0.0
 */
final class SafeUrl
{
    /**
     * Schemes a link may use.
     *
     * @since 1.0.0
     *
     * @var array<int, string>
     */
    public const SCHEMES = [ 'http', 'https', 'mailto' ];

    /**
     * `$url` when it is safe to link to, else null.
     *
     * @since 1.0.0
     *
     * @param  mixed  $url  Candidate URL.
     *
     * @return string|null
     */
    public static function of( mixed $url ): ?string
    {
        if ( ! is_string( $url ) ) {
            return null;
        }

        $url = trim( $url );

        if ( '' === $url ) {
            return null;
        }

        // Browsers ignore control characters and whitespace inside a scheme.
        $normalized = (string) preg_replace( '/[\x00-\x20\x7F]+/', '', $url );

        if ( 1 !== preg_match( '/^([a-z][a-z0-9+.\-]*):/i', $normalized, $matches ) ) {
            // Relative (`/path`, `?q`, `#x`); `//host` is protocol-relative http(s).
            return $url;
        }

        return in_array( strtolower( $matches[1] ), self::SCHEMES, true ) ? $url : null;
    }
}
