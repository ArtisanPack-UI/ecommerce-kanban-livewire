<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Models\KanbanBoard;
use ArtisanPackUI\Ecommerce\Models\KanbanColumn;
use ArtisanPackUI\Ecommerce\Models\Order;
use ArtisanPackUI\EcommerceKanbanLivewire\EcommerceKanbanLivewireServiceProvider;
use ArtisanPackUI\EcommerceKanbanLivewire\Livewire\Board;
use ArtisanPackUI\EcommerceKanbanLivewire\Support\BoardUrls;
use ArtisanPackUI\EcommerceKanbanLivewire\Support\Hooks;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

beforeEach( function (): void {
    grantAbilities( [ 'kanbanBoard.viewAny', 'kanbanBoard.view', 'kanbanCard.move' ] );
    $this->actingAs( makeUser() );
} );

it( 'names every hook under the ap.ecommerceKanbanLivewire prefix', function (): void {
    $hooks = ( new ReflectionClass( Hooks::class ) )->getConstants();

    expect( $hooks )->toHaveCount( 10 );

    foreach ( $hooks as $hook ) {
        expect( $hook )->toStartWith( 'ap.ecommerceKanbanLivewire.' );
    }
} );

it( 'adds header buttons from boardToolbar, dropping those the viewer may not use', function (): void {
    [ $board ] = makeBoard( [ 0 ] );
    $seen      = null;

    addFilter( Hooks::BOARD_TOOLBAR, function ( array $actions, KanbanBoard $filtered, ?Authenticatable $user ) use ( &$seen ): array {
        $seen = [ $filtered->id, $user?->getAuthIdentifier() ];

        return [
            ...$actions,
            [ 'key' => 'rush', 'label' => 'Rush mode', 'icon' => 'o-bolt', 'event' => 'rush.toggle' ],
            [ 'key' => 'report', 'label' => 'Report', 'url' => 'https://example.test/report' ],
            [ 'key' => 'admin-only', 'label' => 'Admin only', 'ability' => 'kanbanBoard.update', 'event' => 'admin.thing' ],
            [ 'key' => 'evil', 'label' => 'Evil', 'url' => 'javascript:alert(1)' ],
            [ 'key' => 'no-target', 'label' => 'Nothing' ],
            'not an action',
        ];
    } );

    $component = Livewire::test( Board::class, [ 'board' => $board ] );

    expect( array_column( $component->viewData( 'toolbar' ), 'key' ) )->toBe( [ 'rush', 'report' ] )
        ->and( $seen )->toBe( [ $board->id, auth()->id() ] );

    $component->assertSeeHtml( 'data-ec-kb-toolbar-action="rush"' )
        ->assertSeeHtml( 'rush.toggle' )
        ->assertSeeHtml( 'https://example.test/report' )
        ->assertDontSeeHtml( 'admin.thing' )
        ->assertDontSeeHtml( 'javascript:alert' );

    grantAbilities( [ 'kanbanBoard.update' ] );

    expect( array_column( Livewire::test( Board::class, [ 'board' => $board ] )->viewData( 'toolbar' ), 'key' ) )->toBe( [ 'rush', 'report', 'admin-only' ] );
} );

it( 'adds card classes from cardClasses', function (): void {
    [ $board, $columns ] = makeBoard( [ 0 ] );
    $card                = addCard( $columns[0], [ 'order_number' => 'RUSH-1' ] );
    $seen                = [];

    addFilter( Hooks::CARD_CLASSES, function ( array $classes, Order $order, KanbanColumn $column, KanbanBoard $filtered ) use ( &$seen ): array {
        $seen[] = [ $order->order_number, $column->id, $filtered->id ];

        return [ ...$classes, 'border-l-4  border-l-error', 'border-l-4', 42 ];
    } );

    $component = Livewire::test( Board::class, [ 'board' => $board ] )->call( 'loadBoard', [] );

    expect( $component->viewData( 'columns' )[0]['cards'][0]['classes'] )->toBe( [ 'border-l-4', 'border-l-error' ] )
        ->and( $seen )->toBe( [ [ 'RUSH-1', $columns[0]->id, $board->id ] ] );

    $component->assertSeeHtml( 'border-l-4 border-l-error' );

    expect( $component->call( 'refreshCards', [ $card->order_id ] )->effects['returns'][0]['cards'][ $card->order_id ] )->toContain( 'border-l-error' );
} );

