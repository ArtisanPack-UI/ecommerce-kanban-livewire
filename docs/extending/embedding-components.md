---
title: Embedding Components
---

# Embedding Components

The picker and the board are Livewire components you can place on your own pages. Use this when the package's routes don't fit: the board inside your own admin area, under your own URLs, or next to your own panels.

## Turning the board routes off

```php
// config/artisanpack/ecommerce-kanban-livewire.php
'board' => [
    'routes_enabled' => false,
    // …
],
```

With `routes_enabled` off, the package registers neither route (`artisanpack.ecommerce.kanban.*`) and adds no "Kanban" admin menu entry. The Livewire and Blade components are still registered, and so is the `ecommerce-kanban.throttle` middleware alias.

You can also leave the routes on and embed the board on other pages as well.

## Components

| Component | Props | Shows |
| --- | --- | --- |
| `artisanpack-ecommerce-kanban-board` | `board` (a `KanbanBoard`, or its id) | A whole board: header, filter bar, columns, and every dialog. It renders the card slide-out, the automations panel, and (for users with `kanbanBoard.update`) the widget picker itself. Answers 404 for a board that doesn't exist and 403 without `kanbanBoard.view`. |
| `artisanpack-ecommerce-kanban-board-picker` | `pick` (bool, default `false`) | The board list. Without `pick`, it redirects to the user's last board, or the only board, when it has a URL for it. Needs `kanbanBoard.viewAny`. |
| `artisanpack-ecommerce-kanban-card-detail` | `boardId` (int) | The card slide-out. Rendered by the board; you don't embed it on its own. |
| `artisanpack-ecommerce-kanban-widget-picker` | `boardId` (int) | The widget picker. Rendered by the board. |
| `artisanpack-ecommerce-kanban-automations` | `boardId` (int) | The automations panel. Rendered by the board. |

Every component checks its ability on mount and again on every update.

The Blade components the board is built from are `x-artisanpack-ec-kb-card`, `x-artisanpack-ec-kb-column`, `x-artisanpack-ec-kb-wip`, `x-artisanpack-ec-kb-widget`, `x-artisanpack-ec-kb-connection`, and `x-artisanpack-ec-kb-empty`. They expect the board component's data and Alpine scope, so use them through the board rather than on their own.

## A board on your own route

Register a route that runs your middleware and the board's rate limiter:

```php
use App\Http\Controllers\FulfillmentBoardController;
use Illuminate\Support\Facades\Route;

Route::get( 'staff/boards/{board}', FulfillmentBoardController::class )
    ->middleware( [ 'web', 'auth', 'ecommerce-kanban.throttle' ] )
    ->name( 'staff.boards.show' );
```

Add `ecommerce-kanban.throttle` to every route that embeds the board. The limiter is registered as Livewire persistent middleware, so Livewire re-runs it on the board's updates, but only for pages whose route has it. See [Rate limits](Advanced#rate-limits).

Embed the component in the view:

```blade
<livewire:artisanpack-ecommerce-kanban-board :board="$board" />
```

The page needs what the package's layouts provide (see [Layouts](Installation-Layouts#the-layout-contract)): your Tailwind build and main script (with drag-and-drop and, for live boards, Echo), Livewire's assets, `<x-artisanpack-toast />`, and `@include( 'ecommerce-kanban::partials.rate-limit-notice' )` after `@livewireScripts`.

## Pointing links at your routes

The board links to boards from the switcher, the picker, each card's **Open** action (its new-tab link), and the automation failure toast. With the routes off those links have nowhere to go, so supply your URLs:

```php
use ArtisanPackUI\Ecommerce\Models\KanbanBoard;

addFilter( 'ap.ecommerceKanbanLivewire.boardUrl', function ( ?string $url, KanbanBoard $board, array $query ): ?string {
    return route( 'staff.boards.show', [ 'board' => $board->id, ...$query ] );
} );

addFilter( 'ap.ecommerceKanbanLivewire.pickerUrl', function ( ?string $url ): ?string {
    return route( 'staff.boards.index', [ 'pick' => 1 ] );
} );
```

`$query` carries the parameters the board adds, such as `card` to open a card's slide-out, so pass it through. Return null to leave a link out. Without a board URL, the picker lists boards without links and doesn't redirect, and the switcher and new-tab links are left out.

## Deep links

The board reads its state from the query string on any page it is embedded in: `?card={order}` opens a card, and the filter keys (`q`, `customer`, `tags`, `products`, `from`, `to`, `stale`, `payment`, `fulfillment`, `f`) set the filters. See [Filters](Usage-Filters).

## Middleware

`board.middleware` is still read with the routes off: every class or alias in it is registered as Livewire persistent middleware. Keep it in step with the middleware on your own routes. The package's routes in `routes/web.php` are the reference.
