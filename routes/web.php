<?php

/**
 * Board routes.
 *
 * Every route is a controller action returning a page view, so the routes
 * survive `route:cache`. Paths sit under `board.route_prefix`, names under
 * `artisanpack.ecommerce.kanban.`, and every route runs `board.middleware`
 * followed by the `ecommerce-kanban.throttle` limiter (which only counts
 * Livewire updates). `boards.show` is a fixed name: the ecommerce admin links
 * to it.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceKanbanLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

use ArtisanPackUI\EcommerceKanbanLivewire\Http\Controllers\BoardScreenController;
use ArtisanPackUI\EcommerceKanbanLivewire\Http\Middleware\ThrottleKanbanMutations;
use Illuminate\Support\Facades\Route;

Route::prefix( trim( (string) config( 'artisanpack.ecommerce-kanban-livewire.board.route_prefix', 'ecommerce-kanban' ), '/' ) )
    ->middleware( [ ...(array) config( 'artisanpack.ecommerce-kanban-livewire.board.middleware', [ 'web', 'auth' ] ), ThrottleKanbanMutations::ALIAS ] )
    ->name( 'artisanpack.ecommerce.kanban.' )
    ->group( static function (): void {
        Route::get( '/', [ BoardScreenController::class, 'index' ] )->name( 'boards.index' );
        Route::get( 'boards/{board}', [ BoardScreenController::class, 'show' ] )->whereNumber( 'board' )->name( 'boards.show' );
    } );
