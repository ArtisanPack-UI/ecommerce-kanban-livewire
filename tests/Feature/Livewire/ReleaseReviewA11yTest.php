<?php

declare( strict_types=1 );

use ArtisanPack\LivewireUiComponents\Support\RenderedIds;
use ArtisanPackUI\Ecommerce\Kanban\Widgets\AbstractKanbanCardWidget;
use ArtisanPackUI\Ecommerce\Models\KanbanColumn;
use ArtisanPackUI\Ecommerce\Models\Order;
use ArtisanPackUI\Ecommerce\Registries\KanbanCardWidgetRegistry;
use ArtisanPackUI\EcommerceKanbanLivewire\Livewire\Board;
use ArtisanPackUI\EcommerceKanbanLivewire\Livewire\WidgetPicker;
use ArtisanPackUI\EcommerceKanbanLivewire\Support\KanbanStyles;
use Illuminate\Support\Facades\Blade;
use Livewire\Livewire;

/*
 * The 2.0 release review's front-end and accessibility fixes (F1–F18, B7's
 * view side, D9): the markup each fix depends on.
 */

/**
 * Registers a widget that renders `$payload` for every order.
 *
 * @param  array<string, mixed>  $payload  What `render()` returns.
 */
function reviewWidget( string $key, array $payload ): void
{
    app( KanbanCardWidgetRegistry::class )->register( $key, new class( $key, $payload ) extends AbstractKanbanCardWidget {
        public function __construct( private string $widgetKey, private array $payload )
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
            return $this->payload;
        }
    } );
}

/**
 * `$html` without HTML comments (Livewire's `<!--[if BLOCK]>` markers).
 */
function reviewClean( string $html ): string
{
    return (string) preg_replace( '/<!--.*?-->/s', '', $html );
}

/**
 * The values of every `id="…"` attribute in `$html`.
 *
 * @return array<int, string>
 */
function reviewIds( string $html ): array
{
    preg_match_all( '/\sid="([^"]+)"/', $html, $matches );

    return $matches[1];
}

/**
 * The ids in `$html` outside the element whose `$attribute` is `$value`,
 * and the ids inside it.
 *
 * @return array{0: array<int, string>, 1: array<int, string>}
 */
function reviewIdsAround( string $html, string $attribute, string $value ): array
{
    $document = new DOMDocument();
    libxml_use_internal_errors( true );
    $document->loadHTML( '<?xml encoding="utf-8"?>' . $html );
    libxml_clear_errors();

    $xpath   = new DOMXPath( $document );
    $inside  = [];
    $outside = [];

    foreach ( $xpath->query( '//*[@id]' ) as $element ) {
        $within = $xpath->query( 'ancestor-or-self::*[@' . $attribute . '="' . $value . '"]', $element )->length > 0;

        if ( $within ) {
            $inside[] = $element->getAttribute( 'id' );
        } else {
            $outside[] = $element->getAttribute( 'id' );
        }
    }

    return [ $outside, $inside ];
}

beforeEach( function (): void {
    grantAbilities( [ 'kanbanBoard.viewAny', 'kanbanBoard.view', 'kanbanBoard.update', 'kanbanCard.move', 'order.view', 'order.update' ] );
    $this->actingAs( makeUser() );
} );

it( 'renders no id in a single-card refresh that the board already uses (F10)', function (): void {
    reviewWidget( 'note', [ 'value' => 'On hold', 'tooltip' => 'Waiting on the customer' ] );

    [ $board, $columns ] = makeBoard( [ [ 'cards' => 0, 'card_widgets' => [ 'note' ] ] ] );
    $first               = addCard( $columns[0], [ 'order_number' => '2001' ] );
    $second              = addCard( $columns[0], [ 'order_number' => '2002' ] );

    $component = Livewire::test( Board::class, [ 'board' => $board ] )->call( 'loadBoard', [] );
    $boardIds  = reviewIds( $component->html() );

    expect( $boardIds )->toBe( array_values( array_unique( $boardIds ) ) );

    foreach ( [ $first, $second ] as $card ) {
        // A refresh is its own request, so the component library's id claims start empty.
        app()->forgetInstance( RenderedIds::class );

        $html = $component->call( 'refreshCards', [ $card->order_id ] )->effects['returns'][0]['cards'][ $card->order_id ];

        [ $elsewhere ] = reviewIdsAround( $component->html(), 'data-ec-kb-card', (string) $card->order_id );

        expect( reviewIds( $html ) )->not->toBe( [] )
            ->and( array_values( array_intersect( reviewIds( $html ), $elsewhere ) ) )->toBe( [] );
    }
} );

