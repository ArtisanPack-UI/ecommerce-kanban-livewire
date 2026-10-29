<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Contracts\KanbanAutomationTrigger;
use ArtisanPackUI\Ecommerce\Models\KanbanAutomation;
use ArtisanPackUI\Ecommerce\Models\Order;
use ArtisanPackUI\Ecommerce\Registries\KanbanAutomationRegistry;
use ArtisanPackUI\EcommerceKanbanLivewire\Livewire\Board;
use Livewire\Livewire;

beforeEach( function (): void {
    grantAbilities( [ 'kanbanBoard.viewAny', 'kanbanBoard.view', 'kanbanCard.move' ] );
    $this->actingAs( makeUser() );

    $triggers = app( KanbanAutomationRegistry::class );

    $triggers->register( 'test-label', new class() implements KanbanAutomationTrigger {
        public function key(): string
        {
            return 'test-label';
        }

        public function label(): string
        {
            return 'Buy a <b>label</b>';
        }

        public function fire( Order $order, KanbanAutomation $automation, array $config ): void
        {
        }
    } );

    $triggers->register( 'test-broken', new class() implements KanbanAutomationTrigger {
        public function key(): string
        {
            return 'test-broken';
        }

        public function label(): string
        {
            return 'Notify the carrier';
        }

        public function fire( Order $order, KanbanAutomation $automation, array $config ): void
        {
            throw new RuntimeException( 'Carrier is down.' );
        }
    } );
} );

/**
 * Packing → Shipped with order F-1 in Packing.
 *
 * @return array{0: ArtisanPackUI\Ecommerce\Models\KanbanBoard, 1: Illuminate\Support\Collection, 2: Order}
 */
function feedbackBoard( string $number = 'F-1' ): array
{
    [ $board, $columns ] = makeBoard( [ [ 'cards' => 0, 'label_override' => 'Packing' ], [ 'cards' => 0, 'label_override' => 'Shipped' ] ] );

    foreach ( $columns as $column ) {
        $column->substatus->update( [ 'system_status' => 'processing' ] );
    }

    $order = addCard( $columns[0], [ 'order_number' => $number, 'system_status' => 'processing' ] )->order;

    return [ $board, $columns, $order ];
}

/**
 * An automation on `$board` into `$to`.
 */
function automationInto( $board, $to, string $trigger ): KanbanAutomation
{
    return KanbanAutomation::factory()->create( [ 'board_id' => $board->id, 'to_column_id' => $to->id, 'trigger_key' => $trigger, 'trigger_config' => [] ] );
}

/**
 * The toasts in a test response, as JSON.
 */
function toasts( $component ): string
{
    return collect( $component->effects['xjs'] ?? [] )->flatten()->implode( ' ' );
}

it( 'toasts the automations a move ran, in one toast', function (): void {
    [ $board, $columns, $order ] = feedbackBoard();
    automationInto( $board, $columns[1], 'test-label' );
    automationInto( $board, $columns[1], 'test-label' );

    $component = Livewire::test( Board::class, [ 'board' => $board ] )->call( 'loadBoard', [] );
    $moved     = $component->call( 'moveCard', $order->id, $columns[1]->id );
    $json      = toasts( $moved );

    expect( $moved->effects['returns'][0] )->toBe( 'moved' )
        ->and( $json )->toContain( '2 automations ran for order F-1' )
        ->and( $json )->toContain( trim( json_encode( e( 'Buy a <b>label</b>, Buy a <b>label</b>' ) ), '"' ) )
        ->and( $json )->not->toContain( 'Buy a <b>label</b>' )
        ->and( substr_count( $json, 'toast(' ) )->toBe( 1 );
} );

it( 'names the failed trigger and links to the order', function (): void {
    [ $board, $columns, $order ] = feedbackBoard();
    automationInto( $board, $columns[1], 'test-label' );
    automationInto( $board, $columns[1], 'test-broken' );

    $json = toasts( Livewire::test( Board::class, [ 'board' => $board ] )->call( 'loadBoard', [] )->call( 'moveCard', $order->id, $columns[1]->id ) );

    expect( $json )->toContain( '1 automation failed for order F-1' )
        ->and( $json )->toContain( 'Failed: Notify the carrier.' )
        ->and( $json )->toContain( '1 other automation ran.' )
        ->and( $json )->toContain( 'Open order F-1' )
        ->and( $json )->toContain( 'card=' . $order->id )
        ->and( $json )->toContain( 'alert-error' )
        ->and( substr_count( $json, 'toast(' ) )->toBe( 1 );
} );

