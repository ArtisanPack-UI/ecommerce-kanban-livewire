---
title: Advanced Overview
---

# Advanced

Access control, commands, internals, and contributor tooling.

## In this section

- [Authorization](Advanced-Authorization) - The abilities each screen and action needs
- [Artisan Commands](Advanced-Artisan-Commands) - `ecommerce-kanban:install` and the engine commands a store uses with the board
- [Performance](Advanced-Performance) - Lazy columns, partial re-renders, and query budgets
- [Browser Tests](Advanced-Browser-Tests) - Running and writing the Playwright browser suite

## How a request flows

```
Route → BoardScreenController (checks the ability, returns a page view)
          → page view  @extends( $ecommerceKanbanLayout )
              → <livewire:artisanpack-ecommerce-kanban-board-picker />
              → <livewire:artisanpack-ecommerce-kanban-board />
                   → engine services   (moves, assignments, columns, widgets, automations: writes)
                   → engine queries    (boards, cards, counts: reads)
```

- **Pages are controller actions** returning a view that embeds one Livewire component. They are not full-page Livewire routes, so `route:cache` works and the layout can be swapped (`layout`).
- **Writes go through the engine.** `KanbanBoardService` moves cards, `KanbanRoutingService` adds and removes them, and `KanbanColumnService` edits columns. The board owns no tables.
- **Checked twice.** The controller answers 403 when the page's ability is missing, and every component checks again on mount and on every update.

## Rate limits

Board changes run through the engine's `ecommerce.admin.mutate` rate limiter (120 a minute per user by default; `ECOMMERCE_RATE_ADMIN_MUTATE_PER_USER`). The `ecommerce-kanban.throttle` middleware (`ThrottleKanbanMutations`) applies it. It runs on the board routes, after `board.middleware`, and is registered as Livewire persistent middleware, so it runs again on every Livewire update from a board page.

What counts:

- **Not counted:** page loads, and updates from this package's components that only read:
  - **Property changes** (`ThrottleKanbanMutations::READ_PROPERTIES`) limited to:
    - the board's own `liveFeedback` flag;
    - the filter bar and the "Add order" search;
    - whether a dialog or panel is open.
  - **Calls** (`ThrottleKanbanMutations::READ_CALLS`) limited to:
    - loading the board or a lazy column, collapsing or expanding a column, and "Show more";
    - `$refresh`, polls, and real-time column, card, and board refreshes;
    - automation feedback;
    - the customer and product searches and "Clear filters";
    - opening and closing dialogs and panels.
- **Counted:** every other update: moves, adding and removing cards, column and widget edits, automation changes, and any update from a component that isn't this package's (named by the snapshot), whatever its properties are called.

Livewire runs persistent middleware on a copy of the update request that carries the page's path and no request body, so the middleware reads the update itself from the current request (`ThrottleKanbanMutations::updateRequest()`).

Over the limit, the update doesn't run. The middleware answers `429` with a `Retry-After` header and a JSON body (`message`, `retry_after`), and the page shows "Too many changes in a short time. Try again in N seconds." as a toast instead of Livewire's error dialog (`ecommerce-kanban::partials.rate-limit-notice`).

## Host middleware

`board.middleware` (`[ 'web', 'auth' ]` by default) wraps every board route. Add your 2FA or `verified` middleware there: every class or alias in it is also registered as Livewire persistent middleware, so it runs on each Livewire update from a board page too. Middleware groups are left out; Livewire's update route has its own.

## One-time action tokens

Removing a card from a board, removing a column, and deleting an automation carry a one-time token, minted when the confirmation renders. The token is signed with the app key over the user, the component, the action, and the subject, and expires after 12 hours. Using it writes a row to the engine's `idempotency_records` table (endpoint key prefix `ecommerce-kanban.`), so a second click with the same token is a no-op with a notice. A write that fails releases its token, so the action can be retried. The engine's scheduled `ecommerce:prune-idempotency-records` command clears old rows.

## Registering routes yourself

Set `board.routes_enabled` to `false` and register the routes in your own route file, or embed the components on your own pages. See [Embedding Components](Extending-Embedding-Components). `routes/web.php` in the package is the reference.