it( 'renders no id in a single-column refresh that another column already uses (F10)', function (): void {
    reviewWidget( 'note', [ 'value' => 'On hold', 'tooltip' => 'Waiting on the customer' ] );

    [ $board, $columns ] = makeBoard( [ [ 'cards' => 0, 'card_widgets' => [ 'note' ] ], [ 'cards' => 0, 'card_widgets' => [ 'note' ] ] ] );
    addCard( $columns[0], [ 'order_number' => '2001' ] );
    addCard( $columns[1], [ 'order_number' => '2002' ] );

    $component = Livewire::test( Board::class, [ 'board' => $board ] )->call( 'loadBoard', [] );
    app()->forgetInstance( RenderedIds::class );
    $html      = $component->call( 'refreshColumns', [ $columns[1]->id ] )->effects['returns'][0]['columns'][ $columns[1]->id ];

    [ $elsewhere ] = reviewIdsAround( $component->html(), 'data-ec-kb-column', (string) $columns[1]->id );

    expect( array_values( array_intersect( reviewIds( $html ), $elsewhere ) ) )->toBe( [] );
} );

it( 'keeps the card\'s move state out of a live region, mirrors the undo icon, and never dims the card (F7, F8, F16)', function (): void {
    [ $board, $columns ] = makeBoard( [ [ 'cards' => 0 ] ] );
    addCard( $columns[0], [ 'order_number' => '2001' ] );

    $html = Livewire::test( Board::class, [ 'board' => $board ] )->call( 'loadBoard', [] )->html();

    expect( $html )->toMatch( '/class="ec-kb-card-state" aria-hidden="true"/' )
        ->and( $html )->not->toMatch( '/class="ec-kb-card-state"[^>]*role="status"/' )
        ->and( KanbanStyles::css() )->not->toMatch( "/data-ec-kb-state='moving'\\]\\s*\\{[^}]*opacity\\s*:/" );

    preg_match( '/<p class="ec-kb-card-state"[\s\S]*?<\/p>/', $html, $state );

    expect( $state[0] ?? '' )->toContain( 'data-mirror' );
} );

it( 'announces the connection state as text in one status region, and hides "Live" until Alpine starts (F6)', function (): void {
    [ $board ] = makeBoard( [ 0 ] );

    $html = Livewire::test( Board::class, [ 'board' => $board ] )->html();

    expect( $html )->toContain( 'data-ec-kb-connection-status' )
        ->and( substr_count( $html, 'data-ec-kb-connection-status' ) )->toBe( 1 )
        ->and( $html )->toMatch( '/role="status"\s+data-ec-kb-connection-status\s+x-text=/' )
        ->and( $html )->toMatch( '/class="apui-badge" aria-hidden="true" x-show="\'live\' === connection" x-cloak/' );
} );

it( 'gives edit-mode columns column instructions and the widget list translated ones (F4)', function (): void {
    [ $board ] = makeBoard( [ 0, 0 ] );

    $html = Livewire::test( Board::class, [ 'board' => $board ] )->call( 'startEditing' )->html();

    preg_match( '/data-drag-group="columns"[\s\S]*?data-drag-messages="([^"]+)"/', $html, $strip );
    $messages = json_decode( html_entity_decode( $strip[1] ?? '' ), true );

    expect( $messages['instructions'] )->toContain( 'pick up the column' )
        ->and( $messages['instructions'] )->not->toContain( 'card' )
        ->and( $messages['item'] )->toBe( 'Column' );

    app()->setLocale( 'de' );

    expect( Board::dragMessages( 'widgets' ) )->toMatchArray( [ 'item' => 'Widget', 'container' => 'die Widget-Liste' ] )
        ->and( Board::dragMessages( 'widgets' )['instructions'] )->toStartWith( 'Drücken Sie' );
} );

it( 'keeps widget-picker rows list items with their own name, tells the list what to announce, and renders the preview as a plain card (F3, F4, F14)', function (): void {
    reviewWidget( 'reviewtotal', [ 'value' => '$12.00' ] );
    reviewWidget( 'reviewnote', [ 'value' => 'On hold' ] );

    [ $board, $columns ] = makeBoard( [ [ 'cards' => 0, 'card_widgets' => [ 'reviewtotal', 'reviewnote' ] ] ] );
    addCard( $columns[0], [ 'order_number' => '2001' ] );

    app()->setLocale( 'de' );

    $html = Livewire::test( WidgetPicker::class, [ 'boardId' => $board->id ] )
        ->call( 'openFor', $columns[0]->id )
        ->html();

    expect( $html )->toMatch( '/<li\s+class="ec-kb-widget-row"\s+role="listitem"\s+aria-label="Reviewtotal"/' )
        ->and( $html )->toMatch( '/data-drag-messages="[^"]*Dr&uuml;cken Sie|data-drag-messages="[^"]*Drücken Sie|data-drag-messages="[^"]*Dr\\\\u00fccken Sie/' )
        ->and( $html )->toContain( 'data-ec-kb-card-preview=' )
        ->and( $html )->not->toMatch( '/data-ec-kb-card="/' );
} );

