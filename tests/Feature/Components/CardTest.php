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

it( 'maps tones to soft, small design-system badges with an icon and text, never color alone', function ( string $tone, string $color, ?string $text ): void {
    fakeWidget( 'toned', [ 'value' => '2 days', 'tone' => $tone ] );

    $component = cardBoard( [ 'toned' ] );
    $html      = $component->html();

    expect( $html )->toMatch( '/<span[^>]*class="apui-badge"[^>]*data-ec-kb-widget="toned"[^>]*data-ec-kb-tone="' . $tone . '"/' )
        ->and( $html )->toMatch( '/data-ec-kb-tone="' . $tone . '"[^>]*data-variant="soft"\s+data-color="' . $color . '"\s+data-size="sm"/' );

    null === $text
        ? $component->assertDontSeeHtml( 'data-ec-kb-tone-icon' )
        : $component->assertSeeHtml( 'data-ec-kb-tone-icon' )->assertSee( '(' . $text . ')' );
} )->with( [
    'neutral' => [ 'neutral', 'neutral', null ],
    'success' => [ 'success', 'success', 'Good' ],
    'warning' => [ 'warning', 'warning', 'Needs attention' ],
    'danger'  => [ 'danger', 'error', 'Problem' ],
    'info'    => [ 'info', 'info', 'Note' ],
] );

it( 'renders the payload icon, tooltip, and link', function (): void {
    fakeWidget( 'shipping', [ 'value' => 'Express', 'icon' => 'truck', 'tooltip' => 'Ships today', 'href' => 'https://example.test/track' ] );

    cardBoard( [ 'shipping' ] )
        ->assertSeeHtml( 'href="https://example.test/track"' )
        ->assertSeeHtml( 'class="ec-kb-widget-link"' )
        ->assertSeeHtml( 'role="tooltip"' )
        ->assertSee( 'Ships today' )
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

    $html    = $component->html();
    $targets = html_entity_decode( $html );

    $component->assertSee( 'Actions for order 1042' )
        ->assertSeeInOrder( [ 'Open', 'Move to', 'Remove from board' ] )
        ->assertSee( 'Move to…' );

    expect( $targets )->toContain( 'card=' . $orderId )
        ->and( $targets )->toContain( CardActions::MOVE_EVENT )
        ->and( $targets )->toContain( CardActions::REMOVE_EVENT )
        ->and( $html )->toContain( 'ec-kb-card-actions-trigger' )
        ->and( $html )->toMatch( '/role="separator".*data-ec-kb-action="remove"/s' )
        ->and( $html )->toMatch( '/data-ec-kb-action="remove"[^>]*data-color="error"/s' );
} );

it( 'leaves out Move to… and Remove from board without the abilities', function (): void {
    cardBoard( [] )
        ->assertSeeHtml( 'data-ec-kb-action="open"' )
        ->assertDontSeeHtml( 'data-ec-kb-action="move"' )
        ->assertDontSeeHtml( 'data-ec-kb-action="remove"' );
} );

it( 'keeps the built-in actions when a satellite action reuses their keys', function (): void {
    grantAbilities( [ 'kanbanCard.move', 'kanbanBoard.update' ] );
    addFilter( CardActions::FILTER, static function ( array $actions ): array {
        array_unshift( $actions, [ 'key' => 'remove', 'label' => 'Archive order', 'event' => 'archive.order' ] );
        $actions[] = [ 'key' => 'open', 'label' => 'Open elsewhere', 'url' => 'https://example.test/elsewhere' ];

        return $actions;
    } );

    $component = cardBoard( [] )
        ->assertSee( 'Remove from board' )
        ->assertDontSee( 'Archive order' )
        ->assertDontSee( 'Open elsewhere' );

    $html    = $component->html();
    $targets = html_entity_decode( $html );

    expect( substr_count( $html, 'data-ec-kb-action="remove"' ) )->toBe( 1 )
        ->and( substr_count( $html, 'data-ec-kb-action="open"' ) )->toBe( 1 )
        ->and( $targets )->toContain( CardActions::REMOVE_EVENT )
        ->and( $targets )->not->toContain( 'archive.order' )
        ->and( $targets )->not->toContain( 'example.test/elsewhere' );
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

    $component = cardBoard( [] )
        ->assertSee( 'Reprint label' )
        ->assertSee( 'View invoice' )
        ->assertDontSee( 'No target' )
        ->assertDontSee( 'Sneaky link' );

    $targets = html_entity_decode( $component->html() );

    expect( $targets )->toContain( 'labels.reprint' )
        ->and( $targets )->toContain( 'https://example.test/invoice' )
        ->and( $targets )->toContain( 'window.location.assign( target.url )' )
        ->and( $targets )->not->toContain( 'orders.refund' )
        ->and( $targets )->not->toContain( 'javascript:alert' );

    // Satellite actions follow the built-in ones; without Remove from board the menu has no separator.
    $menu = (string) strstr( (string) strstr( $component->html(), 'data-ec-kb-card-actions' ), 'ec-kb-card-widgets', true );

    expect( $menu )->toMatch( '/data-ec-kb-action="open".*data-ec-kb-action="reprint".*data-ec-kb-action="invoice"/s' )
        ->and( $menu )->not->toContain( 'role="separator"' );
} );

