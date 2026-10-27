<?php

/**
 * Order status labels.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceKanbanLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceKanbanLivewire\Support;

use Illuminate\Support\Str;

/**
 * Translated labels for the engine's order, payment, and fulfillment
 * statuses, for the filter bar and the card slide-out.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceKanbanLivewire
 *
 * @since      1.0.0
 */
final class Statuses
{
    /**
     * System statuses that move the whole order when a card enters a column
     * holding them (docs/kanban.md "Status roll-up"), so a move there is
     * confirmed first.
     *
     * @since 1.0.0
     *
     * @var array<int, string>
     */
    public const EXIT = [ 'cancelled', 'refunded', 'failed' ];

    /**
     * Known values for `$type` (`system`, `payment`, or `fulfillment`) with
     * their labels.
     *
     * @since 1.0.0
     *
     * @param  string  $type  Status type.
     *
     * @return array<string, string>
     */
    public static function labels( string $type ): array
    {
        return match ( $type ) {
            'system' => [
                'pending'    => __( 'Pending' ),
                'processing' => __( 'Processing' ),
                'complete'   => __( 'Complete' ),
                'cancelled'  => __( 'Cancelled' ),
                'refunded'   => __( 'Refunded' ),
                'failed'     => __( 'Failed' ),
            ],
            'payment' => [
                'pending'            => __( 'Pending' ),
                'paid'               => __( 'Paid' ),
                'partially_refunded' => __( 'Partially refunded' ),
                'refunded'           => __( 'Refunded' ),
                'voided'             => __( 'Voided' ),
                'failed'             => __( 'Failed' ),
            ],
            'fulfillment' => [
                'unfulfilled' => __( 'Unfulfilled' ),
                'partial'     => __( 'Partially fulfilled' ),
                'fulfilled'   => __( 'Fulfilled' ),
            ],
            default => [],
        };
    }

    /**
     * The label for one status; an unknown value is shown as a headline.
     *
     * @since 1.0.0
     *
     * @param  string       $type   Status type.
     * @param  string|null  $value  Status value.
     *
     * @return string
     */
    public static function label( string $type, ?string $value ): string
    {
        $value = (string) $value;

        return self::labels( $type )[ $value ] ?? Str::headline( $value );
    }

    /**
     * `$type`'s statuses as `[ id, name ]` options.
     *
     * @since 1.0.0
     *
     * @param  string  $type  Status type.
     *
     * @return array<int, array{id: string, name: string}>
     */
    public static function options( string $type ): array
    {
        $options = [];

        foreach ( self::labels( $type ) as $value => $label ) {
            $options[] = [ 'id' => $value, 'name' => $label ];
        }

        return $options;
    }
}
