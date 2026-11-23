<?php

declare( strict_types=1 );

use ArtisanPackUI\EcommerceKanbanLivewire\Livewire\Board;
use ArtisanPackUI\EcommerceKanbanLivewire\Livewire\BoardPicker;
use Illuminate\Support\Facades\Blade;
use Livewire\Livewire;

beforeEach( function (): void {
    grantAbilities( [ 'kanbanBoard.viewAny', 'kanbanBoard.view' ] );
    $this->actingAs( makeUser() );
} );

it( 'renders the full empty state as the design system\'s EmptyState in a Card, with a neutral tile by default', function (): void {
    $html = Blade::render( '<x-artisanpack-ec-kb-empty icon="o-view-columns" title="Nothing here" description="Add something." data-probe />' );

    expect( $html )->toMatch( '/<div[^>]*class="apui-card ec-kb-empty-card"[^>]*data-probe/' )
        ->and( $html )->toMatch( '/class="apui-empty-state ec-kb-empty"[^>]*data-variant="no-results"/' )
        ->and( $html )->toMatch( '/<h2[^>]*data-part="title"[^>]*>Nothing here<\/h2>/' )
        ->and( $html )->toContain( 'Add something.' )
        ->and( $html )->not->toContain( 'role="status"' );
} );

it( 'gives a first-use empty state the primary tile, and a live one a status role', function (): void {
    $html = Blade::render( '<x-artisanpack-ec-kb-empty first-use live title="No boards yet" />' );

    expect( $html )->toContain( 'data-variant="first-use"' )
        ->and( $html )->toMatch( '/class="apui-empty-state ec-kb-empty"[^>]*role="status"|role="status"[^>]*class="apui-empty-state ec-kb-empty"/' );
} );

it( 'puts the default slot in the empty state\'s actions', function (): void {
    $html = Blade::render( '<x-artisanpack-ec-kb-empty title="Empty"><button type="button">Do it</button></x-artisanpack-ec-kb-empty>' );

    expect( $html )->toMatch( '/data-part="actions"[^>]*><button type="button">Do it<\/button>/' );
} );

it( 'renders the compact empty state as a dashed, token-only box without a heading or a status role', function (): void {
    $html = Blade::render( '<x-artisanpack-ec-kb-empty compact icon="o-cube" title="No orders in this column" />' );

    expect( $html )->toMatch( '/<div class="ec-kb-empty" data-ec-kb-compact\s*>/' )
        ->and( $html )->toContain( '<p>No orders in this column</p>' )
        ->and( $html )->toContain( 'apui-icon' )
        ->and( $html )->not->toContain( 'apui-card' )
        ->and( $html )->not->toContain( '<h2' )
        ->and( $html )->not->toContain( 'role="status"' );

    // The dashed edge is the drop-target hint; the column hides it once a card lands.
    $css = (string) file_get_contents( dirname( __DIR__, 3 ) . '/resources/css/kanban.css' );

    expect( $css )->toContain( 'border: var(--apui-border-width) dashed var(--apui-border-default);' )
        ->and( $css )->toContain( '.ec-kb-column-cards:has([data-ec-kb-card]) > [data-ec-kb-empty-column]' );
} );

it( 'offers Create board on the first-use picker empty state', function (): void {
    Livewire::test( BoardPicker::class )
        ->assertSeeHtml( 'data-ec-kb-no-boards' )
        ->assertSeeHtml( 'data-variant="first-use"' )
        ->assertSee( 'No boards yet' );
} );

it( 'offers Edit board on a board without columns only to people who may edit it', function (): void {
    [ $board ] = makeBoard( [] );

    Livewire::test( Board::class, [ 'board' => $board ] )
        ->assertSeeHtml( 'data-ec-kb-no-columns' )
        ->assertSee( 'This board has no columns' )
        ->assertDontSeeHtml( 'data-ec-kb-empty-edit-board' );

    grantAbilities( [ 'kanbanBoard.update' ] );

    Livewire::test( Board::class, [ 'board' => $board ] )
        ->assertSeeHtml( 'data-ec-kb-empty-edit-board' )
        ->call( 'startEditing' )
        ->assertSet( 'editing', true );
} );

it( 'shows a filter icon in a column the filters emptied', function (): void {
    [ $board ] = makeBoard( [ 0 ] );

    Livewire::test( Board::class, [ 'board' => $board ] )
        ->call( 'loadBoard', [] )
        ->assertSee( 'No orders in this column' )
        ->set( 'search', 'nothing-matches' )
        ->assertSee( 'No matching orders in this column' );
} );