it( 'runs boardRendered with what the board shows', function (): void {
    [ $board, $columns ] = makeBoard( [ 1, 0 ] );
    $calls               = [];

    addAction( Hooks::BOARD_RENDERED, function ( KanbanBoard $rendered, array $shown, ?Authenticatable $user ) use ( &$calls ): void {
        $calls[] = [ $rendered->id, array_column( $shown, 'id' ), count( $shown[0]['cards'] ), $user?->getAuthIdentifier() ];
    } );

    Livewire::test( Board::class, [ 'board' => $board ] )->call( 'loadBoard', [] );

    expect( $calls )->toBe( [
        [ $board->id, [ $columns[0]->id, $columns[1]->id ], 0, auth()->id() ],
        [ $board->id, [ $columns[0]->id, $columns[1]->id ], 1, auth()->id() ],
    ] );
} );

it( 'runs no hook for a viewer who may not see the board', function (): void {
    [ $board ] = makeBoard( [ 1 ] );
    $ran       = false;

    addAction( Hooks::BOARD_RENDERED, function () use ( &$ran ): void {
        $ran = true;
    } );
    addFilter( Hooks::BOARD_TOOLBAR, function ( array $actions ) use ( &$ran ): array {
        $ran = true;

        return $actions;
    } );
    addFilter( Hooks::CARD_CLASSES, function ( array $classes ) use ( &$ran ): array {
        $ran = true;

        return $classes;
    } );

    denyAbilities( [ 'kanbanBoard.view' ] );

    Livewire::test( Board::class, [ 'board' => $board ] )->assertForbidden();

    expect( $ran )->toBeFalse();
} );

it( 'runs cardMoveFailed with the refusal code, order, and column', function (): void {
    [ $board, $columns ] = makeBoard( [ [ 'cards' => 1, 'wip_limit' => 1 ], [ 'cards' => 1, 'wip_limit' => 1 ] ] );
    $order               = Order::query()->orderBy( 'id' )->first();
    $calls               = [];

    addAction( Hooks::CARD_MOVE_FAILED, function ( string $code, ?Order $failed, ?KanbanColumn $column, KanbanBoard $on, ?Authenticatable $user ) use ( &$calls ): void {
        $calls[] = [ $code, $failed?->id, $column?->id, $on->id, $user?->getAuthIdentifier() ];
    } );

    $component = Livewire::test( Board::class, [ 'board' => $board ] )->call( 'loadBoard', [] );

    $component->call( 'moveCard', $order->id, $columns[1]->id );
    $component->call( 'moveCard', $order->id, 999999 );
    $component->call( 'moveCard', 999999, $columns[1]->id );

    expect( $calls )->toBe( [
        [ 'wip-limit-reached', $order->id, $columns[1]->id, $board->id, auth()->id() ],
        [ 'column-not-on-board', $order->id, null, $board->id, auth()->id() ],
        [ 'not-on-board', null, $columns[1]->id, $board->id, auth()->id() ],
    ] );
} );

it( 'points board and picker links where boardUrl and pickerUrl say', function (): void {
    [ $board ] = makeBoard( [ 0 ] );

    addFilter( Hooks::BOARD_URL, fn ( ?string $url, KanbanBoard $linked, array $query ): string => 'https://host.test/boards/' . $linked->id . '?' . http_build_query( $query ) );
    addFilter( Hooks::PICKER_URL, fn (): ?string => null );

    expect( BoardUrls::show( $board, [ 'card' => 7 ] ) )->toBe( 'https://host.test/boards/' . $board->id . '?card=7' )
        ->and( BoardUrls::picker() )->toBeNull();
} );

it( 'loads the Vite entries viteEntries returns', function (): void {
    $hot = tempnam( sys_get_temp_dir(), 'hot' );
    file_put_contents( $hot, 'http://localhost:5173' );
    Vite::useHotFile( $hot );

    addFilter( Hooks::VITE_ENTRIES, fn ( array $entries ): array => [ 'resources/js/kanban.js' ] );

    $html = view( 'ecommerce-kanban::partials.vite-assets' )->render();

    expect( $html )->toContain( 'resources/js/kanban.js' )->not->toContain( 'resources/css/app.css' );

    removeAllFilters( Hooks::VITE_ENTRIES );
    addFilter( Hooks::VITE_ENTRIES, fn (): array => [] );

    expect( trim( view( 'ecommerce-kanban::partials.vite-assets' )->render() ) )->toBe( '' );

    @unlink( $hot );
} );

it( 'publishes the views as the escape hatch', function (): void {
    $paths = ServiceProvider::pathsToPublish( EcommerceKanbanLivewireServiceProvider::class, 'ecommerce-kanban-views' );

    expect( array_values( $paths ) )->toBe( [ resource_path( 'views/vendor/ecommerce-kanban' ) ] )
        ->and( realpath( array_key_first( $paths ) ) )->toBe( realpath( __DIR__ . '/../../resources/views' ) );
} );
