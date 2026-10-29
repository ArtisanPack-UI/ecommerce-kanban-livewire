<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Events\KanbanBoardConfigChanged;
use ArtisanPackUI\Ecommerce\Models\KanbanColumn;
use ArtisanPackUI\Ecommerce\Models\OrderSubstatus;
use ArtisanPackUI\Ecommerce\Registries\SatelliteRegistry;
use ArtisanPackUI\EcommerceKanbanLivewire\Livewire\Board;
use ArtisanPackUI\EcommerceKanbanLivewire\Support\AdminLinks;
use ArtisanPackUI\EcommerceKanbanLivewire\Support\LayoutResolver;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;

beforeEach( function (): void {
    grantAbilities( [ 'kanbanBoard.viewAny', 'kanbanBoard.view', 'kanbanBoard.update', 'kanbanCard.move' ] );
    $this->actingAs( makeUser() );
} );

/**
 * A board with columns A, B, and C (processing).
 *
 * @return array{0: ArtisanPackUI\Ecommerce\Models\KanbanBoard, 1: Illuminate\Support\Collection<int, KanbanColumn>}
 */
function editableBoard( array $cards = [ 0, 0, 0 ] ): array
{
    [ $board, $columns ] = makeBoard( array_map( static fn ( int $count, string $label ): array => [ 'cards' => $count, 'label_override' => $label ], $cards, array_slice( [ 'A', 'B', 'C' ], 0, count( $cards ) ) ) );

    foreach ( $columns as $column ) {
        $column->substatus->update( [ 'system_status' => 'processing' ] );
    }

    return [ $board, $columns ];
}

/**
 * The board's column labels, left to right.
 *
 * @return array<int, string>
 */
function columnOrder( $board ): array
{
    return $board->columns()->get()->map( fn ( KanbanColumn $column ): string => (string) $column->label_override )->all();
}

it( 'turns edit mode on and off with kanbanBoard.update', function (): void {
    [ $board ] = editableBoard();

    Livewire::test( Board::class, [ 'board' => $board ] )
        ->call( 'loadBoard', [] )
        ->call( 'startEditing' )
        ->assertSet( 'editing', true )
        ->assertSeeHtml( 'data-ec-kb-column-tools' )
        ->assertSeeHtml( 'data-drag-group="columns"' )
        ->assertSeeHtml( 'data-ec-kb-add-column' )
        ->assertDontSeeHtml( 'data-drag-group="cards"' )
        ->call( 'stopEditing' )
        ->assertSet( 'editing', false )
        ->assertDontSeeHtml( 'data-ec-kb-column-tools' );

    denyAbilities( [ 'kanbanBoard.update' ] );

    Livewire::test( Board::class, [ 'board' => $board ] )->call( 'startEditing' )->assertForbidden();
    Livewire::test( Board::class, [ 'board' => $board ] )->call( 'reorderColumns', [] )->assertForbidden();
    Livewire::test( Board::class, [ 'board' => $board ] )->call( 'editColumn', 1 )->assertForbidden();
} );

it( 'reorders columns from a header drop and announces the change', function (): void {
    Event::fake( [ KanbanBoardConfigChanged::class ] );
    [ $board, $columns ] = editableBoard();

    $component = Livewire::test( Board::class, [ 'board' => $board ] )
        ->call( 'startEditing' )
        ->call( 'reorderColumns', [ $columns[2]->id, $columns[0]->id, $columns[1]->id ] );

    expect( $component->effects['returns'][0] )->toBe( 'moved' )
        ->and( columnOrder( $board ) )->toBe( [ 'C', 'A', 'B' ] );
    $component->assertSeeInOrder( [ 'C', 'A', 'B' ] );
    Event::assertDispatched( KanbanBoardConfigChanged::class, fn ( KanbanBoardConfigChanged $event ): bool => KanbanBoardConfigChanged::COLUMNS_REORDERED === $event->change );
} );

