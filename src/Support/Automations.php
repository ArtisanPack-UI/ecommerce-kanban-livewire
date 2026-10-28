<?php

/**
 * Automation display helpers.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceKanbanLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceKanbanLivewire\Support;

use ArtisanPackUI\Ecommerce\Models\KanbanAutomation;
use ArtisanPackUI\Ecommerce\Models\KanbanBoard;
use ArtisanPackUI\Ecommerce\Registries\KanbanAutomationRegistry;
use ArtisanPackUI\Ecommerce\Registries\PromotionConditionRegistry;
use Throwable;

/**
 * Reads a board's automations for the automations panel and the column
 * badges (spec §7.9): trigger labels from the engine's
 * `KanbanAutomationRegistry`, a one-line conditions summary, and how many
 * active automations fire into each column.
 *
 * Trigger config is never read here: it is encrypted and can hold webhook
 * signing secrets.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceKanbanLivewire
 *
 * @since      1.0.0
 */
final class Automations
{
    /**
     * Active automations firing into each column of a board, keyed by
     * column id; columns without any are left out. One query.
     *
     * @since 1.0.0
     *
     * @param  KanbanBoard  $board  The board.
     *
     * @return array<int, int>
     */
    public static function countsInto( KanbanBoard $board ): array
    {
        return KanbanAutomation::query()
            ->where( 'board_id', $board->id )
            ->where( 'is_active', true )
            ->selectRaw( 'to_column_id, count(*) as aggregate' )
            ->groupBy( 'to_column_id' )
            ->pluck( 'aggregate', 'to_column_id' )
            ->mapWithKeys( static fn ( mixed $count, mixed $id ): array => [ (int) $id => (int) $count ] )
            ->all();
    }

    /**
     * A trigger's label, or a note that it is no longer installed.
     *
     * @since 1.0.0
     *
     * @param  string  $key  The trigger key.
     *
     * @return string
     */
    public static function triggerLabel( string $key ): string
    {
        $triggers = app( KanbanAutomationRegistry::class );

        if ( $triggers->has( $key ) ) {
            try {
                return $triggers->get( $key )->label();
            } catch ( Throwable $exception ) {
                report( $exception );
            }
        }

        return __( 'Unavailable trigger (:key)', [ 'key' => $key ] );
    }

    /**
     * A one-line summary of an automation's condition tree: every order, the
     * conditions an order must match, or a note for nested trees.
     *
     * @since 1.0.0
     *
     * @param  mixed  $tree  The stored condition tree.
     *
     * @return string
     */
    public static function conditionsSummary( mixed $tree ): string
    {
        if ( ! is_array( $tree ) || [] === $tree ) {
            return __( 'Runs for every order.' );
        }

        $leaves = self::leaves( $tree );

        if ( null === $leaves ) {
            return __( 'Runs for orders that match nested conditions.' );
        }

        if ( [] === $leaves ) {
            return __( 'Runs for every order.' );
        }

        $labels = array_map( static fn ( string $type ): string => self::conditionLabel( $type ), $leaves );

        return trans_choice(
            'Runs when the order matches :conditions.|Runs when the order matches all of: :conditions.',
            count( $labels ),
            [ 'conditions' => implode( ', ', $labels ) ],
        );
    }

    /**
     * The condition types of a flat "all" tree, or null for anything nested.
     *
     * @since 1.0.0
     *
     * @param  array<int|string, mixed>  $tree  The condition tree.
     *
     * @return array<int, string>|null
     */
    private static function leaves( array $tree ): ?array
    {
        if ( ! array_is_list( $tree ) ) {
            if ( [ 'all' ] !== array_keys( $tree ) || ! is_array( $tree['all'] ) || ! array_is_list( $tree['all'] ) ) {
                return isset( $tree['type'] ) && is_string( $tree['type'] ) ? [ $tree['type'] ] : null;
            }

            $tree = $tree['all'];
        }

        $types = [];

        foreach ( $tree as $node ) {
            if ( ! is_array( $node ) || ! is_string( $node['type'] ?? null ) ) {
                return null;
            }

            $types[] = $node['type'];
        }

        return $types;
    }

    /**
     * A promotion condition's label, or its key when it isn't registered.
     *
     * @since 1.0.0
     *
     * @param  string  $type  The condition type.
     *
     * @return string
     */
    private static function conditionLabel( string $type ): string
    {
        $conditions = app( PromotionConditionRegistry::class );

        if ( ! $conditions->has( $type ) ) {
            return $type;
        }

        try {
            return $conditions->get( $type )->label();
        } catch ( Throwable $exception ) {
            report( $exception );

            return $type;
        }
    }
}
