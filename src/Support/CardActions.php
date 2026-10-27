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
 * (`{resource}.{action}`, checked against the board), and a `url`, an
 * `event` (a Livewire event dispatched with `{ orderId, columnId, boardId }`
 * for a component on the page to handle), or both: a plain click then
 * dispatches the event and a modified click (new tab) follows the link.
 *
 * ```php
 * addFilter( 'ap.ecommerceKanbanLivewire.cardActions', function ( array $actions, Order $order, KanbanColumn $column, KanbanBoard $board ): array {
 *     $actions[] = [ 'key' => 'reprint', 'label' => __( 'Reprint label' ), 'icon' => 'o-printer', 'ability' => 'order.update', 'event' => 'print-labels.reprint' ];
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

        $actions = applyFilters( self::FILTER, $actions, $order, $column, $this->board );
        $result  = [];

        foreach ( is_array( $actions ) ? $actions : [] as $action ) {
            $action = is_array( $action ) ? $this->normalize( $action ) : null;

            if ( null !== $action ) {
                $result[] = $action;
            }
        }

        return $result;
    }

    /**
     * A well-formed action the viewer may take, or null.
     *
     * @since 1.0.0
     *
     * @param  array<string, mixed>  $action  Raw action.
     *
     * @return array{key: string, label: string, icon: string|null, url: string|null, event: string|null}|null
     */
    private function normalize( array $action ): ?array
    {
        $string = static fn ( mixed $value ): ?string => is_string( $value ) && '' !== trim( $value ) ? trim( $value ) : null;

        $key     = $string( $action['key'] ?? null );
        $label   = $string( $action['label'] ?? null );
        $url     = SafeUrl::of( $string( $action['url'] ?? null ) );
        $event   = $string( $action['event'] ?? null );
        $ability = $string( $action['ability'] ?? null );

        if ( null === $key || null === $label || ( null === $url && null === $event ) ) {
            return null;
        }

        if ( null !== $ability && ! $this->allows( $ability ) ) {
            return null;
        }

        return [
            'key'   => $key,
            'label' => $label,
            'icon'  => Icons::name( $string( $action['icon'] ?? null ) ),
            'url'   => $url,
            'event' => $event,
        ];
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
