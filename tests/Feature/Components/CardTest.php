<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Kanban\Widgets\AbstractKanbanCardWidget;
use ArtisanPackUI\Ecommerce\Models\KanbanColumn;
use ArtisanPackUI\Ecommerce\Models\Order;
use ArtisanPackUI\Ecommerce\Registries\KanbanCardWidgetRegistry;
use ArtisanPackUI\EcommerceKanbanLivewire\Livewire\Board;
use ArtisanPackUI\EcommerceKanbanLivewire\Support\CardActions;
use ArtisanPackUI\EcommerceKanbanLivewire\Support\WidgetViews;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

/**
 * Registers a widget whose payload is `$payload` (or that throws when it is null).
 *
 * @param  array<string, mixed>|null  $payload  What `render()` returns.
 */
function fakeWidget( string $key, ?array $payload ): void
{
    app( KanbanCardWidgetRegistry::class )->register( $key, new class( $key, $payload ) extends AbstractKanbanCardWidget {
        public function __construct( private string $widgetKey, private ?array $payload )
        {
        }

        public function key(): string
        {
            return $this->widgetKey;
        }

        public function label(): string
        {
            return ucfirst( $this->widgetKey );
        }

        public function render( Order $order, KanbanColumn $column ): array
        {
            return $this->payload ?? throw new RuntimeException( 'Widget failed.' );
        }
    } );
}

/**
 * A loaded board with one card using `$widgets`.
 *
 * @param  array<int, string>    $widgets  Widget keys for the column.
 * @param  array<string, mixed>  $order    Order attributes.
 */
function cardBoard( array $widgets, array $order = [] ): \Livewire\Features\SupportTesting\Testable
{
    [ $board, $columns ] = makeBoard( [ [ 'cards' => 0, 'card_widgets' => $widgets ] ] );
    addCard( $columns[0], $order + [ 'order_number' => '1042' ] );

    return Livewire::test( Board::class, [ 'board' => $board ] )->call( 'loadBoard', [] );
}

beforeEach( function (): void {
    grantAbilities( [ 'kanbanBoard.viewAny', 'kanbanBoard.view' ] );
    $this->actingAs( makeUser() );
} );

it( 'shows the order number, customer, placed date, and widgets in order', function (): void {
    fakeWidget( 'first', [ 'value' => 'Alpha' ] );
    fakeWidget( 'second', [ 'value' => 'Beta' ] );

    cardBoard( [ 'second', 'first' ], [
        'billing_address' => [ 'first_name' => 'Jane', 'last_name' => 'Doe' ],
        'placed_at'       => Carbon::parse( '2026-03-14 12:00:00', 'UTC' ),
    ] )
        ->assertSee( 'Order 1042' )
        ->assertSee( 'Jane Doe' )
        ->assertSee( 'March 14, 2026' )
        ->assertSeeHtml( 'datetime="2026-03-14T12:00:00' )
        ->assertSeeInOrder( [ 'Beta', 'Alpha' ] );
} );

it( 'names the card with a summary and makes it focusable', function (): void {
    fakeWidget( 'total-ish', [ 'value' => '$124.00' ] );
    fakeWidget( 'items-ish', [ 'value' => '3 items' ] );

    cardBoard( [ 'items-ish', 'total-ish' ], [ 'billing_address' => [ 'first_name' => 'Jane', 'last_name' => 'Doe' ] ] )
        ->assertSeeHtml( 'aria-label="Order 1042, Jane Doe, 3 items, $124.00"' )
        ->assertSeeHtml( 'tabindex="0"' );
} );

it( 'falls back to the order email for the customer and does not repeat the customer widget', function (): void {
    $component = cardBoard( [ 'customer' ], [ 'email' => 'jane@example.test' ] );

    $component->assertSee( 'jane@example.test' );

    expect( substr_count( $component->html(), 'jane@example.test' ) )->toBe( 2 ); // the name line and the accessible name
} );