it( 'rejects a stale column order without rendering, so the client reverts', function (): void {
    [ $board, $columns ] = editableBoard();

    $component = Livewire::test( Board::class, [ 'board' => $board ] )
        ->call( 'startEditing' )
        ->call( 'reorderColumns', [ $columns[1]->id, $columns[0]->id ] );

    expect( $component->effects['returns'][0] )->toBe( 'rejected' )
        ->and( $component->effects['html'] ?? null )->toBeNull()
        ->and( columnOrder( $board ) )->toBe( [ 'A', 'B', 'C' ] );
} );

it( 'moves a column left and right, and not past the ends', function (): void {
    [ $board, $columns ] = editableBoard();

    Livewire::test( Board::class, [ 'board' => $board ] )
        ->call( 'startEditing' )
        ->call( 'moveColumn', $columns[0]->id, 1 )
        ->assertDispatched( 'ec-kb-focus-column', columnId: $columns[0]->id, direction: 'right' )
        ->call( 'moveColumn', $columns[2]->id, -1 )
        ->call( 'moveColumn', $columns[1]->id, -1 );

    expect( columnOrder( $board ) )->toBe( [ 'B', 'C', 'A' ] );

    Livewire::test( Board::class, [ 'board' => $board ] )->call( 'startEditing' )->call( 'moveColumn', $columns[1]->id, -1 );

    expect( columnOrder( $board ) )->toBe( [ 'B', 'C', 'A' ] );
} );

it( 'edits a column inline and shows the engine\'s validation messages', function (): void {
    [ $board, $columns ] = editableBoard();

    Livewire::test( Board::class, [ 'board' => $board ] )
        ->call( 'startEditing' )
        ->call( 'editColumn', $columns[0]->id )
        ->assertSet( 'columnDialog', true )
        ->assertSet( 'columnForm.label_override', 'A' )
        ->set( 'columnForm.color_override', 'green' )
        ->set( 'columnForm.wip_limit', '0' )
        ->call( 'saveColumn' )
        ->assertHasErrors( [ 'columnForm.color_override', 'columnForm.wip_limit' ] )
        ->assertSet( 'columnDialog', true )
        ->set( 'columnForm.label_override', 'QC' )
        ->set( 'columnForm.color_override', '#10B981' )
        ->set( 'columnForm.icon_override', 'o-check' )
        ->set( 'columnForm.wip_limit', '10' )
        ->call( 'saveColumn' )
        ->assertHasNoErrors()
        ->assertSet( 'columnDialog', false )
        ->assertSee( 'QC' );

    $column = $columns[0]->fresh();

    expect( $column->label_override )->toBe( 'QC' )
        ->and( $column->color_override )->toBe( '#10B981' )
        ->and( $column->icon_override )->toBe( 'o-check' )
        ->and( $column->wip_limit )->toBe( 10 );

    Livewire::test( Board::class, [ 'board' => $board ] )
        ->call( 'startEditing' )
        ->call( 'editColumn', $columns[0]->id )
        ->set( 'columnForm.label_override', '' )
        ->set( 'columnForm.wip_limit', '' )
        ->call( 'saveColumn' );

    expect( $columns[0]->fresh()->label_override )->toBeNull()->and( $columns[0]->fresh()->wip_limit )->toBeNull();
} );

it( 'ignores a column from another board', function (): void {
    [ $board ]           = editableBoard();
    [ , $otherColumns ]  = editableBoard();

    Livewire::test( Board::class, [ 'board' => $board ] )
        ->call( 'startEditing' )
        ->call( 'editColumn', $otherColumns[0]->id )
        ->assertSet( 'columnDialog', false )
        ->call( 'confirmDeleteColumn', $otherColumns[0]->id )
        ->assertSet( 'confirmingDeleteColumn', false );
} );

