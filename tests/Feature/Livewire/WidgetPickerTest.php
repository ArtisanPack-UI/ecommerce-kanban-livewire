<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Events\KanbanBoardConfigChanged;
use ArtisanPackUI\Ecommerce\Models\KanbanCardWidget;
use ArtisanPackUI\EcommerceKanbanLivewire\Livewire\WidgetPicker;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;

beforeEach( function (): void {
    grantAbilities( [ 'kanbanBoard.viewAny', 'kanbanBoard.view', 'kanbanBoard.update' ] );
    $this->actingAs( makeUser() );
} );

it( 'opens for a column with its list and needs kanbanBoard.update', function (): void {
    [ $board, $columns ] = makeBoard( [ [ 'cards' => 0, 'card_widgets' => [ 'total', [ 'key' => 'tags', 'config' => [ 'max_tags' => 2 ] ] ] ] ] );

    Livewire::test( WidgetPicker::class, [ 'boardId' => $board->id ] )
        ->assertDontSeeHtml( 'data-ec-kb-widget-row' )
        ->dispatch( 'ec-kb-edit-widgets', columnId: $columns[0]->id )
        ->assertSet( 'open', true )
        ->assertSet( 'entries', [ [ 'key' => 'total', 'config' => [] ], [ 'key' => 'tags', 'config' => [ 'max_tags' => 2 ] ] ] )
        ->assertSeeHtml( 'data-ec-kb-widget-row="total"' )
        ->assertSeeHtml( 'data-ec-kb-widget-config="tags"' )
        ->assertSee( 'Provided by ecommerce' )
        ->assertSee( 'This column uses its own widgets.' );

    denyAbilities( [ 'kanbanBoard.update' ] );

    Livewire::test( WidgetPicker::class, [ 'boardId' => $board->id ] )->call( 'openFor', $columns[0]->id )->assertForbidden();
} );

it( 'ignores a column from another board', function (): void {
    [ $board ]          = makeBoard( [ 0 ] );
    [ , $otherColumns ] = makeBoard( [ 0 ] );

    Livewire::test( WidgetPicker::class, [ 'boardId' => $board->id ] )->call( 'openFor', $otherColumns[0]->id )->assertSet( 'open', false );
} );

it( 'adds, reorders, and removes widgets', function (): void {
    [ $board, $columns ] = makeBoard( [ 0 ] );

    $component = Livewire::test( WidgetPicker::class, [ 'boardId' => $board->id ] )
        ->call( 'openFor', $columns[0]->id )
        ->set( 'addKey', 'total' )->call( 'addWidget' )
        ->set( 'addKey', 'tags' )->call( 'addWidget' )
        ->set( 'addKey', 'tags' )->call( 'addWidget' )
        ->set( 'addKey', 'not-registered' )->call( 'addWidget' )
        ->set( 'addKey', 'days-in-column' )->call( 'addWidget' );

    expect( array_column( $component->get( 'entries' ), 'key' ) )->toBe( [ 'total', 'tags', 'days-in-column' ] );

    $component->call( 'moveWidget', 0, 1 )->call( 'moveWidget', 0, -1 );
    expect( array_column( $component->get( 'entries' ), 'key' ) )->toBe( [ 'tags', 'total', 'days-in-column' ] );

    $component->call( 'reorderWidgets', [ 2, 0, 1 ] );
    expect( array_column( $component->get( 'entries' ), 'key' ) )->toBe( [ 'days-in-column', 'tags', 'total' ] );

    $component->call( 'reorderWidgets', [ 0, 0, 1 ] );
    expect( array_column( $component->get( 'entries' ), 'key' ) )->toBe( [ 'days-in-column', 'tags', 'total' ] );

    $component->call( 'removeWidget', 1 );
    expect( array_column( $component->get( 'entries' ), 'key' ) )->toBe( [ 'days-in-column', 'total' ] );
} );