it( 'has hooks for the moving and reverting states', function (): void {
    cardBoard( [] )
        ->assertSeeHtml( 'x-on:ec-kb-card-state.window' )
        ->assertSeeHtml( 'x-bind:data-ec-kb-state="state"' )
        ->assertSeeHtml( 'class="ec-kb-spinner"' )
        ->assertSeeHtml( 'class="ec-kb-card-state" aria-hidden="true"' )
        ->assertSee( 'Moving…' )
        ->assertSee( 'Move undone' );
} );

it( 'renders the card as a focusable group that holds still under reduced motion', function (): void {
    $html = cardBoard( [] )->html();

    expect( $html )->toMatch( '/<div\s[^>]*class="ec-kb-card"[^>]*tabindex="0"\s+role="group"\s+aria-label="Order 1042/' )
        ->and( $html )->not->toContain( '<article' )
        ->and( $html )->not->toContain( 'animate-pulse' );

    // The card's motion stops under reduced motion (resources/css/kanban.css).
    expect( (string) file_get_contents( dirname( __DIR__, 3 ) . '/resources/css/kanban.css' ) )->toMatch( '/prefers-reduced-motion: reduce\)\s*\{\s*\.ec-kb-card\s*\{\s*transition: none;/' );
} );

it( 'gives each column an explicit region role, so a draggable column never becomes a button', function (): void {
    [ $board ] = makeBoard( [ 0, 0 ] );

    $html = Livewire::test( Board::class, [ 'board' => $board ] )->call( 'loadBoard', [] )->html();

    expect( preg_match_all( '/<section\s[^>]*role="region"\s+aria-labelledby="ec-kb-column-\d+-title"/', $html ) )->toBe( 2 );
} );

it( 'maps every tone to a design-system Badge color', function (): void {
    foreach ( WidgetViews::TONES as $tone ) {
        expect( $tone['color'] )->toBeIn( [ 'neutral', 'success', 'warning', 'error', 'info' ] );
    }
} );

it( 'sets the order number in the mono face without breaking the translated line', function (): void {
    cardBoard( [], [ 'order_number' => '<b>10</b>' ] )
        ->assertSeeHtml( 'Order <span class="ec-kb-card-number">&lt;b&gt;10&lt;/b&gt;</span>' )
        ->assertDontSeeHtml( '<b>10</b>' );
} );

it( 'shows the grab cursor only when the viewer may move cards', function (): void {
    expect( cardBoard( [] )->html() )->not->toContain( 'data-ec-kb-draggable' );

    grantAbilities( [ 'kanbanCard.move' ] );

    expect( cardBoard( [], [ 'order_number' => '1043' ] )->html() )->toMatch( '/class="ec-kb-card"[^>]*data-ec-kb-draggable/' );
} );

it( 'reads without widgets or a placed date', function (): void {
    $html = cardBoard( [], [ 'placed_at' => null ] )->html();

    expect( $html )->toContain( 'ec-kb-card-customer' )
        ->and( $html )->not->toContain( 'ec-kb-card-date' );
} );

it( 'leads with the tone icon, then the widget icon, then the value', function (): void {
    fakeWidget( 'fulfilled', [ 'value' => 'Fulfilled', 'tone' => 'success', 'icon' => 'truck' ] );

    $html  = cardBoard( [ 'fulfilled' ] )->html();
    $badge = (string) strstr( (string) strstr( $html, 'data-ec-kb-widget="fulfilled"' ), '</span></span>', true );

    expect( $badge )->toContain( 'class="ec-kb-widget-icons"' )
        ->and( substr_count( (string) strstr( $badge, 'ec-kb-widget-icons' ), 'class="apui-icon"' ) )->toBe( 2 )
        ->and( strpos( $badge, 'data-part="icon"' ) )->toBeLessThan( strpos( $badge, 'data-part="label"' ) );
} );

it( 'shows no icons for a plain neutral value', function (): void {
    fakeWidget( 'plain', [ 'value' => '$184.00' ] );

    cardBoard( [ 'plain' ] )->assertDontSeeHtml( 'class="ec-kb-widget-icons"' );
} );

it( 'reads neutral tags in the info color with no tone icon', function (): void {
    $html = (string) WidgetViews::render( [ 'key' => 'tags', 'label' => 'Tags', 'value' => 'Wholesale', 'tone' => 'neutral', 'icon' => 'tag' ] );

    expect( $html )->toMatch( '/data-ec-kb-widget="tags"[^>]*data-ec-kb-tone="neutral"[^>]*data-variant="soft"\s+data-color="info"/' )
        ->and( $html )->not->toContain( 'data-ec-kb-tone-icon' );

    expect( WidgetViews::tone( 'warning', 'tags' )['color'] )->toBe( 'warning' )
        ->and( WidgetViews::tone( 'neutral', 'tags' ) )->toBe( [ 'color' => 'info', 'icon' => null, 'text' => null ] )
        ->and( WidgetViews::tone( 'neutral', 'total' )['color'] )->toBe( 'neutral' );
} );

it( 'makes a badge with a tooltip focusable so the tooltip opens from the keyboard', function (): void {
    fakeWidget( 'waiting', [ 'value' => '3 days', 'tone' => 'warning', 'tooltip' => 'Packing since Nov 19' ] );

    cardBoard( [ 'waiting' ] )
        ->assertSeeHtml( 'class="ec-kb-widget-tip" tabindex="0"' )
        ->assertSeeHtml( 'role="tooltip"' )
        ->assertSee( '(Packing since Nov 19)' );
} );