it( 'adds a column for a sub-status not on the board, grouped by system status', function (): void {
    [ $board, $columns ] = editableBoard();
    $qc                  = OrderSubstatus::factory()->create( [ 'system_status' => 'processing', 'label' => 'Quality check' ] );
    $hold                = OrderSubstatus::factory()->create( [ 'system_status' => 'on_hold', 'label' => 'Waiting on customer' ] );

    $component = Livewire::test( Board::class, [ 'board' => $board ] )
        ->call( 'startEditing' )
        ->call( 'openAddColumn' )
        ->assertSet( 'addColumnDialog', true );

    $options = $component->viewData( 'substatusOptions' );

    expect( collect( $options )->flatten( 1 )->pluck( 'id' )->all() )->toContain( $qc->id, $hold->id )
        ->not->toContain( $columns[0]->substatus_id )
        ->and( count( $options ) )->toBeGreaterThanOrEqual( 2 );

    $component->call( 'addColumn' )
        ->assertHasErrors( [ 'newSubstatusId' => 'required' ] )
        ->set( 'newSubstatusId', $columns[0]->substatus_id )
        ->call( 'addColumn' )
        ->assertHasErrors( 'newSubstatusId' )
        ->set( 'newSubstatusId', $qc->id )
        ->call( 'addColumn' )
        ->assertHasNoErrors()
        ->assertSet( 'addColumnDialog', false )
        ->assertSee( 'Quality check' );

    expect( $board->columns()->pluck( 'substatus_id' )->last() )->toBe( $qc->id );
} );

it( 'removes an empty column once per token', function (): void {
    [ $board, $columns ] = editableBoard();

    $component = Livewire::test( Board::class, [ 'board' => $board ] )
        ->call( 'loadBoard', [] )
        ->call( 'startEditing' )
        ->call( 'confirmDeleteColumn', $columns[2]->id )
        ->assertSet( 'confirmingDeleteColumn', true )
        ->assertSee( 'Remove column C?' );

    $token = $component->viewData( 'deletingColumn' )['token'];

    $component->call( 'deleteColumn', $token )->assertSet( 'confirmingDeleteColumn', false );

    expect( KanbanColumn::query()->find( $columns[2]->id ) )->toBeNull();
} );

it( 'refuses to remove a column with cards until a destination is chosen', function (): void {
    [ $board, $columns ] = editableBoard( [ 0, 2, 0 ] );

    $component = Livewire::test( Board::class, [ 'board' => $board ] )
        ->call( 'loadBoard', [] )
        ->call( 'startEditing' )
        ->call( 'confirmDeleteColumn', $columns[1]->id )
        ->assertSee( 'This column has 2 cards.' );

    $token = $component->viewData( 'deletingColumn' )['token'];

    $component->call( 'deleteColumn', $token )
        ->assertHasErrors( 'deleteMoveTo' )
        ->assertSet( 'confirmingDeleteColumn', true );

    expect( KanbanColumn::query()->find( $columns[1]->id ) )->not->toBeNull();

    // The refusal released the token, so the same confirmation can retry.
    $component->set( 'deleteMoveTo', $columns[0]->id )->call( 'deleteColumn', $token )->assertHasNoErrors();

    expect( KanbanColumn::query()->find( $columns[1]->id ) )->toBeNull()
        ->and( $columns[0]->cardCount() )->toBe( 2 );
} );

it( 'links "Advanced settings" to the admin board screen when the admin is installed', function (): void {
    [ $board ] = editableBoard();

    Livewire::test( Board::class, [ 'board' => $board ] )->call( 'startEditing' )->assertDontSeeHtml( 'data-ec-kb-advanced-settings' );

    Route::get( '/admin/kanban-boards/{board}/edit', fn () => 'ok' )->name( AdminLinks::BOARD_EDIT_ROUTE );
    app( 'router' )->getRoutes()->refreshNameLookups();
    $this->partialMock( SatelliteRegistry::class, function ( $mock ): void {
        $mock->shouldReceive( 'has' )->with( LayoutResolver::ADMIN_PACKAGE )->andReturnTrue();
        $mock->shouldReceive( 'isActive' )->with( LayoutResolver::ADMIN_PACKAGE )->andReturnTrue();
    } );

    Livewire::test( Board::class, [ 'board' => $board ] )
        ->call( 'startEditing' )
        ->assertSeeHtml( 'data-ec-kb-advanced-settings' )
        ->assertSeeHtml( '/admin/kanban-boards/' . $board->id . '/edit' );
} );