it( 'shows a row error as an alert the row\'s remove button points at (F18)', function (): void {
    [ $board, $columns ] = makeBoard( [ 0 ] );

    $html = Livewire::test( WidgetPicker::class, [ 'boardId' => $board->id ] )
        ->call( 'openFor', $columns[0]->id )
        ->set( 'addKey', 'tags' )->call( 'addWidget' )
        ->set( 'entries.0.key', 'no-such-widget' )
        ->call( 'save' )
        ->assertHasErrors( 'entries.0' )
        ->html();

    expect( $html )->toMatch( '/id="ec-kb-widget-0-error"[^>]*role="alert"[^>]*tabindex="-1"/' )
        ->and( $html )->toContain( 'aria-describedby="ec-kb-widget-0-error"' )
        ->and( $html )->toContain( 'x-on:submit.prevent="$wire.save()' );
} );

it( 'announces "Add order" results through a status region that is always there (F9)', function (): void {
    [ $board, $columns ] = makeBoard( [ 0 ] );
    $other               = makeBoard( [ 0 ] );
    addCard( $other[1][0], [ 'order_number' => '7001' ] );
    addCard( $other[1][0], [ 'order_number' => '7002' ] );

    $component = Livewire::test( Board::class, [ 'board' => $board ] )->call( 'openAddOrder' );

    expect( reviewClean( $component->html() ) )->toMatch( '/role="status"\s+data-ec-kb-add-order-status>\s*<\/p>/' );

    $component->set( 'addOrderSearch', '700' );
    expect( reviewClean( $component->html() ) )->toMatch( '/data-ec-kb-add-order-status>\s*2 orders found\s*<\/p>/' );

    $component->set( 'addOrderSearch', 'no-such-order' );
    expect( reviewClean( $component->html() ) )->toMatch( '/data-ec-kb-add-order-status>\\s*No orders that aren&#039;t already on this board match.\\s*<\\/p>/' );
} );

it( 'describes the "Move to…" dialog by its task (F15)', function (): void {
    [ $board ] = makeBoard( [ 0 ] );

    Livewire::test( Board::class, [ 'board' => $board ] )
        ->assertSee( 'Choose a column and where the order goes in it.' )
        ->assertDontSee( 'The keyboard and single-pointer way to move a card.' );
} );

it( 'sets dir="rtl" on the board for a right-to-left locale only (F11)', function (): void {
    [ $board ] = makeBoard( [ 0 ] );

    expect( Livewire::test( Board::class, [ 'board' => $board ] )->html() )->not->toMatch( '/class="ec-kb-board"\s+dir=/' );

    app()->setLocale( 'ar' );

    expect( Livewire::test( Board::class, [ 'board' => $board ] )->html() )->toMatch( '/class="ec-kb-board"\s+dir="rtl"/' )
        ->and( KanbanStyles::textDirection( 'ar' ) )->toBe( 'rtl' )
        ->and( KanbanStyles::textDirection( 'he_IL' ) )->toBe( 'rtl' )
        ->and( KanbanStyles::textDirection( 'pt_BR' ) )->toBe( 'ltr' );
} );

it( 'inlines its own styles once, so an embedded board is styled (D9)', function (): void {
    [ $board ] = makeBoard( [ 0 ] );

    $html = reviewClean( Blade::render( '<livewire:artisanpack-ecommerce-kanban-board :board="$board" />@include( \'ecommerce-kanban::partials.styles\' )', [ 'board' => $board ] ) );

    expect( substr_count( $html, 'data-ecommerce-kanban-styles' ) )->toBe( 1 )
        ->and( $html )->toMatch( '/<div hidden wire:ignore data-ec-kb-board-styles>\s*<style data-ecommerce-kanban-styles/' );
} );

it( 'opens a card action\'s URL in a new tab on a modified click (B7)', function (): void {
    [ $board, $columns ] = makeBoard( [ [ 'cards' => 0 ] ] );
    addCard( $columns[0], [ 'order_number' => '2001' ] );

    $html = Livewire::test( Board::class, [ 'board' => $board ] )->call( 'loadBoard', [] )->html();

    expect( $html )->toContain( 'window.ecommerceKanbanModifiedAt' )
        ->and( $html )->toContain( 'window.open( target.url' )
        ->and( $html )->toContain( 'watchModifiers()' );
} );
