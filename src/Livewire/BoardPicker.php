<?php

/**
 * Board picker component.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceKanbanLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceKanbanLivewire\Livewire;

use ArtisanPackUI\EcommerceKanbanLivewire\Livewire\Concerns\AuthorizesKanban;
use ArtisanPackUI\EcommerceKanbanLivewire\Queries\BoardsQuery;
use ArtisanPackUI\EcommerceKanbanLivewire\Support\AdminLinks;
use ArtisanPackUI\EcommerceKanbanLivewire\Support\BoardUrls;
use ArtisanPackUI\EcommerceKanbanLivewire\Support\LastBoard;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Lists the boards the user can open (spec §7.1).
 *
 * Unless `$pick` is set, it sends the user straight to the board they last
 * opened, or to the only board when there is just one.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceKanbanLivewire
 *
 * @since      1.0.0
 */
class BoardPicker extends Component
{
    use AuthorizesKanban;

    /**
     * Mounts the picker, redirecting when there's an obvious board to open.
     *
     * @since 1.0.0
     *
     * @param  bool  $pick  Show the list even when there's a board to redirect to.
     *
     * @return void
     */
    public function mount( bool $pick = false ): void
    {
        $this->authorizeKanban( 'kanbanBoard.viewAny' );

        if ( $pick ) {
            return;
        }

        $user   = auth()->user();
        $boards = BoardsQuery::visibleTo( $user );
        $lastId = LastBoard::id( $user );
        $target = ( null !== $lastId ? $boards->firstWhere( 'id', $lastId ) : null )
            ?? ( 1 === $boards->count() ? $boards->first() : null );
        $url    = null !== $target ? BoardUrls::show( $target ) : null;

        if ( null !== $url ) {
            $this->redirect( $url );
        }
    }

    /**
     * Re-checks access on every update.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function hydrate(): void
    {
        $this->authorizeKanban( 'kanbanBoard.viewAny' );
    }

    /**
     * Renders the picker.
     *
     * @since 1.0.0
     *
     * @return View
     */
    public function render(): View
    {
        $user = auth()->user();

        return view( 'ecommerce-kanban::livewire.board-picker', [
            'boards'         => BoardsQuery::visibleTo( $user ),
            'lastBoardId'    => LastBoard::id( $user ),
            'createBoardUrl' => AdminLinks::createBoardUrl(),
        ] );
    }
}
