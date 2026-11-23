<?php

declare( strict_types=1 );

use ArtisanPackUI\EcommerceKanbanLivewire\EcommerceKanbanLivewireServiceProvider;
use ArtisanPackUI\EcommerceKanbanLivewire\Http\Controllers\BoardScreenController;
use ArtisanPackUI\EcommerceKanbanLivewire\Http\Middleware\ThrottleKanbanMutations;
use ArtisanPackUI\EcommerceKanbanLivewire\Support\Hooks;
use Illuminate\Support\Facades\Route;
use Livewire\Mechanisms\PersistentMiddleware\PersistentMiddleware;

it( 'registers the board routes under the prefix with controller actions', function (): void {
    $index = Route::getRoutes()->getByName( 'artisanpack.ecommerce.kanban.boards.index' );
    $show  = Route::getRoutes()->getByName( 'artisanpack.ecommerce.kanban.boards.show' );

    expect( $index )->not->toBeNull()
        ->and( $index->uri() )->toBe( 'ecommerce-kanban' )
        ->and( $index->getActionName() )->toBe( BoardScreenController::class . '@index' )
        ->and( $show->uri() )->toBe( 'ecommerce-kanban/boards/{board}' )
        ->and( $show->getActionName() )->toBe( BoardScreenController::class . '@show' )
        ->and( $show->wheres )->toBe( [ 'board' => '[0-9]+' ] )
        ->and( $show->gatherMiddleware() )->toContain( 'web', 'auth', ThrottleKanbanMutations::ALIAS );
} );

it( 'builds the fixed board URL the admin links to', function (): void {
    expect( route( 'artisanpack.ecommerce.kanban.boards.show', [ 'board' => 7 ] ) )->toEndWith( '/ecommerce-kanban/boards/7' );
} );

it( 'registers the limiter as Livewire persistent middleware', function (): void {
    expect( app( PersistentMiddleware::class )->getPersistentMiddleware() )->toContain( ThrottleKanbanMutations::class );
} );

it( 'makes the host board middleware persistent, without groups', function (): void {
    config()->set( 'artisanpack.ecommerce-kanban-livewire.board.middleware', [ 'web', 'auth', 'throttle:60,1', 'missing-alias' ] );

    expect( EcommerceKanbanLivewireServiceProvider::hostPersistentMiddleware() )->toBe( [
        Illuminate\Auth\Middleware\Authenticate::class,
        Illuminate\Routing\Middleware\ThrottleRequests::class,
    ] );
} );

it( 'sends guests to authenticate', function (): void {
    $this->getJson( route( 'artisanpack.ecommerce.kanban.boards.index' ) )->assertUnauthorized();
} );

it( 'answers 403 on the picker without kanbanBoard.viewAny', function (): void {
    $this->actingAs( makeUser() )->get( route( 'artisanpack.ecommerce.kanban.boards.index' ) )->assertForbidden();
} );

it( 'answers 403 on a board without kanbanBoard.view', function (): void {
    grantAbilities( [ 'kanbanBoard.viewAny' ] );
    [ $board ] = makeBoard();

    $this->actingAs( makeUser() )->get( route( 'artisanpack.ecommerce.kanban.boards.show', [ 'board' => $board->id ] ) )->assertForbidden();
} );

it( 'answers 404 for a board that does not exist or a non-numeric id', function (): void {
    grantAbilities( [ 'kanbanBoard.viewAny', 'kanbanBoard.view' ] );
    $this->actingAs( makeUser() );

    $this->get( '/ecommerce-kanban/boards/999' )->assertNotFound();
    $this->get( '/ecommerce-kanban/boards/production' )->assertNotFound();
} );

it( 'renders a board page in the standalone layout', function (): void {
    grantAbilities( [ 'kanbanBoard.viewAny', 'kanbanBoard.view' ] );
    [ $board ] = makeBoard( [ 0 ], [ 'name' => 'Production' ] );

    $this->actingAs( makeUser() )
        ->get( route( 'artisanpack.ecommerce.kanban.boards.show', [ 'board' => $board->id ] ) )
        ->assertOk()
        ->assertSee( '<title>Production', false )
        ->assertSee( 'Skip to content' )
        ->assertSee( 'id="ecommerce-kanban-content"', false )
        ->assertSee( 'data-ecommerce-kanban-rate-limit', false )
        ->assertSeeLivewire( 'artisanpack-ecommerce-kanban-board' );
} );

