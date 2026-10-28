---
title: Hooks Reference
---

# Hooks Reference

Every seam a satellite or host can use to change the board without publishing views. Filters and actions use the `artisanpack-ui/hooks` helpers. Register everything from a service provider's `boot()`. The names are constants on `ArtisanPackUI\EcommerceKanbanLivewire\Support\Hooks`.

Filters receive the value as the first argument and must return it; every extra argument is passed to the callback, so declare only the ones you need. Hooks run only after the board has been authorized for the viewer.

## Cards

| Hook | Constant | Type | When it runs | Arguments | Return |
| --- | --- | --- | --- | --- | --- |
| `ap.ecommerceKanbanLivewire.cardActions` | `CARD_ACTIONS` | filter | Building each card's actions menu | `array $actions`, `Order $order`, `KanbanColumn $column`, `KanbanBoard $board` | Actions: `key`, `label`, optional `icon` and `ability`, and a `url`, an `event`, or both. The core actions are `open`, `move`, and `remove`. See [Card and Toolbar Actions](Extending-Card-Actions). |
| `ap.ecommerceKanbanLivewire.cardClasses` | `CARD_CLASSES` | filter | Rendering each card | `array $classes`, `Order $order`, `KanbanColumn $column`, `KanbanBoard $board` | Extra CSS classes on the card. Strings only; split on whitespace; repeats dropped. Add the classes to your Tailwind sources. |
| `ap.ecommerceKanbanLivewire.widgetView` | `WIDGET_VIEW` | filter | Rendering each widget on a card | `?string $view` (null), `string $key`, `array $payload` | A Blade view name for this widget key, or the value unchanged. A view that doesn't exist falls back to the generic view. See [Card Widgets](Extending-Card-Widgets). |

```php
use ArtisanPackUI\Ecommerce\Models\Order;

// Highlight large orders.
addFilter( 'ap.ecommerceKanbanLivewire.cardClasses', function ( array $classes, Order $order ): array {
    return $order->total_amount > 50_000 ? [ ...$classes, 'border-l-4 border-l-warning' ] : $classes;
} );
```

## Board

| Hook | Constant | Type | When it runs | Arguments | Return |
| --- | --- | --- | --- | --- | --- |
| `ap.ecommerceKanbanLivewire.boardToolbar` | `BOARD_TOOLBAR` | filter | Rendering the board header | `array $actions` (empty), `KanbanBoard $board`, `?Authenticatable $user` | Header buttons, in the card action shape. Events are dispatched with `{ boardId }`. See [Card and Toolbar Actions](Extending-Card-Actions#toolbar-actions). |
| `ap.ecommerceKanbanLivewire.filters` | `FILTERS` | filter | Building the filter bar | `array $controls` (empty), `KanbanBoard $board` | Extra controls: `key`, `label`, `type` (`toggle`, `select`, or `text`), and `options` for `select`. Pair with the engine's `ap.ecommerce.kanban.cardQuery`. See [Filter Controls](Extending-Filter-Controls). |
| `ap.ecommerceKanbanLivewire.boardRendered` | `BOARD_RENDERED` | action | After each board render | `KanbanBoard $board`, `array $columns` (what the board shows: labels, counts, and the presented cards), `?Authenticatable $user` | — |
| `ap.ecommerceKanbanLivewire.cardMoveFailed` | `CARD_MOVE_FAILED` | action | When the board refuses a move, or the engine rejects it | `string $code`, `?Order $order`, `?KanbanColumn $column`, `KanbanBoard $board`, `?Authenticatable $user` | — |

`$code` is one of `wip-limit-reached`, `invalid-status-transition`, `incompatible-column`, `move-rejected`, `not-on-board`, `column-not-on-board`, `no-entry-column`, or `unknown`. See [Moving Cards](Usage-Moving-Cards#why-a-move-is-refused).

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
| `ap.ecommerceKanbanLivewire.boardUrl` | `BOARD_URL` | filter | Building a link to a board (switcher, picker, card "Open", automation toasts) | `?string $url` (the package route, or null when routes are off), `KanbanBoard $board`, `array $query` (for example `[ 'card' => 1004 ]`) | The board's URL, or null for none |
| `ap.ecommerceKanbanLivewire.pickerUrl` | `PICKER_URL` | filter | Building the link to the board picker | `?string $url` (the package route with `?pick=1`, or null) | The picker's URL, or null for none |
| `ap.ecommerceKanbanLivewire.layout.viteEntries` | `VITE_ENTRIES` | filter | Loading assets in the standalone layout and under cms-framework's layout | `array $entries` (`[ 'resources/css/app.css', 'resources/js/app.js' ]`) | Vite entries; an empty array loads nothing. See [Front-End Setup](Installation-Front-End#which-entries-the-board-loads). |

Use the URL filters when you serve the board from your own routes. See [Embedding Components](Extending-Embedding-Components).

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