it( 'saves a column list with config, storing plain keys for widgets without settings', function (): void {
    Event::fake( [ KanbanBoardConfigChanged::class ] );
    [ $board, $columns ] = makeBoard( [ 0 ] );

    Livewire::test( WidgetPicker::class, [ 'boardId' => $board->id ] )
        ->call( 'openFor', $columns[0]->id )
        ->set( 'addKey', 'total' )->call( 'addWidget' )
        ->set( 'addKey', 'days-in-column' )->call( 'addWidget' )
        ->set( 'entries.1.config.stale_after_days', '1' )
        ->call( 'save' )
        ->assertHasNoErrors()
        ->assertSet( 'open', false )
        ->assertDispatched( 'ec-kb-board-changed' );

    expect( $columns[0]->fresh()->card_widgets )->toBe( [ 'total', [ 'key' => 'days-in-column', 'config' => [ 'stale_after_days' => 1 ] ] ] );
    Event::assertDispatched( KanbanBoardConfigChanged::class );
} );

it( 'shows the engine\'s validation messages on the field', function (): void {
    [ $board, $columns ] = makeBoard( [ 0 ] );

    Livewire::test( WidgetPicker::class, [ 'boardId' => $board->id ] )
        ->call( 'openFor', $columns[0]->id )
        ->set( 'addKey', 'tags' )->call( 'addWidget' )
        ->set( 'entries.0.config.max_tags', '0' )
        ->call( 'save' )
        ->assertHasErrors( 'entries.0.config.max_tags' )
        ->assertSet( 'open', true );

    expect( $columns[0]->fresh()->card_widgets )->toBe( [] );
} );

it( 'edits the board default and says which list applies', function (): void {
    [ $board, $columns ] = makeBoard( [ 0 ] );

    $component = Livewire::test( WidgetPicker::class, [ 'boardId' => $board->id ] )
        ->call( 'openFor', null )
        ->assertSet( 'columnId', null )
        ->assertSee( 'Default card widgets' )
        ->assertSee( 'No board default: columns without their own widgets use the store default.' )
        ->set( 'addKey', 'shipping-method' )->call( 'addWidget' )
        ->assertSee( 'Columns without their own widgets use this list.' )
        ->call( 'save' )
        ->assertHasNoErrors();

    expect( $board->fresh()->settings['card_widgets'] )->toBe( [ 'shipping-method' ] );

    Livewire::test( WidgetPicker::class, [ 'boardId' => $board->id ] )
        ->call( 'openFor', $columns[0]->id )
        ->assertSee( 'This column has no widgets of its own, so it uses the board default.' );
} );

it( 'lists widgets by their store label', function (): void {
    [ $board, $columns ] = makeBoard( [ 0 ] );
    KanbanCardWidget::query()->create( [ 'key' => 'total', 'label' => 'Order value', 'default_config' => [], 'provided_by' => 'ecommerce' ] );

    Livewire::test( WidgetPicker::class, [ 'boardId' => $board->id ] )
        ->call( 'openFor', $columns[0]->id )
        ->assertSee( 'Order value' );
} );

it( 'previews a card from the board with the unsaved list', function (): void {
    [ $board, $columns ] = makeBoard( [ 0 ] );
    addCard( $columns[0], [ 'order_number' => 'PV-1', 'meta' => [ 'tags' => [ 'rush', 'gift', 'vip' ] ] ] );

    $component = Livewire::test( WidgetPicker::class, [ 'boardId' => $board->id ] )
        ->call( 'openFor', $columns[0]->id )
        ->assertSee( 'Order PV-1' )
        ->set( 'addKey', 'tags' )->call( 'addWidget' )
        ->set( 'entries.0.config.max_tags', '1' );

    $component->assertSee( 'rush +2' );

    expect( $columns[0]->fresh()->card_widgets )->toBe( [] );
} );

