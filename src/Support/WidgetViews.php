<?php

/**
 * Card widget views.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceKanbanLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceKanbanLivewire\Support;

use ArtisanPackUI\EcommerceKanbanLivewire\EcommerceKanbanLivewireServiceProvider;
use Illuminate\Support\Facades\View;
use Illuminate\Support\HtmlString;
use Throwable;

/**
 * Renders the engine's framework-agnostic widget payloads
 * (`key, label, value, tone, icon, tooltip, href, refresh_channel`) as Blade.
 *
 * A satellite supplies its own view for a widget key through the
 * `ap.ecommerceKanbanLivewire.widgetView` filter:
 *
 * ```php
 * addFilter( 'ap.ecommerceKanbanLivewire.widgetView', function ( ?string $view, string $key, array $payload ): ?string {
 *     return 'print-provider' === $key ? 'print-provider::kanban.status-widget' : $view;
 * } );
 * ```
 *
 * The view receives `$widget` (the payload). Keys without a view, and views
 * that don't exist, use the generic view. A view that throws while rendering
 * is reported and renders nothing, so one broken widget never breaks a card.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceKanbanLivewire
 *
 * @since      1.0.0
 */
final class WidgetViews
{
    /**
     * The view-override filter.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public const FILTER = 'ap.ecommerceKanbanLivewire.widgetView';

    /**
     * The generic payload view.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public const GENERIC = EcommerceKanbanLivewireServiceProvider::VIEW_NAMESPACE . '::components.widgets.generic';

    /**
     * The design-system Badge color for each tone, and the icon that repeats
     * the tone without color.
     *
     * @since 1.0.0
     *
     * @var array<string, array{color: string, icon: string|null}>
     */
    public const TONES = [
        'neutral' => [ 'color' => 'neutral', 'icon' => null ],
        'success' => [ 'color' => 'success', 'icon' => 'o-check-circle' ],
        'warning' => [ 'color' => 'warning', 'icon' => 'o-exclamation-triangle' ],
        'danger'  => [ 'color' => 'error', 'icon' => 'o-x-circle' ],
        'info'    => [ 'color' => 'info', 'icon' => 'o-information-circle' ],
    ];

    /**
     * Widget keys whose values are tags: a neutral tag reads in the info
     * color, with no tone icon or tone text.
     *
     * @since 2.0.0
     *
     * @var array<int, string>
     */
    public const TAG_KEYS = [ 'tags' ];

    /**
     * The view for a payload.
     *
     * @since 1.0.0
     *
     * @param  array<string, string|null>  $payload  Widget payload.
     *
     * @return string
     */
    public static function view( array $payload ): string
    {
        $key  = (string) ( $payload['key'] ?? '' );
        $view = applyFilters( self::FILTER, null, $key, $payload );

        return is_string( $view ) && '' !== $view && View::exists( $view ) ? $view : self::GENERIC;
    }

    /**
     * Renders a payload, or nothing when its view throws.
     *
     * @since 1.0.0
     *
     * @param  array<string, string|null>  $payload  Widget payload.
     *
     * @return HtmlString
     */
    public static function render( array $payload ): HtmlString
    {
        try {
            return new HtmlString( view( self::view( $payload ), [ 'widget' => $payload ] )->render() );
        } catch ( Throwable $exception ) {
            report( $exception );

            return new HtmlString( '' );
        }
    }

    /**
     * The Badge color and tone icon for a payload's tone, with the text
     * that names the tone for screen readers. A neutral tag (see
     * `TAG_KEYS`) uses the info color.
     *
     * @since 1.0.0
     * @since 2.0.0 Added `$key`.
     *
     * @param  string|null  $tone  Payload tone.
     * @param  string|null  $key   Widget key.
     *
     * @return array{color: string, icon: string|null, text: string|null}
     */
    public static function tone( ?string $tone, ?string $key = null ): array
    {
        $tone = isset( self::TONES[ (string) $tone ] ) ? (string) $tone : 'neutral';

        if ( 'neutral' === $tone && in_array( (string) $key, self::TAG_KEYS, true ) ) {
            return [ 'color' => 'info', 'icon' => null, 'text' => null ];
        }

        return self::TONES[ $tone ] + [ 'text' => match ( $tone ) {
            'success' => __( 'Good' ),
            'warning' => __( 'Needs attention' ),
            'danger'  => __( 'Problem' ),
            'info'    => __( 'Note' ),
            default   => null,
        } ];
    }
}