it( 'shows nothing when no automation runs', function (): void {
    [ $board, $columns, $order ] = feedbackBoard();
    automationInto( $board, $columns[0], 'test-label' );

    $json = toasts( Livewire::test( Board::class, [ 'board' => $board ] )->call( 'loadBoard', [] )->call( 'moveCard', $order->id, $columns[1]->id ) );

    expect( $json )->not->toContain( 'automation' );
} );

it( 'only toasts automations from this move', function (): void {
    [ $board, $columns, $order ] = feedbackBoard();
    automationInto( $board, $columns[1], 'test-broken' );

    $component = Livewire::test( Board::class, [ 'board' => $board ] )->call( 'loadBoard', [] )->call( 'moveCard', $order->id, $columns[1]->id );

    KanbanAutomation::query()->delete();

    $json = toasts( $component->call( 'moveCard', $order->id, $columns[0]->id ) );

    expect( $json )->not->toContain( 'automation' );
} );

it( 'leaves feedback to the broadcasts while the page is live', function (): void {
    [ $board, $columns, $order ] = feedbackBoard();
    automationInto( $board, $columns[1], 'test-label' );

    $json = toasts( Livewire::test( Board::class, [ 'board' => $board ] )->set( 'liveFeedback', true )->call( 'loadBoard', [] )->call( 'moveCard', $order->id, $columns[1]->id ) );

    expect( $json )->not->toContain( 'automation' );
} );

it( 'toasts broadcast automation results for a card the actor moved', function (): void {
    [ $board, $columns, $order ] = feedbackBoard();
    $label                       = automationInto( $board, $columns[1], 'test-label' );
    $broken                      = automationInto( $board, $columns[1], 'test-broken' );

    $result = Livewire::test( Board::class, [ 'board' => $board ] )->call( 'automationFeedback', $order->id, [ $label->id ], [ $broken->id, 'x' ] );
    $json   = toasts( $result );

    expect( $result->effects['html'] ?? null )->toBeNull()
        ->and( $json )->toContain( '1 automation failed for order F-1' )
        ->and( $json )->toContain( 'Failed: Notify the carrier.' )
        ->and( $json )->toContain( '1 other automation ran.' );

    expect( toasts( Livewire::test( Board::class, [ 'board' => $board ] )->call( 'automationFeedback', $order->id, [ $label->id ] ) ) )
        ->toContain( '1 automation ran for order F-1' );
} );

it( 'ignores broadcast results for other boards and orders', function (): void {
    [ $board, , $order ]            = feedbackBoard();
    [ $other, $otherColumns, $far ] = feedbackBoard( 'F-2' );
    $foreign                        = automationInto( $other, $otherColumns[1], 'test-label' );

    expect( toasts( Livewire::test( Board::class, [ 'board' => $board ] )->call( 'automationFeedback', $order->id, [ $foreign->id ] ) ) )->not->toContain( 'automation' )
        ->and( toasts( Livewire::test( Board::class, [ 'board' => $board ] )->call( 'automationFeedback', $far->id, [ $foreign->id ] ) ) )->not->toContain( 'automation' )
        ->and( toasts( Livewire::test( Board::class, [ 'board' => $board ] )->call( 'automationFeedback', 999999, [ $foreign->id ] ) ) )->not->toContain( 'automation' )
        ->and( toasts( Livewire::test( Board::class, [ 'board' => $board ] )->call( 'automationFeedback', $order->id ) ) )->not->toContain( 'automation' );
} );

it( 'needs kanbanBoard.view for broadcast feedback', function (): void {
    [ $board, , $order ] = feedbackBoard();
    $component           = Livewire::test( Board::class, [ 'board' => $board ] );

    denyAbilities( [ 'kanbanBoard.view' ] );

    $component->call( 'automationFeedback', $order->id, [ 1 ] )->assertForbidden();
} );

it( 'marks the moved card for the page', function (): void {
    [ $board, $columns, $order ] = feedbackBoard();

    Livewire::test( Board::class, [ 'board' => $board ] )
        ->call( 'loadBoard', [] )
        ->call( 'moveCard', $order->id, $columns[1]->id )
        ->assertDispatched( 'ec-kb-card-moved', orderId: $order->id );
} );

it( 'passes the live state and feedback window to the page', function (): void {
    [ $board ] = feedbackBoard();

    Livewire::test( Board::class, [ 'board' => $board ] )
        ->assertSeeHtml( 'liveFeedback' )
        ->assertSeeHtml( 'automationFeedback(' )
        ->assertSeeHtml( 'feedbackWindow' );
} );