it( 'maps tones to badge colours with an icon and text, never colour alone', function ( string $tone, string $class, ?string $text ): void {
    fakeWidget( 'toned', [ 'value' => '2 days', 'tone' => $tone ] );

    $component = cardBoard( [ 'toned' ] )
        ->assertSeeHtml( $class )
        ->assertSeeHtml( 'data-ec-kb-tone="' . $tone . '"' );

    null === $text
        ? expect( $component->assertDontSeeHtml( 'data-ec-kb-tone-icon' )->html() )->not->toMatch( '/badge-neutral[^>]*data-ec-kb-widget=/' )
        : $component->assertSeeHtml( 'data-ec-kb-tone-icon' )->assertSee( '(' . $text . ')' );
} )->with( [
    'neutral' => [ 'neutral', 'badge-outline', null ],
    'success' => [ 'success', 'badge-success', 'Good' ],
    'warning' => [ 'warning', 'badge-warning', 'Needs attention' ],
    'danger'  => [ 'danger', 'badge-error', 'Problem' ],
    'info'    => [ 'info', 'badge-info', 'Note' ],
] );

it( 'renders the payload icon, tooltip, and link', function (): void {
    fakeWidget( 'shipping', [ 'value' => 'Express', 'icon' => 'truck', 'tooltip' => 'Ships today', 'href' => 'https://example.test/track' ] );

    cardBoard( [ 'shipping' ] )
        ->assertSeeHtml( 'href="https://example.test/track"' )
        ->assertSeeHtml( 'data-tip="Ships today"' )
        ->assertSee( '(Ships today)' )
        ->assertSee( 'Shipping:' )
        ->assertSeeHtml( '<svg' );
} );

it( 'renders a widget with an unsafe href as plain text', function (): void {
    fakeWidget( 'sneaky', [ 'value' => 'Click me', 'href' => 'javascript:alert(1)' ] );

    cardBoard( [ 'sneaky' ] )
        ->assertSee( 'Click me' )
        ->assertDontSeeHtml( 'javascript:alert(1)' );
} );

it( 'drops an icon that does not exist instead of breaking the card', function (): void {
    fakeWidget( 'odd', [ 'value' => 'Odd', 'icon' => 'no-such-icon-anywhere' ] );

    cardBoard( [ 'odd' ] )->assertOk()->assertSee( 'Odd' );
} );

it( 'renders nothing for a widget that throws in the engine and keeps the card', function (): void {
    fakeWidget( 'broken', null );
    fakeWidget( 'fine', [ 'value' => 'Still here' ] );

    cardBoard( [ 'broken', 'fine' ] )
        ->assertOk()
        ->assertSee( 'Order 1042' )
        ->assertSee( 'Still here' )
        ->assertDontSeeHtml( 'data-ec-kb-widget="broken"' );
} );

it( 'lets a satellite supply the view for a widget key', function (): void {
    useFixtureViews();
    fakeWidget( 'print-status', [ 'value' => 'Printing' ] );
    fakeWidget( 'other', [ 'value' => 'Generic' ] );
    addFilter( WidgetViews::FILTER, static fn ( ?string $view, string $key ): ?string => 'print-status' === $key ? 'host::widget' : $view );

    cardBoard( [ 'print-status', 'other' ] )
        ->assertSeeHtml( 'data-custom-widget="print-status"' )
        ->assertSee( 'Printing (custom)' )
        ->assertSeeHtml( 'data-ec-kb-widget="other"' );
} );

it( 'falls back to the generic view for a view that does not exist, and renders nothing for one that throws', function (): void {
    useFixtureViews();
    fakeWidget( 'missing-view', [ 'value' => 'Missing' ] );
    fakeWidget( 'broken-view', [ 'value' => 'Broken' ] );
    addFilter( WidgetViews::FILTER, static fn ( ?string $view, string $key ): ?string => match ( $key ) {
        'missing-view' => 'host::nope',
        'broken-view'  => 'host::broken-widget',
        default        => $view,
    } );

    cardBoard( [ 'missing-view', 'broken-view' ] )
        ->assertOk()
        ->assertSee( 'Order 1042' )
        ->assertSeeHtml( 'data-ec-kb-widget="missing-view"' )
        ->assertDontSeeHtml( 'data-ec-kb-widget="broken-view"' );
} );

