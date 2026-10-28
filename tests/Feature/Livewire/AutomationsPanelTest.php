<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Events\KanbanBoardConfigChanged;
use ArtisanPackUI\Ecommerce\Models\KanbanAutomation;
use ArtisanPackUI\Ecommerce\Registries\PromotionConditionRegistry;
use ArtisanPackUI\Ecommerce\Registries\SatelliteRegistry;
use ArtisanPackUI\EcommerceKanbanLivewire\Livewire\AutomationsPanel;
use ArtisanPackUI\EcommerceKanbanLivewire\Livewire\Board;
use ArtisanPackUI\EcommerceKanbanLivewire\Support\AdminLinks;
use ArtisanPackUI\EcommerceKanbanLivewire\Support\Automations;
use ArtisanPackUI\EcommerceKanbanLivewire\Support\LayoutResolver;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;

beforeEach( function (): void {
    grantAbilities( [ 'kanbanBoard.viewAny', 'kanbanBoard.view', 'kanbanBoard.update' ] );
    $this->actingAs( makeUser() );
} );

/**
 * A board with Packing → Shipped and three automations: one into Shipped
 * from Packing, one into Shipped from any column (switched off), and one
 * into Packing.
 *
 * @return array{0: ArtisanPackUI\Ecommerce\Models\KanbanBoard, 1: Illuminate\Support\Collection, 2: array<string, KanbanAutomation>}
 */
function automatedBoard(): array
{
    [ $board, $columns ] = makeBoard( [ [ 'cards' => 0, 'label_override' => 'Packing' ], [ 'cards' => 0, 'label_override' => 'Shipped' ] ] );

    $automations = [
        'email'   => KanbanAutomation::factory()->create( [ 'board_id' => $board->id, 'from_column_id' => $columns[0]->id, 'to_column_id' => $columns[1]->id, 'trigger_key' => 'send-email', 'trigger_config' => [] ] ),
        'webhook' => KanbanAutomation::factory()->create( [ 'board_id' => $board->id, 'to_column_id' => $columns[1]->id, 'trigger_key' => 'webhook', 'trigger_config' => [ 'secret' => 'shh' ], 'is_active' => false ] ),
        'packing' => KanbanAutomation::factory()->create( [ 'board_id' => $board->id, 'to_column_id' => $columns[0]->id ] ),
    ];

    return [ $board, $columns, $automations ];
}

/**
 * Pretends the ecommerce admin is installed with its board edit route.
 */
function fakeAdminInstalled(): void
{
    Route::get( '/admin/kanban-boards/{board}/edit', fn () => 'ok' )->name( AdminLinks::BOARD_EDIT_ROUTE );
    app( 'router' )->getRoutes()->refreshNameLookups();
    test()->partialMock( SatelliteRegistry::class, function ( $mock ): void {
        $mock->shouldReceive( 'has' )->with( LayoutResolver::ADMIN_PACKAGE )->andReturnTrue();
        $mock->shouldReceive( 'isActive' )->with( LayoutResolver::ADMIN_PACKAGE )->andReturnTrue();
    } );
}

it( 'lists every automation on the board with its trigger, columns, conditions, and state', function (): void {
    [ $board, , $automations ] = automatedBoard();

    $component = Livewire::test( AutomationsPanel::class, [ 'boardId' => $board->id ] )
        ->assertDontSeeHtml( 'data-ec-kb-automation=' )
        ->dispatch( 'ec-kb-edit-automations', columnId: null )
        ->assertSet( 'open', true )
        ->assertSet( 'columnId', null )
        ->assertSee( 'Automations on ' . $board->name )
        ->assertSeeHtml( 'data-ec-kb-automation="' . $automations['email']->id . '"' )
        ->assertSeeHtml( 'data-ec-kb-automation="' . $automations['webhook']->id . '"' )
        ->assertSeeHtml( 'data-ec-kb-automation="' . $automations['packing']->id . '"' )
        ->assertSee( 'Send an email' )
        ->assertSee( 'Send a webhook' )
        ->assertSee( 'Any column' )
        ->assertSee( 'Runs for every order.' )
        ->assertDontSee( 'shh' );

    expect( $component->html() )->not->toContain( '<x-artisanpack' );
} );

