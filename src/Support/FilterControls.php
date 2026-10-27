<?php

/**
 * Satellite filter controls.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceKanbanLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceKanbanLivewire\Support;

use ArtisanPackUI\Ecommerce\Kanban\KanbanCardFilters;
use ArtisanPackUI\Ecommerce\Models\KanbanBoard;

/**
 * Filter controls satellites add to the filter bar (spec §7.5).
 *
 * `ap.ecommerceKanbanLivewire.filters` receives the list (empty by default)
 * and the board, and returns controls of the form:
 *
 * ```php
 * [
 *     'key'     => 'gift',                // the value's key in KanbanCardFilters::$extra
 *     'label'   => __( 'Gift orders' ),
 *     'type'    => 'toggle',              // toggle, select, or text
 *     'options' => [ [ 'id' => 'yes', 'name' => __( 'Yes' ) ] ], // select only
 * ]
 * ```
 *
 * Pair each with an `ap.ecommerce.kanban.cardQuery` filter that reads
 * `$filters->extra[ $key ]` and narrows the cards.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceKanbanLivewire
 *
 * @since      1.0.0
 */
final class FilterControls
{
    /**
     * @since 1.0.0
     *
     * @var string
     */
    public const FILTER = 'ap.ecommerceKanbanLivewire.filters';

    /**
     * @since 1.0.0
     *
     * @var array<int, string>
     */
    public const TYPES = [ 'toggle', 'select', 'text' ];

    /**
     * The valid controls for `$board`.
     *
     * @since 1.0.0
     *
     * @param  KanbanBoard  $board  The board.
     *
     * @return array<int, array{key: string, label: string, type: string, options: array<int, array{id: string, name: string}>}>
     */
    public static function for( KanbanBoard $board ): array
    {
        $controls = applyFilters( self::FILTER, [], $board );
        $result   = [];

        foreach ( is_array( $controls ) ? $controls : [] as $control ) {
            $control = is_array( $control ) ? self::normalize( $control ) : null;

            if ( null !== $control && ! isset( $result[ $control['key'] ] ) ) {
                $result[ $control['key'] ] = $control;
            }
        }

        return array_values( $result );
    }

    /**
     * @since 1.0.0
     *
     * @param  array<string, mixed>  $control  Raw control.
     *
     * @return array{key: string, label: string, type: string, options: array<int, array{id: string, name: string}>}|null
     */
    private static function normalize( array $control ): ?array
    {
        $key   = is_string( $control['key'] ?? null ) ? trim( $control['key'] ) : '';
        $label = is_string( $control['label'] ?? null ) ? trim( $control['label'] ) : '';
        $type  = $control['type'] ?? 'text';

        // Core keys belong to the built-in controls.
        if ( 1 !== preg_match( '/^[a-z][a-z0-9_]{0,39}$/D', $key ) || in_array( $key, KanbanCardFilters::KEYS, true ) || '' === $label || ! in_array( $type, self::TYPES, true ) ) {
            return null;
        }

        $options = [];

        foreach ( is_array( $control['options'] ?? null ) ? $control['options'] : [] as $option ) {
            if ( is_array( $option ) && is_scalar( $option['id'] ?? null ) && is_scalar( $option['name'] ?? null ) ) {
                $options[] = [ 'id' => (string) $option['id'], 'name' => (string) $option['name'] ];
            }
        }

        if ( 'select' === $type && [] === $options ) {
            return null;
        }

        return [ 'key' => $key, 'label' => $label, 'type' => $type, 'options' => $options ];
    }
}
