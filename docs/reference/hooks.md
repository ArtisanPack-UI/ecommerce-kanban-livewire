---
title: Hooks
menu_order: 10
redirect_from: extending/hooks-reference
---

# Hooks

Every seam a satellite or host can use to change the board without publishing views. Filters and actions use the `artisanpack-ui/hooks` helpers. Register everything from a service provider's `boot()`. The names are constants on `ArtisanPackUI\EcommerceKanbanLivewire\Support\Hooks`.

Filters receive the value as the first argument and must return it; every extra argument is passed to the callback, so declare only the ones you need. Hooks run only after the board has been authorized for the viewer.

## Cards

| Hook | Constant | Type | When it runs | Arguments | Return |
| --- | --- | --- | --- | --- | --- |
| `ap.ecommerceKanbanLivewire.cardActions` | `CARD_ACTIONS` | filter | Building each card's actions menu | `array $actions`, `Order $order`, `KanbanColumn $column`, `KanbanBoard $board` | Actions: `key`, `label`, optional `icon` and `ability`, and a `url`, an `event`, or both. The core actions are `open`, `move`, and `remove`. Keys are unique: the first action per key wins, and a satellite can't replace a core action by reusing its key. See [Card and Toolbar Actions](../extending/card-actions.md#unique-keys). |
| `ap.ecommerceKanbanLivewire.cardClasses` | `CARD_CLASSES` | filter | Rendering each card | `array $classes`, `Order $order`, `KanbanColumn $column`, `KanbanBoard $board` | Extra CSS classes on the card. Strings only; split on whitespace; repeats dropped. Style them in your own stylesheet (see the example below). |
| `ap.ecommerceKanbanLivewire.widgetView` | `WIDGET_VIEW` | filter | Rendering each widget on a card | `?string $view` (null), `string $key`, `array $payload` | A Blade view name for this widget key, or the value unchanged. A view that doesn't exist falls back to the generic view. See [Card Widgets](../extending/card-widgets.md). |

```php
use ArtisanPackUI\Ecommerce\Models\Order;

// Highlight large orders.
addFilter( 'ap.ecommerceKanbanLivewire.cardClasses', function ( array $classes, Order $order ): array {
    return $order->total_amount > 50_000 ? [ ...$classes, 'shop-card-large' ] : $classes;
} );
```

The board doesn't style classes it doesn't know, so add a rule for yours to your main stylesheet. Build it on the card's `ec-kb-card` class and the design system's tokens, so it follows the light and dark themes:

```css
.ec-kb-card.shop-card-large {
    border-inline-start: 4px solid var(--apui-color-warning);
}
```

Tailwind isn't required. If your app builds with Tailwind, you can return Tailwind utilities instead, as long as the file that returns them is in your Tailwind sources. daisyUI colors such as `border-l-warning` no longer exist in 2.0.

## Board

| Hook | Constant | Type | When it runs | Arguments | Return |
| --- | --- | --- | --- | --- | --- |
| `ap.ecommerceKanbanLivewire.boardToolbar` | `BOARD_TOOLBAR` | filter | Rendering the board header | `array $actions` (empty), `KanbanBoard $board`, `?Authenticatable $user` | Header buttons, in the card action shape. Events are dispatched with `{ boardId }`. See [Card and Toolbar Actions](../extending/card-actions.md#toolbar-actions). |
| `ap.ecommerceKanbanLivewire.filters` | `FILTERS` | filter | Building the filter bar | `array $controls` (empty), `KanbanBoard $board` | Extra controls: `key`, `label`, `type` (`toggle`, `select`, or `text`), and `options` for `select`. Pair with the engine's `ap.ecommerce.kanban.cardQuery`, which the board enforces on every card path (see [Engine hooks](#engine-hooks)). See [Filter Controls](../extending/filter-controls.md). |
| `ap.ecommerceKanbanLivewire.boardRendered` | `BOARD_RENDERED` | action | After each board render | `KanbanBoard $board`, `array $columns` (what the board shows: labels, counts, and the presented cards), `?Authenticatable $user` | — |
| `ap.ecommerceKanbanLivewire.cardMoveFailed` | `CARD_MOVE_FAILED` | action | When the board refuses a move, or the engine rejects it | `string $code`, `?Order $order`, `?KanbanColumn $column`, `KanbanBoard $board`, `?Authenticatable $user` | — |

`$code` is one of `wip-limit-reached`, `invalid-status-transition`, `incompatible-column`, `move-rejected`, `not-on-board`, `column-not-on-board`, `no-entry-column`, or `unknown`. See [Moving Cards](../usage/moving-cards.md#why-a-move-is-refused).

```php
use ArtisanPackUI\Ecommerce\Models\KanbanBoard;
use ArtisanPackUI\Ecommerce\Models\Order;
use Illuminate\Support\Facades\Log;

addAction( 'ap.ecommerceKanbanLivewire.boardRendered', function ( KanbanBoard $board ): void {
    Metrics::increment( 'kanban.views', [ 'board' => $board->id ] );
} );

addAction( 'ap.ecommerceKanbanLivewire.cardMoveFailed', function ( string $code, ?Order $order ): void {
    Log::info( 'Kanban move refused', [ 'code' => $code, 'order' => $order?->id ] );
} );
```

`boardRendered` runs whenever the board component renders: the page load, loading the cards, filter changes, edits, and board events. Refreshes that re-render single columns or cards (after a drop, a poll, or a live card event) skip it. Keep it cheap.

`cardMoveFailed` runs on the server. A drop into a full column that the browser refuses before sending it doesn't reach the server, so it doesn't run the action.

## URLs and assets

| Hook | Constant | Type | When it runs | Arguments | Return |
| --- | --- | --- | --- | --- | --- |
| `ap.ecommerceKanbanLivewire.boardUrl` | `BOARD_URL` | filter | Building a link to a board (switcher, picker, card "Open") | `?string $url` (the package route, or null when routes are off), `KanbanBoard $board`, `array $query` (for example `[ 'card' => 1004 ]`) | The board's URL, or null for none |
| `ap.ecommerceKanbanLivewire.pickerUrl` | `PICKER_URL` | filter | Building the link to the board picker | `?string $url` (the package route with `?pick=1`, or null) | The picker's URL, or null for none |
| `ap.ecommerceKanbanLivewire.layout.viteEntries` | `VITE_ENTRIES` | filter | Loading assets in the standalone layout and under cms-framework's layout | `array $entries` (`[ 'resources/css/app.css', 'resources/js/app.js' ]`) | Vite entries; an empty array loads nothing. See [Front-End Setup](../installation/front-end.md#which-entries-the-board-loads). |

Use the URL filters when you serve the board from your own routes. See [Embedding Components](../extending/embedding-components.md).

## Livewire events

The board's own components talk through browser-side Livewire events. A satellite can dispatch them to drive the board:

| Event | Payload | Handled by | Does |
| --- | --- | --- | --- |
| `ec-kb-open-card` | `orderId` | Card slide-out | Opens the card's slide-out |
| `ec-kb-move-card` | `orderId` | Board | Opens "Move to…" for the card |
| `ec-kb-remove-card` | `orderId` | Board | Asks before removing the card from the board |
| `ec-kb-edit-widgets` | `columnId` (null for the board default) | Widget picker | Opens the widget picker |
| `ec-kb-edit-automations` | `columnId` (null for the whole board) | Automations panel | Opens the automations panel |
| `ec-kb-board-changed` | — | Board | Re-renders the board after another component changed its setup |

## Engine hooks

Every read and write goes through the engine, so its kanban hooks apply to the board: `ap.ecommerce.kanban.cardQuery`, `ap.ecommerce.kanban.cardMoving`, `ap.ecommerce.kanban.cardMoved`, `ap.ecommerce.kanban.widgetRendered`, `ap.ecommerce.kanban.boardAssignmentAdded`, `ap.ecommerce.kanban.boardAssignmentRemoved`, `ap.ecommerce.kanban.automationFired`, and the rest. See the engine's kanban documentation.

The board also applies one engine filter itself: `ap.ecommerce.kanban.orderTags` (`array $tags`, `Order $order`), which the engine uses for an order's tags. The board runs it when it builds the Tags filter's options, so tags your filter adds or removes show there the same way they do on cards.

### `ap.ecommerce.kanban.cardQuery` is a visibility boundary

The board enforces a `cardQuery` filter on every card path, not only when it lists cards. A card the filter hides is left out of the columns, counts, and live refreshes; moves, reorders, "Move to…", and "Remove from board" on it are refused; the card slide-out (including `?card={order}`) doesn't open it; and it is left out of the Customer, Products, and Tags filter options, the widget picker's preview, the board picker's card counts, and the count "Show more" uses. The board applies the filter with its own filter bar cleared, so a hidden card stays hidden whatever the viewer filters by. The column header's total and the WIP limit still count hidden cards, because the engine enforces WIP limits against every card: the numbers show, but no hidden card's content does. `Queries\VisibleCards` is the query behind all of this (see [PHP API](php-api.md#queriesvisiblecards)). See [Restricting which cards people see](../usage/filters.md#restricting-which-cards-people-see).