it( 'splits a column into what runs into it and what runs out of it', function (): void {
    [ $board, $columns, $automations ] = automatedBoard();

    $component = Livewire::test( AutomationsPanel::class, [ 'boardId' => $board->id ] )->call( 'openFor', $columns[0]->id );
    $groups    = $component->viewData( 'groups' );

    expect( array_column( $groups, 'key' ) )->toBe( [ 'into', 'out' ] )
        ->and( array_column( $groups[0]['rows'], 'id' ) )->toBe( [ $automations['packing']->id ] )
        ->and( array_column( $groups[1]['rows'], 'id' ) )->toBe( [ $automations['email']->id ] );

    $component->assertSee( 'Automations for Packing' )->assertSee( 'Into this column' )->assertSee( 'Out of this column' );

    $shipped = Livewire::test( AutomationsPanel::class, [ 'boardId' => $board->id ] )->call( 'openFor', $columns[1]->id )->viewData( 'groups' );

    expect( array_column( $shipped[0]['rows'], 'id' ) )->toBe( [ $automations['email']->id, $automations['webhook']->id ] )
        ->and( $shipped[1]['rows'] )->toBe( [] );
} );

it( 'ignores a column from another board', function (): void {
    [ $board ]          = automatedBoard();
    [ , $otherColumns ] = makeBoard( [ 0 ] );

    Livewire::test( AutomationsPanel::class, [ 'boardId' => $board->id ] )->call( 'openFor', $otherColumns[0]->id )->assertSet( 'open', false );
} );

it( 'shows an empty state', function (): void {
    [ $board, $columns ] = makeBoard( [ 0 ] );

    Livewire::test( AutomationsPanel::class, [ 'boardId' => $board->id ] )
        ->call( 'openFor', null )
        ->assertSee( 'This board has no automations.' )
        ->call( 'openFor', $columns[0]->id )
        ->assertSee( 'Nothing runs when a card moves into this column.' )
        ->assertSee( 'Nothing runs only when a card leaves this column.' );
} );

it( 'lets viewers read the list without switching or deleting', function (): void {
    [ $board, , $automations ] = automatedBoard();
    denyAbilities( [ 'kanbanBoard.update' ] );

    $component = Livewire::test( AutomationsPanel::class, [ 'boardId' => $board->id ] )
        ->call( 'openFor', null )
        ->assertSeeHtml( 'data-ec-kb-automation-state' )
        ->assertDontSeeHtml( 'data-ec-kb-automation-toggle' )
        ->assertDontSeeHtml( 'data-ec-kb-delete-automation' );

    $component->call( 'toggleActive', $automations['email']->id )->assertForbidden();
    Livewire::test( AutomationsPanel::class, [ 'boardId' => $board->id ] )->call( 'confirmDelete', $automations['email']->id )->assertForbidden();
    Livewire::test( AutomationsPanel::class, [ 'boardId' => $board->id ] )->call( 'deleteAutomation', 'token' )->assertForbidden();

    expect( $automations['email']->fresh()->is_active )->toBeTrue();
} );

it( 'needs kanbanBoard.view to open', function (): void {
    [ $board ] = automatedBoard();
    denyAbilities( [ 'kanbanBoard.view' ] );

    Livewire::test( AutomationsPanel::class, [ 'boardId' => $board->id ] )->assertForbidden();
} );

