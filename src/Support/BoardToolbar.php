<?php

/**
 * Board header actions.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceKanbanLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceKanbanLivewire\Support;

use ArtisanPackUI\Ecommerce\Models\KanbanBoard;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Extra buttons in the board header, from the
 * `ap.ecommerceKanbanLivewire.boardToolbar` filter (empty by default).
 *
 * Each action takes the card action shape ({@see CardActions}): `key`,
 * `label`, optional `icon` and `ability` (checked against the board), and a
 * `url`, an `event` (a Livewire event dispatched with `{ boardId }` for a
 * component on the page to handle), or both, where a plain click
 * dispatches the event and a modified click follows the link.
 *
 * ```php
 * addFilter( 'ap.ecommerceKanbanLivewire.boardToolbar', function ( array $actions, KanbanBoard $board ): array {
 *     $actions[] = [ 'key' => 'rush', 'label' => __( 'Rush mode' ), 'icon' => 'o-bolt', 'ability' => 'kanbanBoard.update', 'event' => 'rush.toggle' ];
 *
 *     return $actions;
 * } );
 * ```
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceKanbanLivewire
 *
 * @since      1.0.0
 */
final class BoardToolbar
{
    /**
     * The extension filter.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public const FILTER = 'ap.ecommerceKanbanLivewire.boardToolbar';

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
     * The header actions the viewer may take.
     *
     * @since 1.0.0
     *
     * @return array<int, array{key: string, label: string, icon: string|null, url: string|null, event: string|null}>
     */
    public function actions(): array
    {
        return ActionList::normalize(
            applyFilters( self::FILTER, [], $this->board, $this->user ),
            fn ( string $ability ): bool => Authorization::allows( $this->user, $ability, $this->board ),
        );
    }
}
