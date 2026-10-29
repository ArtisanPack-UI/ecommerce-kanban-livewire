<?php

/**
 * Card move error messages.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceKanbanLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceKanbanLivewire\Support;

use ArtisanPackUI\Ecommerce\Exceptions\IncompatibleBoardSubstatusException;
use ArtisanPackUI\Ecommerce\Exceptions\InvalidOrderStatusTransitionException;
use ArtisanPackUI\Ecommerce\Exceptions\KanbanOperationException;
use ArtisanPackUI\Ecommerce\Exceptions\SubstatusTransitionRejectedException;
use Throwable;

/**
 * Turns the engine's refusals of a card move into a translated message for
 * the toast (spec §5.1). The engine's own messages name ids and English
 * enum values; these say what happened in the user's language.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceKanbanLivewire
 *
 * @since      1.0.0
 */
final class MoveErrors
{
    /**
     * The machine-readable code for a refusal, as the REST layer names it.
     *
     * @since 1.0.0
     *
     * @param  Throwable  $exception  The refusal.
     *
     * @return string
     */
    public static function code( Throwable $exception ): string
    {
        return match ( true ) {
            $exception instanceof KanbanOperationException              => $exception->errorCode,
            $exception instanceof InvalidOrderStatusTransitionException => 'invalid-status-transition',
            $exception instanceof IncompatibleBoardSubstatusException   => 'incompatible-column',
            $exception instanceof SubstatusTransitionRejectedException  => 'move-rejected',
            default                                                     => 'unknown',
        };
    }

    /**
     * The message for a refusal code.
     *
     * @since 1.0.0
     *
     * @param  string  $code    Refusal code.
     * @param  string  $column  The target column's label.
     *
     * @return string
     */
    public static function message( string $code, string $column ): string
    {
        return match ( $code ) {
            'not-on-board'              => __( 'This order is no longer on this board.' ),
            'wip-limit-reached'         => __( ':column is at its WIP limit.', [ 'column' => $column ] ),
            'move-rejected'             => __( 'This move was blocked.' ),
            'column-not-on-board'       => __( 'That column is not on this board.' ),
            'invalid-status-transition' => __( 'The order cannot move to :column from its current status.', [ 'column' => $column ] ),
            'incompatible-column'       => __( ':column cannot hold this order at its current status.', [ 'column' => $column ] ),
            'no-entry-column'           => __( 'This board has no column that can hold this order.' ),
            default                     => __( 'The card could not be moved.' ),
        };
    }
}