it( 'switches an automation on and off and tells the board', function (): void {
    [ $board, , $automations ] = automatedBoard();
    Event::fake( [ KanbanBoardConfigChanged::class ] );

    $component = Livewire::test( AutomationsPanel::class, [ 'boardId' => $board->id ] )
        ->call( 'openFor', null )
        ->call( 'toggleActive', $automations['email']->id )
        ->assertDispatched( 'ec-kb-board-changed' );

    expect( $automations['email']->fresh()->is_active )->toBeFalse()
        ->and( json_encode( $component->effects['xjs'] ?? [] ) )->toContain( 'Automation switched off.' );

    $component->call( 'toggleActive', $automations['email']->id );

    expect( $automations['email']->fresh()->is_active )->toBeTrue()
        ->and( json_encode( $component->effects['xjs'] ?? [] ) )->toContain( 'Automation switched on.' );

    Event::assertDispatched( KanbanBoardConfigChanged::class, fn ( KanbanBoardConfigChanged $event ): bool => (int) $event->board->id === (int) $board->id && KanbanBoardConfigChanged::BOARD_UPDATED === $event->change );
} );

it( 'leaves automations on other boards alone', function (): void {
    [ $board ]   = automatedBoard();
    [ , , $own ] = automatedBoard();

    Livewire::test( AutomationsPanel::class, [ 'boardId' => $board->id ] )
        ->call( 'toggleActive', $own['email']->id )
        ->call( 'confirmDelete', $own['email']->id )
        ->assertSet( 'deletingId', null );

    expect( $own['email']->fresh()->is_active )->toBeTrue();
} );

it( 'deletes an automation once per confirmation token', function (): void {
    [ $board, , $automations ] = automatedBoard();

    $component = Livewire::test( AutomationsPanel::class, [ 'boardId' => $board->id ] )
        ->call( 'openFor', null )
        ->call( 'confirmDelete', $automations['packing']->id )
        ->assertSeeHtml( 'data-ec-kb-confirm-delete-automation' );

    $token = $component->viewData( 'deleting' )['token'];

    $component->call( 'deleteAutomation', $token )->assertDispatched( 'ec-kb-board-changed' )->assertSet( 'deletingId', null );

    expect( KanbanAutomation::query()->find( $automations['packing']->id ) )->toBeNull()
        ->and( json_encode( $component->effects['xjs'] ?? [] ) )->toContain( 'Automation deleted.' );

    $component->call( 'confirmDelete', $automations['email']->id );
    $replayed = $component->call( 'deleteAutomation', $token );

    expect( KanbanAutomation::query()->find( $automations['email']->id ) )->not->toBeNull()
        ->and( json_encode( $replayed->effects['xjs'] ?? [] ) )->toContain( 'This action has expired.' );
} );

it( 'does nothing when deleting without a confirmation', function (): void {
    [ $board, , $automations ] = automatedBoard();

    Livewire::test( AutomationsPanel::class, [ 'boardId' => $board->id ] )->call( 'deleteAutomation', 'bogus' );

    expect( KanbanAutomation::query()->count() )->toBe( 3 )
        ->and( $automations['email']->fresh() )->not->toBeNull();
} );

it( 'cancels a delete', function (): void {
    [ $board, , $automations ] = automatedBoard();

    Livewire::test( AutomationsPanel::class, [ 'boardId' => $board->id ] )
        ->call( 'openFor', null )
        ->call( 'confirmDelete', $automations['email']->id )
        ->call( 'cancelDelete' )
        ->assertSet( 'deletingId', null )
        ->assertDontSeeHtml( 'data-ec-kb-confirm-delete-automation' );
} );

it( 'is list and toggle only without the admin', function (): void {
    [ $board ] = automatedBoard();

    Livewire::test( AutomationsPanel::class, [ 'boardId' => $board->id ] )
        ->call( 'openFor', null )
        ->assertSeeHtml( 'data-ec-kb-automation-toggle' )
        ->assertDontSeeHtml( 'data-ec-kb-add-automation' )
        ->assertDontSeeHtml( 'data-ec-kb-edit-automation' );
} );

