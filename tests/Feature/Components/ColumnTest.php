<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Models\KanbanAutomation;
use ArtisanPackUI\EcommerceKanbanLivewire\Livewire\Board;
use Livewire\Livewire;

beforeEach( function (): void {
    grantAbilities( [ 'kanbanBoard.viewAny', 'kanbanBoard.view' ] );
    $this->actingAs( makeUser() );
} );

/**
 * The markup of one column in `$html`.
 */
function columnHtml( string $html, int $columnId ): string
{
    $start = strpos( $html, 'data-ec-kb-column="' . $columnId . '"' );
    $end   = false === $start ? false : strpos( $html, '</section>', $start );

    return false === $start || false === $end ? '' : substr( $html, $start, $end - $start );
}

it( 'renders the column on the token-only surface with a color dot beside the name', function (): void {
    [ $board, $columns ] = makeBoard( [ [ 'cards' => 1, 'color_override' => '#112233' ] ] );

    $column = columnHtml( Livewire::test( Board::class, [ 'board' => $board ] )->call( 'loadBoard', [] )->html(), $columns[0]->id );

    expect( $column )->toMatch( '/<span class="ec-kb-column-dot" aria-hidden="true"\s+style="--ec-kb-column-color: #112233"/' )
        ->and( $column )->toContain( 'class="ec-kb-column-title"' )
        ->and( $column )->not->toContain( 'border-top-color' )
        ->and( $column )->not->toContain( 'rounded-box' )
        ->and( $column )->not->toContain( 'skeleton h-20' );
} );

it( 'labels the collapse button with the column and its state', function (): void {
    [ $board, $columns ] = makeBoard( [ [ 'cards' => 1, 'label_override' => 'To pick' ] ] );

    $component = Livewire::test( Board::class, [ 'board' => $board ] )->call( 'loadBoard', [] );
    $column    = columnHtml( $component->html(), $columns[0]->id );

    expect( $column )->toMatch( '/aria-expanded="true"\s+aria-controls="ec-kb-column-' . $columns[0]->id . '-cards"\s+aria-label="Collapse To pick"/' )
        ->and( $column )->toContain( 'data-variant="ghost"' );

    $column = columnHtml( $component->call( 'toggleColumn', $columns[0]->id )->html(), $columns[0]->id );

    expect( $column )->toContain( 'data-ec-kb-collapsed' )
        ->and( $column )->toMatch( '/aria-expanded="false"[^>]*aria-label="Expand To pick"/' )
        ->and( $column )->toMatch( '/id="ec-kb-column-' . $columns[0]->id . '-cards" class="ec-kb-column-body"\s+hidden/' )
        ->and( $column )->not->toContain( 'data-ec-kb-card=' );
} );

it( 'shows the WIP limit as a soft neutral badge that turns to warning with an icon at the limit', function (): void {
    [ $board, $columns ] = makeBoard( [ [ 'cards' => 2, 'wip_limit' => 3 ], [ 'cards' => 3, 'wip_limit' => 3 ] ] );

    $html  = Livewire::test( Board::class, [ 'board' => $board ] )->call( 'loadBoard', [] )->html();
    $under = columnHtml( $html, $columns[0]->id );
    $at    = columnHtml( $html, $columns[1]->id );

    expect( $under )->toMatch( '/class="apui-badge"[^>]*data-ec-kb-wip[^>]*data-variant="soft"\s+data-color="neutral"\s+data-size="sm"/' )
        ->and( $under )->toContain( '<span class="apui-sr-only">2 of 3 cards</span>' )
        ->and( $at )->toMatch( '/data-ec-kb-wip-reached[^>]*data-variant="soft"\s+data-color="warning"/' )
        ->and( $at )->toContain( 'data-part="icon"' )
        ->and( $at )->toContain( '<span class="apui-sr-only">3 of 3 cards, WIP limit reached</span>' );
} );

it( 'says how many automations run when a card moves into the column', function (): void {
    [ $board, $columns ] = makeBoard( [ 1, [ 'cards' => 0, 'label_override' => 'Packing' ] ] );
    KanbanAutomation::factory()->count( 2 )->create( [ 'board_id' => $board->id, 'to_column_id' => $columns[1]->id, 'trigger_key' => 'test-label', 'trigger_config' => [] ] );

    $column = columnHtml( Livewire::test( Board::class, [ 'board' => $board ] )->call( 'loadBoard', [] )->html(), $columns[1]->id );

    expect( $column )->toContain( 'aria-label="2 automations run when a card moves into Packing"' )
        ->and( $column )->toContain( 'data-ec-kb-column-automations="2"' );
} );

it( 'draws two design-system skeleton cards while the column loads', function (): void {
    [ $board, $columns ] = makeBoard( [ 1 ] );

    $column = columnHtml( Livewire::test( Board::class, [ 'board' => $board ] )->html(), $columns[0]->id );

    expect( substr_count( $column, 'class="apui-skeleton"' ) )->toBe( 2 )
        ->and( $column )->toContain( 'class="ec-kb-column-loading"' );
} );

it( 'offers Show more as a full-width ghost button', function (): void {
    config()->set( 'artisanpack.ecommerce-kanban-livewire.cards.per_column', 2 );
    [ $board, $columns ] = makeBoard( [ 3 ] );

    $column = columnHtml( Livewire::test( Board::class, [ 'board' => $board ] )->call( 'loadBoard', [] )->html(), $columns[0]->id );

    expect( $column )->toMatch( '/class="apui-button ec-kb-column-more".*?data-ec-kb-show-more="data-ec-kb-show-more"\s+data-variant="ghost"\s+data-color="neutral"\s+data-size="sm"/s' );
} );

it( 'gives the edit tools labelled icon-only buttons with tooltips', function (): void {
    grantAbilities( [ 'kanbanBoard.update' ] );
    [ $board, $columns ] = makeBoard( [ [ 'cards' => 0, 'label_override' => 'Packing' ], 0 ] );

    $column = columnHtml( Livewire::test( Board::class, [ 'board' => $board ] )->call( 'startEditing' )->html(), $columns[0]->id );

    expect( $column )->toContain( 'role="toolbar" aria-label="Edit Packing"' )
        ->and( $column )->toContain( 'aria-label="Move Packing left"' )
        ->and( $column )->toContain( 'aria-label="Remove Packing"' )
        ->and( substr_count( $column, 'role="tooltip"' ) )->toBe( 6 )
        ->and( $column )->toMatch( '/data-ec-kb-move-column="left"[^>]*data-icon-only|data-icon-only[^>]*data-ec-kb-move-column="left"|disabled[^>]*data-ec-kb-move-column="left"/s' );
} );