it( 'compiles every component in the board, its dialogs, and the widget picker', function (): void {
    [ $board, $columns ] = editableBoard( [ 1, 0, 0 ] );

    $boardHtml = Livewire::test( Board::class, [ 'board' => $board ] )
        ->call( 'loadBoard', [] )
        ->call( 'openAddOrder' )
        ->set( 'addOrderSearch', 'x' )
        ->call( 'startEditing' )
        ->call( 'editColumn', $columns[0]->id )
        ->call( 'openAddColumn' )
        ->call( 'confirmDeleteColumn', $columns[0]->id )
        ->html();

    $pickerHtml = Livewire::test( ArtisanPackUI\EcommerceKanbanLivewire\Livewire\WidgetPicker::class, [ 'boardId' => $board->id ] )
        ->call( 'openFor', $columns[0]->id )
        ->set( 'entries', [ [ 'key' => 'tags', 'config' => [] ] ] )
        ->html();

    expect( $boardHtml )->not->toContain( '<x-artisanpack' )->not->toContain( '<x-slot' )
        ->and( $pickerHtml )->not->toContain( '<x-artisanpack' )->not->toContain( '<x-slot' );
} );

it( 'edits the color with the color picker', function (): void {
    [ $board, $columns ] = editableBoard();

    Livewire::test( Board::class, [ 'board' => $board ] )
        ->call( 'startEditing' )
        ->call( 'editColumn', $columns[0]->id )
        ->assertSee( 'Color' )
        ->assertSeeHtml( 'type="color"' )
        ->set( 'columnForm.color_override', '#10b981' )
        ->call( 'saveColumn' )
        ->assertHasNoErrors();

    expect( $columns[0]->fresh()->color_override )->toBe( '#10b981' );
} );

it( 'needs kanbanCard.move to move cards out of a removed column, and ignores another board\'s column', function (): void {
    [ $board, $columns ] = editableBoard( [ 0, 1, 0 ] );
    [ , $otherColumns ]  = editableBoard();

    $component = Livewire::test( Board::class, [ 'board' => $board ] )
        ->call( 'startEditing' )
        ->call( 'confirmDeleteColumn', $columns[1]->id );
    $token = $component->viewData( 'deletingColumn' )['token'];

    $component->set( 'deleteMoveTo', $otherColumns[0]->id )->call( 'deleteColumn', $token )->assertHasErrors( 'deleteMoveTo' );
    expect( KanbanColumn::query()->find( $columns[1]->id ) )->not->toBeNull();

    denyAbilities( [ 'kanbanCard.move' ] );
    $component->set( 'deleteMoveTo', $columns[0]->id )->call( 'deleteColumn', $token )->assertForbidden();
    expect( KanbanColumn::query()->find( $columns[1]->id ) )->not->toBeNull();
} );

it( 'shows why a column can\'t be removed when its cards can\'t move to the chosen column', function (): void {
    [ $board, $columns ] = makeBoard( [ [ 'cards' => 2, 'label_override' => 'A' ], [ 'cards' => 0, 'label_override' => 'B' ], [ 'cards' => 0, 'label_override' => 'C' ] ] );
    $columns[0]->substatus->update( [ 'system_status' => 'pending' ] );
    $columns[1]->substatus->update( [ 'system_status' => 'refunded' ] );
    $columns[2]->substatus->update( [ 'system_status' => 'pending' ] );

    $component = Livewire::test( Board::class, [ 'board' => $board ] )
        ->call( 'loadBoard', [] )
        ->call( 'startEditing' )
        ->call( 'confirmDeleteColumn', $columns[0]->id );

    $token = $component->viewData( 'deletingColumn' )['token'];

    // Pending orders can't go straight to refunded: the engine refuses, and the dialog says why.
    $component->set( 'deleteMoveTo', $columns[1]->id )
        ->set( 'confirmDeleteExit', true )
        ->call( 'deleteColumn', $token )
        ->assertHasErrors( 'deleteMoveTo' )
        ->assertSet( 'confirmingDeleteColumn', true );

    expect( KanbanColumn::query()->find( $columns[0]->id ) )->not->toBeNull()
        ->and( $columns[0]->cardCount() )->toBe( 2 );

    // The refusal released the token, so a compatible column works with it.
    $component->set( 'deleteMoveTo', $columns[2]->id )->call( 'deleteColumn', $token )->assertHasNoErrors();

    expect( KanbanColumn::query()->find( $columns[0]->id ) )->toBeNull()
        ->and( $columns[2]->cardCount() )->toBe( 2 );
} );