it( 'frames the standalone layout with a skip link first, the Navbar brand, and no utility classes', function (): void {
    grantAbilities( [ 'kanbanBoard.viewAny', 'kanbanBoard.view' ] );
    [ $board ] = makeBoard( [ 0 ], [ 'name' => 'Production' ] );

    $html = $this->actingAs( makeUser() )
        ->get( route( 'artisanpack.ecommerce.kanban.boards.show', [ 'board' => $board->id ] ) )
        ->assertOk()
        ->getContent();

    $body = (string) strstr( $html, '<body' );

    expect( $html )->toContain( '<html lang="en" dir="ltr" class="ec-kb-standalone">' )
        ->and( $body )->toMatch( '/^<body class="ec-kb-page">\s*<a\s+href="#ecommerce-kanban-content"\s+class="apui-button ec-kb-skip-link"/' )
        ->and( $body )->toMatch( '/data-variant="solid"\s+data-color="primary"\s+data-size="sm"/' )
        ->and( $body )->toContain( 'class="apui-navbar ec-kb-standalone-nav"' )
        ->and( $body )->toContain( 'class="ec-kb-brand"' )
        ->and( $body )->toMatch( '/apui-icon-tile ec-kb-brand-mark/' )
        ->and( $body )->toMatch( '/<a href="[^"]+" class="ec-kb-brand" data-ec-kb-brand>\s*<span\s+class="apui-icon apui-icon-tile ec-kb-brand-mark"[^>]*\saria-hidden="true"\s*><svg [^>]*stroke="currentColor"[^>]*data-ec-kb-brand-glyph><path d="M7\.5 6\.5v9M12 6\.5v4\.5M16\.5 6\.5v11"\/><\/svg>\s*<\/span>\s*<span>Kanban<\/span>\s*<\/a>/' )
        ->and( (string) strstr( (string) strstr( $body, '<nav' ), '</nav>', true ) )->not->toContain( 'heroicon' )
        ->and( $body )->toContain( 'aria-label="Switch to dark theme"' )
        ->and( $body )->toContain( '<main id="ecommerce-kanban-content" class="ec-kb-standalone-main" tabindex="-1">' )
        ->and( (string) strstr( $body, '<main', true ) )->not->toMatch( '/class="(?:[^"]*\s)?(sr-only|focus:\S*|antialiased|p-4|w-full|font-bold)(?:\s[^"]*)?"/' );
} );

it( 'sets the page direction from the locale', function ( string $locale, string $tag ): void {
    grantAbilities( [ 'kanbanBoard.viewAny', 'kanbanBoard.view' ] );
    [ $board ] = makeBoard( [ 0 ], [ 'name' => 'Production' ] );

    app()->setLocale( $locale );

    expect( $this->actingAs( makeUser() )
        ->get( route( 'artisanpack.ecommerce.kanban.boards.show', [ 'board' => $board->id ] ) )
        ->assertOk()
        ->getContent() )->toContain( $tag );
} )->with( [
    'Arabic'           => [ 'ar', '<html lang="ar" dir="rtl" class="ec-kb-standalone">' ],
    'Hebrew (Israel)'  => [ 'he_IL', '<html lang="he-IL" dir="rtl" class="ec-kb-standalone">' ],
    'Portuguese (BR)'  => [ 'pt_BR', '<html lang="pt-BR" dir="ltr" class="ec-kb-standalone">' ],
    'Japanese'         => [ 'ja', '<html lang="ja" dir="ltr" class="ec-kb-standalone">' ],
] );

it( 'draws the brand glyph in an unlinked brand when there is no picker URL', function (): void {
    grantAbilities( [ 'kanbanBoard.viewAny', 'kanbanBoard.view' ] );
    [ $board ] = makeBoard( [ 0 ], [ 'name' => 'Production' ] );

    addFilter( Hooks::PICKER_URL, fn (): ?string => null );

    $body = (string) strstr( $this->actingAs( makeUser() )
        ->get( route( 'artisanpack.ecommerce.kanban.boards.show', [ 'board' => $board->id ] ) )
        ->assertOk()
        ->getContent(), '<body' );

    expect( $body )->toMatch( '/<span class="ec-kb-brand" data-ec-kb-brand>\s*<span\s+class="apui-icon apui-icon-tile ec-kb-brand-mark"[^>]*\saria-hidden="true"\s*><svg [^>]*data-ec-kb-brand-glyph>.*?<\/svg>\s*<\/span>\s*<span>Kanban<\/span>\s*<\/span>/s' )
        ->and( $body )->not->toMatch( '/<a [^>]*class="ec-kb-brand"/' );
} );

