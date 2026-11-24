---
title: Advanced
menu_order: 80
---

# Advanced

Access control, internals, and how the board keeps destructive actions from running twice. The Artisan commands are in the [reference](reference/commands.md). Running the package's own test suites is covered in [CONTRIBUTING.md](https://github.com/ArtisanPack-UI/ecommerce-kanban-livewire/blob/main/CONTRIBUTING.md).

## In this section

- [Authorization](advanced/authorization.md) - The abilities each screen and action needs
- [Performance](advanced/performance.md) - Lazy columns, partial re-renders, and query budgets

## How a request flows

```text
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
  - **Events** (`ThrottleKanbanMutations::READ_EVENTS`): a Livewire event dispatched to one of this package's components whose listener only reads. That covers opening a card's slide-out (`ec-kb-open-card`), "Move to…" (`ec-kb-move-card`), the "Remove from board" confirmation (`ec-kb-remove-card`), opening the widget picker (`ec-kb-edit-widgets`) and the automations panel (`ec-kb-edit-automations`), and the real-time and poll refreshes the board sends itself (`ec-kb-card-updated`, `ec-kb-board-refreshed`, `ec-kb-board-changed`). Before 2.0 these used up the user's change budget.
- **Counted:** every other update: moves, adding and removing cards, column and widget edits, automation changes, any update from a component that isn't this package's (named by the snapshot), whatever its properties are called, and any dispatched event that isn't on the read list, including a satellite's own events (for example a card action's `event`).

Livewire runs persistent middleware on a copy of the update request that carries the page's path and no request body, so the middleware reads the update itself from the current request (`ThrottleKanbanMutations::updateRequest()`).

Over the limit, the update doesn't run. The middleware answers `429` with a `Retry-After` header and a JSON body (`message`, `retry_after`), and the page shows "Too many changes in a short time. Try again in N seconds." as a toast instead of Livewire's error dialog (`ecommerce-kanban::partials.rate-limit-notice`).

## Host middleware

`board.middleware` (`[ 'web', 'auth' ]` by default) wraps every board route. Add your 2FA or `verified` middleware there: every class or alias in it is also registered as Livewire persistent middleware, so it runs on each Livewire update from a board page too. Middleware groups are left out; Livewire's update route has its own.

## One-time action tokens

Removing a card from a board, removing a column, and deleting an automation carry a one-time token, minted when the confirmation renders. The token is signed with the app key over the user, the component, the action, and the subject, and expires after 12 hours. Using it writes a row to the engine's `idempotency_records` table (endpoint key prefix `ecommerce-kanban.`), so a second click with the same token is a no-op with a notice. A write that fails releases its token, so the action can be retried. The engine's scheduled `ecommerce:prune-idempotency-records` command clears old rows.

## Registering routes yourself

Set `board.routes_enabled` to `false` and register the routes in your own route file, or embed the components on your own pages. See [Embedding Components](extending/embedding-components.md). `routes/web.php` in the package is the reference.

## Content Security Policy

The board isn't compatible with a strict Content Security Policy in 2.0:

- **Scripts:** the board, its cards, columns, filter bar, and automations panel use inline Alpine expressions (`x-data` objects and multi-statement `x-on` handlers). They need Alpine's standard build, which evaluates expressions with `new Function`, so your `script-src` must allow `'unsafe-eval'`. Alpine's CSP build and Livewire 4's `csp_safe` mode don't evaluate them: the board renders, but nothing on it works (no dragging, no lazy columns, dialogs don't close).
- **Styles:** a column's color reaches its header dot through an inline `style` attribute, and livewire-ui-components also renders inline `style` attributes, so `style-src-attr` (or `style-src`) must allow `'unsafe-inline'`.
- **Nonces:** the board's inlined `<style>` (`ecommerce-kanban::partials.styles`) and the rate-limit notice's `<script>` carry the Vite CSP nonce (`Vite::useCspNonce()`) when you set one, so a nonce-based `script-src` and `style-src` work for those elements. livewire-ui-components' toaster renders an inline script without a nonce ([livewire-ui-components#163](https://github.com/ArtisanPack-UI/livewire-ui-components/issues/163)), so with a nonce-only `script-src` the board's toasts don't appear until that is fixed.

A later release may move the board's Alpine code into a shipped module so it can run under the CSP build.