it( 'asks before removing a column moves its cards into an exit column', function (): void {
    [ $board, $columns ] = editableBoard( [ 2, 0 ] );
    $columns[1]->substatus->update( [ 'system_status' => 'cancelled' ] );

    $component = Livewire::test( Board::class, [ 'board' => $board ] )
        ->call( 'loadBoard', [] )
        ->call( 'startEditing' )
        ->call( 'confirmDeleteColumn', $columns[0]->id )
        ->set( 'deleteMoveTo', $columns[1]->id )
        ->assertSeeHtml( 'data-ec-kb-delete-column-exit' )
        ->assertSee( 'changes each order' );

    $token = $component->viewData( 'deletingColumn' )['token'];

    $component->call( 'deleteColumn', $token )->assertHasErrors( 'confirmDeleteExit' );

    expect( $columns[0]->cardCount() )->toBe( 2 );

    // Choosing another column asks again.
    $component->set( 'confirmDeleteExit', true )->set( 'deleteMoveTo', $columns[1]->id )->assertSet( 'confirmDeleteExit', false );

    $component->set( 'confirmDeleteExit', true )->call( 'deleteColumn', $token )->assertHasNoErrors();

    expect( KanbanColumn::query()->find( $columns[0]->id ) )->toBeNull()
        ->and( $columns[1]->cardCount() )->toBe( 2 );
} );

it( 'needs no extra confirmation to move a removed column\'s cards into a non-exit column', function (): void {
    [ $board, $columns ] = editableBoard( [ 1, 0 ] );

    $component = Livewire::test( Board::class, [ 'board' => $board ] )
        ->call( 'loadBoard', [] )
        ->call( 'startEditing' )
        ->call( 'confirmDeleteColumn', $columns[0]->id )
        ->set( 'deleteMoveTo', $columns[1]->id )
        ->assertDontSeeHtml( 'data-ec-kb-delete-column-exit' );

    $component->call( 'deleteColumn', $component->viewData( 'deletingColumn' )['token'] )->assertHasNoErrors();

    expect( KanbanColumn::query()->find( $columns[0]->id ) )->toBeNull();
} );

it( 'hands focus back when an edit-mode dialog is dismissed', function (): void {
    [ $board, $columns ] = editableBoard();
    $id                  = $columns[1]->id;

    Livewire::test( Board::class, [ 'board' => $board ] )
        ->call( 'loadBoard', [] )
        ->call( 'startEditing' )
        ->call( 'editColumn', $id )
        ->call( 'closeColumnDialog' )
        ->assertSet( 'columnDialog', false )
        ->assertSet( 'editingColumnId', null )
        ->assertDispatched( 'ec-kb-focus', selector: "[data-ec-kb-column='{$id}'] [data-ec-kb-edit-column]" )
        ->call( 'openAddColumn' )
        ->call( 'cancelAddColumn' )
        ->assertSet( 'addColumnDialog', false )
        ->assertDispatched( 'ec-kb-focus', selector: '[data-ec-kb-add-column]' )
        ->call( 'confirmDeleteColumn', $id )
        ->call( 'cancelDeleteColumn' )
        ->assertSet( 'deletingColumnId', null )
        ->assertDispatched( 'ec-kb-focus', selector: "[data-ec-kb-column='{$id}'] [data-ec-kb-delete-column]" );
} );

it( 'runs a dialog\'s cancel action when the browser closes it', function (): void {
    [ $board ] = editableBoard();

    $html = Livewire::test( Board::class, [ 'board' => $board ] )->html();

    foreach ( [ 'closeMoveDialog', 'cancelMove', 'cancelAddOrder', 'cancelRemoveCard', 'closeColumnDialog', 'cancelAddColumn', 'cancelDeleteColumn' ] as $action ) {
        expect( $html )->toContain( "'" . $action . "'" );
    }
} );