it( 'offers Open, Move to…, and Remove from board to users who may act', function (): void {
    grantAbilities( [ 'kanbanCard.move', 'kanbanBoard.update' ] );

    $component = cardBoard( [] );
    $orderId   = Order::query()->value( 'id' );

    $component->assertSeeHtml( 'aria-label="Actions for order 1042"' )
        ->assertSeeInOrder( [ 'Open', 'Move to', 'Remove from board' ] )
        ->assertSee( 'Move to…' )
        ->assertSeeHtml( 'card=' . $orderId )
        ->assertSeeHtml( CardActions::MOVE_EVENT )
        ->assertSeeHtml( CardActions::REMOVE_EVENT );
} );

it( 'leaves out Move to… and Remove from board without the abilities', function (): void {
    cardBoard( [] )
        ->assertSeeHtml( 'data-ec-kb-action="open"' )
        ->assertDontSeeHtml( 'data-ec-kb-action="move"' )
        ->assertDontSeeHtml( 'data-ec-kb-action="remove"' );
} );

it( 'adds satellite card actions, checking their abilities and dropping malformed ones', function (): void {
    grantAbilities( [ 'order.update' ] );
    addFilter( CardActions::FILTER, static function ( array $actions ): array {
        $actions[] = [ 'key' => 'reprint', 'label' => 'Reprint label', 'icon' => 'printer', 'ability' => 'order.update', 'event' => 'labels.reprint' ];
        $actions[] = [ 'key' => 'invoice', 'label' => 'View invoice', 'url' => 'https://example.test/invoice' ];
        $actions[] = [ 'key' => 'secret', 'label' => 'Refund', 'ability' => 'order.refund', 'event' => 'orders.refund' ];
        $actions[] = [ 'key' => 'broken', 'label' => 'No target' ];
        $actions[] = [ 'key' => 'sneaky', 'label' => 'Sneaky link', 'url' => 'javascript:alert(1)' ];
        $actions[] = 'not an action';

        return $actions;
    } );

    cardBoard( [] )
        ->assertSee( 'Reprint label' )
        ->assertSeeHtml( 'labels.reprint' )
        ->assertSeeHtml( 'href="https://example.test/invoice"' )
        ->assertSeeHtml( 'x-on:click.stop' )
        ->assertDontSeeHtml( 'orders.refund' )
        ->assertDontSee( 'No target' )
        ->assertDontSee( 'Sneaky link' );
} );

it( 'has hooks for the moving and reverting states', function (): void {
    cardBoard( [] )
        ->assertSeeHtml( 'x-on:ec-kb-card-state.window' )
        ->assertSeeHtml( 'transition-[opacity,box-shadow]' )
        ->assertDontSeeHtml( 'bg-base-100 transition ' )
        ->assertSee( 'Moving…' )
        ->assertSee( 'Move undone' );
} );

it( 'renders the card as a focusable group that holds still under reduced motion', function (): void {
    $html = cardBoard( [] )->html();

    expect( $html )->toMatch( '/<div\s[^>]*tabindex="0"\s+role="group"\s+aria-label="Order 1042/' )
        ->and( $html )->not->toContain( '<article' )
        ->and( $html )->toContain( 'motion-reduce:transition-none' )
        ->and( $html )->toContain( 'animate-pulse motion-reduce:animate-none' );
} );

it( 'gives each column an explicit region role, so a draggable column never becomes a button', function (): void {
    [ $board ] = makeBoard( [ 0, 0 ] );

    $html = Livewire::test( Board::class, [ 'board' => $board ] )->call( 'loadBoard', [] )->html();

    expect( preg_match_all( '/<section\s[^>]*role="region"\s+aria-labelledby="ec-kb-column-\d+-title"/', $html ) )->toBe( 2 );
} );
