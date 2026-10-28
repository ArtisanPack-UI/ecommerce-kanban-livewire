<?php

/**
 * Card actions menu.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceKanbanLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceKanbanLivewire\Support;

use ArtisanPackUI\Ecommerce\Models\KanbanBoard;
use ArtisanPackUI\Ecommerce\Models\KanbanColumn;
use ArtisanPackUI\Ecommerce\Models\Order;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Builds a card's actions menu (spec §7.2): Open, Move to…, Remove from
 * board, then anything from the `ap.ecommerceKanbanLivewire.cardActions`
 * filter.
 *
 * Each action is `key`, `label`, optional `icon` and `ability`
 * (`{resource}.{action}`, checked against the board; check order-level
 * abilities in the filter callback, with the order), and a `url`, an
 * `event` (a Livewire event dispatched with `{ orderId, columnId, boardId }`
 * for a component on the page to handle), or both: a plain click then
 * dispatches the event and a modified click (new tab) follows the link.
 *
 * ```php
 * addFilter( 'ap.ecommerceKanbanLivewire.cardActions', function ( array $actions, Order $order, KanbanColumn $column, KanbanBoard $board ): array {
 *     if ( Authorization::allows( auth()->user(), 'order.update', $order ) ) {
 *         $actions[] = [ 'key' => 'reprint', 'label' => __( 'Reprint label' ), 'icon' => 'o-printer', 'event' => 'print-labels.reprint' ];
 *     }
 *
 *     return $actions;
 * } );
 * ```
 *
 * Abilities are checked once per board render, not per card; the action
 * itself re-checks against the card when it runs.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceKanbanLivewire
 *
 * @since      1.0.0
 */
final class CardActions
{
    /**
     * The extension filter.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public const FILTER = 'ap.ecommerceKanbanLivewire.cardActions';

    /**
     * The event "Open" dispatches (handled by the card slide-out).
     *
     * @since 1.0.0
     *
     * @var string
     */
    public const OPEN_EVENT = 'ec-kb-open-card';

    /**
     * The event "Move to…" dispatches (handled by the move dialog).
     *
     * @since 1.0.0
     *
     * @var string
     */
    public const MOVE_EVENT = 'ec-kb-move-card';

    /**
     * The event "Remove from board" dispatches (handled by the assignments UI).
     *
     * @since 1.0.0
     *
     * @var string
     */
    public const REMOVE_EVENT = 'ec-kb-remove-card';

    /**
     * Ability results for this render, keyed by ability.
     *
     * @since 1.0.0
     *
     * @var array<string, bool>
     */
    private array $abilities = [];

    /**
     * @since 1.0.0
     *
     * @param  KanbanBoard           $board  The board.
     * @param  Authenticatable|null  $user   The viewer.
     */
    public function __construct( private readonly KanbanBoard $board, private readonly ?Authenticatable $user )
    {
    }

    /**
     * The actions the viewer may take on a card.
     *
     * @since 1.0.0
     *
     * @param  Order         $order   The card's order.
     * @param  KanbanColumn  $column  The column the card is in.
     *
     * @return array<int, array{key: string, label: string, icon: string|null, url: string|null, event: string|null}>
     */
    public function for( Order $order, KanbanColumn $column ): array
    {
        $actions = [
            [
                'key'   => 'open',
                'label' => __( 'Open' ),
                'icon'  => 'o-arrow-top-right-on-square',
                'url'   => BoardUrls::show( $this->board, [ 'card' => $order->id ] ),
                'event' => self::OPEN_EVENT,
            ],
            [
                'key'     => 'move',
                'label'   => __( 'Move to…' ),
                'icon'    => 'o-arrows-right-left',
                'ability' => 'kanbanCard.move',
                'event'   => self::MOVE_EVENT,
            ],
            [
                'key'     => 'remove',
                'label'   => __( 'Remove from board' ),
                'icon'    => 'o-x-mark',
                'ability' => 'kanbanBoard.update',
                'event'   => self::REMOVE_EVENT,
            ],
        ];

        return ActionList::normalize(
            applyFilters( self::FILTER, $actions, $order, $column, $this->board ),
            fn ( string $ability ): bool => $this->allows( $ability ),
        );
    }

    /**
     * Whether the viewer holds `$ability` on the board, once per ability.
     *
     * @since 1.0.0
     *
     * @param  string  $ability  `{resource}.{action}`.
     *
     * @return bool
     */
    private function allows( string $ability ): bool
    {
        return $this->abilities[ $ability ] ??= Authorization::allows( $this->user, $ability, $this->board );
    }
}