it( 'links Add and Edit to the admin automation forms when the admin is installed', function (): void {
    [ $board, , $automations ] = automatedBoard();
    fakeAdminInstalled();

    Livewire::test( AutomationsPanel::class, [ 'boardId' => $board->id ] )
        ->call( 'openFor', null )
        ->assertSeeHtml( 'data-ec-kb-add-automation' )
        ->assertSeeHtml( '/admin/kanban-boards/' . $board->id . '/edit?automation=new#board-automations-heading' )
        ->assertSeeHtml( '/admin/kanban-boards/' . $board->id . '/edit?automation=' . $automations['email']->id . '#board-automations-heading' );

    denyAbilities( [ 'kanbanBoard.update' ] );

    Livewire::test( AutomationsPanel::class, [ 'boardId' => $board->id ] )
        ->call( 'openFor', null )
        ->assertDontSeeHtml( 'data-ec-kb-add-automation' )
        ->assertDontSeeHtml( 'data-ec-kb-edit-automation' );
} );

it( 'labels triggers that are no longer installed', function (): void {
    [ $board, $columns ] = makeBoard( [ 0 ] );
    KanbanAutomation::factory()->create( [ 'board_id' => $board->id, 'to_column_id' => $columns[0]->id, 'trigger_key' => 'gone-trigger' ] );

    Livewire::test( AutomationsPanel::class, [ 'boardId' => $board->id ] )->call( 'openFor', null )->assertSee( 'Unavailable trigger (gone-trigger)' );
} );

it( 'summarizes conditions', function (): void {
    $conditions = app( PromotionConditionRegistry::class );
    $type       = $conditions->keys()[0];
    $label      = $conditions->get( $type )->label();

    expect( Automations::conditionsSummary( [] ) )->toBe( 'Runs for every order.' )
        ->and( Automations::conditionsSummary( null ) )->toBe( 'Runs for every order.' )
        ->and( Automations::conditionsSummary( [ 'all' => [] ] ) )->toBe( 'Runs for every order.' )
        ->and( Automations::conditionsSummary( [ [ 'type' => $type, 'config' => [] ] ] ) )->toBe( 'Runs when the order matches ' . $label . '.' )
        ->and( Automations::conditionsSummary( [ 'all' => [ [ 'type' => $type ], [ 'type' => 'unknown-type' ] ] ] ) )->toBe( 'Runs when the order matches all of: ' . $label . ', unknown-type.' )
        ->and( Automations::conditionsSummary( [ 'type' => $type ] ) )->toBe( 'Runs when the order matches ' . $label . '.' )
        ->and( Automations::conditionsSummary( [ 'any' => [ [ 'type' => $type ] ] ] ) )->toBe( 'Runs for orders that match nested conditions.' )
        ->and( Automations::conditionsSummary( [ 'all' => [ [ 'not' => [ 'type' => $type ] ] ] ] ) )->toBe( 'Runs for orders that match nested conditions.' );
} );

it( 'badges each column with the active automations firing into it', function (): void {
    [ $board, $columns ] = automatedBoard();

    $component = Livewire::test( Board::class, [ 'board' => $board ] )->call( 'loadBoard', [] );
    $shown     = collect( $component->viewData( 'columns' ) )->pluck( 'automations', 'id' )->all();

    expect( $shown )->toBe( [ $columns[0]->id => 1, $columns[1]->id => 1 ] )
        ->and( Automations::countsInto( $board ) )->toBe( [ $columns[0]->id => 1, $columns[1]->id => 1 ] );

    $component->assertSeeHtml( 'data-ec-kb-column-automations="1"' )
        ->assertSee( '1 automation runs when a card moves into Shipped' )
        ->assertSeeHtml( 'data-ec-kb-automations' )
        ->assertSeeHtml( 'ec-kb-edit-automations' );

    KanbanAutomation::query()->update( [ 'is_active' => false ] );

    $component->dispatch( 'ec-kb-board-changed' )->assertDontSeeHtml( 'data-ec-kb-column-automations=' );
} );

it( 'mounts the panel on the board for viewers who cannot edit', function (): void {
    [ $board ] = automatedBoard();
    denyAbilities( [ 'kanbanBoard.update' ] );

    Livewire::test( Board::class, [ 'board' => $board ] )
        ->assertSeeLivewire( AutomationsPanel::class )
        ->assertSeeHtml( 'data-ec-kb-automations' );
} );