it( 'renders the picker page', function (): void {
    grantAbilities( [ 'kanbanBoard.viewAny', 'kanbanBoard.view' ] );
    makeBoard( [ 0 ], [ 'name' => 'Production' ] );
    makeBoard( [ 0 ], [ 'name' => 'Shipping' ] );

    $this->actingAs( makeUser() )
        ->get( route( 'artisanpack.ecommerce.kanban.boards.index' ) )
        ->assertOk()
        ->assertSee( 'Kanban boards' )
        ->assertSeeLivewire( 'artisanpack-ecommerce-kanban-board-picker' );
} );

it( 'hides a switched-off board from people who can\'t edit it', function (): void {
    grantAbilities( [ 'kanbanBoard.viewAny', 'kanbanBoard.view' ] );
    denyAbilities( [ 'kanbanBoard.update' ] );
    [ $board ] = makeBoard( [ 0 ], [ 'name' => 'Retired', 'is_active' => false ] );

    $this->actingAs( makeUser() )->get( route( 'artisanpack.ecommerce.kanban.boards.show', [ 'board' => $board->id ] ) )->assertNotFound();

    Livewire\Livewire::test( ArtisanPackUI\EcommerceKanbanLivewire\Livewire\Board::class, [ 'board' => $board ] )->assertNotFound();
} );

it( 'opens a switched-off board for an editor, says so, and doesn\'t remember it', function (): void {
    grantAbilities( [ 'kanbanBoard.viewAny', 'kanbanBoard.view', 'kanbanBoard.update' ] );
    [ $board ] = makeBoard( [ 0 ], [ 'name' => 'Retired', 'is_active' => false ] );
    $user      = makeUser();

    $this->actingAs( $user )
        ->get( route( 'artisanpack.ecommerce.kanban.boards.show', [ 'board' => $board->id ] ) )
        ->assertOk()
        ->assertSee( 'This board is switched off' )
        ->assertSee( 'Only people who can edit it see it.' );

    expect( ArtisanPackUI\EcommerceKanbanLivewire\Support\LastBoard::id( $user ) )->toBeNull();

    $this->get( route( 'artisanpack.ecommerce.kanban.boards.index', [ 'pick' => 1 ] ) )->assertDontSee( 'Retired' );
} );

it( 'sends the rate-limit message to the toaster, which renders it as text', function (): void {
    grantAbilities( [ 'kanbanBoard.viewAny', 'kanbanBoard.view' ] );
    [ $board ] = makeBoard( [ 0 ] );

    $this->actingAs( makeUser() )
        ->get( route( 'artisanpack.ecommerce.kanban.boards.show', [ 'board' => $board->id ] ) )
        ->assertSee( "new CustomEvent( 'toast', { detail: { title, description: message, color: 'warning' } } )", false )
        ->assertDontSee( 'innerHTML', false );
} );

it( 'inlines the board\'s token-only stylesheet once, with the CSP nonce', function (): void {
    grantAbilities( [ 'kanbanBoard.viewAny', 'kanbanBoard.view' ] );
    [ $board ] = makeBoard( [ 0 ] );
    Illuminate\Support\Facades\Vite::useCspNonce( 'kanban-nonce' );

    $html = $this->actingAs( makeUser() )
        ->get( route( 'artisanpack.ecommerce.kanban.boards.show', [ 'board' => $board->id ] ) )
        ->assertOk()
        ->getContent();

    expect( substr_count( $html, 'data-ecommerce-kanban-styles' ) )->toBe( 1 )
        ->and( $html )->toMatch( '/<style data-ecommerce-kanban-styles\s+nonce="kanban-nonce"\s*>/' )
        ->and( $html )->toContain( '.ec-kb-card {' )
        ->and( $html )->not->toContain( 'daisyui' );
} );
