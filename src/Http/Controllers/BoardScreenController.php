<?php

/**
 * Board screen controller.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceKanbanLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceKanbanLivewire\Http\Controllers;

use ArtisanPackUI\Ecommerce\Models\KanbanBoard;
use ArtisanPackUI\EcommerceKanbanLivewire\Support\Authorization;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Returns the page view for each board screen (spec §5.2).
 *
 * Every page is a controller action (not a closure, so `route:cache` works)
 * returning a view that extends the resolved layout and embeds one Livewire
 * component. The page is authorized here so a denied user gets a plain 403,
 * and the component authorizes itself again on every request.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceKanbanLivewire
 *
 * @since      1.0.0
 */
class BoardScreenController extends Controller
{
    /**
     * The board picker, which sends the user on to their last board.
     *
     * @since 1.0.0
     *
     * @param  Request  $request  The request (`pick=1` shows the list).
     *
     * @return View
     */
    public function index( Request $request ): View
    {
        abort_unless( Authorization::allows( $request->user(), 'kanbanBoard.viewAny' ), 403 );

        return view( 'ecommerce-kanban::pages.boards.index', [ 'pick' => $request->boolean( 'pick' ) ] );
    }

    /**
     * A board.
     *
     * @since 1.0.0
     *
     * @param  Request      $request  The request.
     * @param  KanbanBoard  $board    The board.
     *
     * @return View
     */
    public function show( Request $request, KanbanBoard $board ): View
    {
        abort_unless( Authorization::allows( $request->user(), 'kanbanBoard.view', $board ), 403 );

        return view( 'ecommerce-kanban::pages.boards.show', [ 'board' => $board ] );
    }
}