it( 'says when there is no card to preview', function (): void {
    [ $board, $columns ] = makeBoard( [ 0 ] );

    Livewire::test( WidgetPicker::class, [ 'boardId' => $board->id ] )
        ->call( 'openFor', $columns[0]->id )
        ->assertSee( 'Put an order on this board to preview its card.' );
} );

it( 'cleans a malformed list from the client', function (): void {
    [ $board, $columns ] = makeBoard( [ 0 ] );

    Livewire::test( WidgetPicker::class, [ 'boardId' => $board->id ] )
        ->call( 'openFor', $columns[0]->id )
        ->set( 'entries', [ 'oops', [ 'key' => 'total', 'config' => 'x' ], [ 'key' => 7 ], [ 'config' => [] ], [ 'key' => 'total' ] ] )
        ->assertOk()
        ->assertSet( 'entries', [ [ 'key' => 'oops', 'config' => [] ], [ 'key' => 'total', 'config' => [] ] ] )
        ->assertSee( 'This widget is no longer installed and will be skipped.' );
} );

it( 'skips the preview until the settings are valid', function (): void {
    [ $board, $columns ] = makeBoard( [ 0 ] );
    addCard( $columns[0], [ 'order_number' => 'PV-2' ] );

    Livewire::test( WidgetPicker::class, [ 'boardId' => $board->id ] )
        ->call( 'openFor', $columns[0]->id )
        ->set( 'addKey', 'tags' )->call( 'addWidget' )
        ->set( 'entries.0.config.max_tags', '0' )
        ->assertSeeHtml( 'data-ec-kb-widget-preview-invalid' )
        ->assertDontSee( 'Order PV-2' )
        ->set( 'entries.0.config.max_tags', '2' )
        ->assertSee( 'Order PV-2' );
} );

it( 'refuses to save a column that was deleted while the picker was open', function (): void {
    [ $board, $columns ] = makeBoard( [ 0 ] );

    $component = Livewire::test( WidgetPicker::class, [ 'boardId' => $board->id ] )
        ->call( 'openFor', $columns[0]->id )
        ->set( 'addKey', 'total' )->call( 'addWidget' );

    $columns[0]->delete();

    $component->call( 'save' )->assertSet( 'open', false );

    expect( $board->fresh()->settings['card_widgets'] ?? [] )->toBe( [] )
        ->and( json_encode( $component->effects['xjs'] ?? [] ) )->toContain( 'That column no longer exists' );
} );

it( 'keeps settings it has no control for', function (): void {
    app( ArtisanPackUI\Ecommerce\Registries\KanbanCardWidgetRegistry::class )->register( 'printful:status', new class () implements ArtisanPackUI\Ecommerce\Contracts\KanbanCardWidget {
        public function key(): string
        {
            return 'printful:status';
        }

        public function label(): string
        {
            return 'Print status';
        }

        public function render( ArtisanPackUI\Ecommerce\Models\Order $order, ArtisanPackUI\Ecommerce\Models\KanbanColumn $column ): array
        {
            return [ 'label' => 'Print status', 'value' => 'Queued' ];
        }

        public function refreshSubscription( ArtisanPackUI\Ecommerce\Models\Order $order ): ?string
        {
            return null;
        }
    } );

    [ $board, $columns ] = makeBoard( [ [ 'cards' => 0, 'card_widgets' => [ [ 'key' => 'printful:status', 'config' => [ 'show_eta' => false, 'region' => 'eu' ] ] ] ] ] );

    Livewire::test( WidgetPicker::class, [ 'boardId' => $board->id ] )
        ->call( 'openFor', $columns[0]->id )
        ->set( 'addKey', 'total' )->call( 'addWidget' )
        ->call( 'save' )
        ->assertHasNoErrors();

    expect( $columns[0]->fresh()->card_widgets )->toBe( [ [ 'key' => 'printful:status', 'config' => [ 'show_eta' => false, 'region' => 'eu' ] ], 'total' ] );
} );
