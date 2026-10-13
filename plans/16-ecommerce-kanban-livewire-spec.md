# Package Spec: `artisanpack-ui/ecommerce-kanban-livewire`

**Status:** Draft — ready to build. Issues are open in the [v1.0 milestone](https://github.com/ArtisanPack-UI/ecommerce-kanban-livewire/milestone/1).
**Owner:** Jacob Martella
**Last updated:** 2026-10-13
**Tracker:** [ArtisanPack-UI/ecommerce#54](https://github.com/ArtisanPack-UI/ecommerce/issues/54)
**Parent plan:** `12-ecommerce-package-plan.md` — §9, §10.3, §11.9, §15.4, §16.5, §16.6, §18 Phase 8
**Engine spec:** [`13-ecommerce-engine-spec.md`](https://github.com/ArtisanPack-UI/ecommerce/blob/main/docs/plans/13-ecommerce-engine-spec.md) (referred to below as "engine §N"); engine guide [`docs/kanban.md`](https://github.com/ArtisanPack-UI/ecommerce/blob/main/docs/kanban.md)
**Siblings:** [`15-ecommerce-admin-livewire-spec.md`](https://github.com/ArtisanPack-UI/ecommerce-admin-livewire/blob/main/docs/plans/15-ecommerce-admin-livewire-spec.md) ("admin §N"), [`14-ecommerce-storefront-livewire-spec.md`](https://github.com/ArtisanPack-UI/ecommerce-storefront-livewire/blob/main/plans/14-ecommerce-storefront-livewire-spec.md) ("storefront §N")

## 0. Purpose

This is the build spec for the Livewire kanban satellite: the drag-and-drop order board on top of the headless `artisanpack-ui/ecommerce` engine. It fixes the package identity, how the board reaches engine data, the routes and screens, how cards and columns move, real-time sync, the split with the admin's kanban settings, and the work the engine and `livewire-drag-and-drop` must do first. §15 breaks the build into issues.

When the parent plan and this spec disagree on board behaviour, this spec wins. When this spec and the engine spec disagree on a table, contract, hook, or ability, the engine spec wins.

Out of scope: the admin's kanban settings forms (admin §7.10), sub-status management (admin §7.10), the React/Vue kanbans (plan §10.3), and swimlanes.

## 1. What the parent plan requires

| # | Deliverable (plan §10.3 / Phase 8) | Section |
|---|---|---|
| 1 | Board view with drag-and-drop columns and cards | §7.2, §7.3 |
| 2 | Column config UI (add / reorder / rename, WIP limit, widget config) | §7.8 |
| 3 | Automation config UI | §7.9 |
| 4 | Board picker (switch between multiple boards) | §7.1 |
| 5 | Filter bar (customer, tag, product, date range, search) | §7.5 |
| 6 | Card detail slide-out (mini order view without leaving the board) | §7.6 |
| 7 | Real-time updates via WebSocket (Reverb / Pusher) | §7.7 |
| — | Uses `@artisanpack-ui/livewire-drag-and-drop` (already WCAG 2.1 AA) | §5.5, §10 |
| — | Browser tests: drag card between columns, automation fires (plan §15.4) | §13 |

## 2. Decisions

### 2.1 Locked by the parent plan

- UI base is `artisanpack-ui/livewire-ui-components` only (plan §11.9). Missing primitives are filed against the component library; until they land the kanban composes them inside one Blade component so the swap is one file.
- Drag-and-drop comes from `@artisanpack-ui/livewire-drag-and-drop` (plan §10.3). No custom drag-and-drop primitive in this package.
- Every card move goes through `KanbanBoardService::move()` (docs/kanban.md "Moving cards"): WIP limits, status roll-up, hooks, events, automations, and broadcasting all live in the engine.
- Day-1 locales are `en`, `es`, `fr`, `de`. Every user-facing string goes through `__()` / `trans_choice()`.
- The package registers with the engine's `SatelliteRegistry` and honours the uninstall lifecycle.

### 2.2 Carried over from the admin and storefront specs (#52, #53)

| # | Decision | Outcome |
|---|---|---|
| D1 | Data access | **In-process.** Livewire components call engine services, models, queries, and policies. No HTTP round-trip to `/api/ecommerce/v1`. |
| D2 | Missing domain logic | **In the engine**, as services with REST on top, so the React and Vue kanbans share it. See D7 for the release. §9 lists the issues. |
| D3 | Browser tests | **Pest 4 browser plugin**, so this package is `php: ^8.3` and Pest 4. |
| — | Pages | **Routes + embeddable components** (storefront D4): the package registers board routes (off with `routes_enabled`) whose controller returns page views in a resolved layout; every screen is also a standalone Livewire component. |

### 2.3 Decided for this package (confirmed 2026-10-13)

| # | Decision | Outcome | Why |
|---|---|---|---|
| D4 | Drag-and-drop | **Upgrade `livewire-drag-and-drop`.** v2.0.1 can't move items between containers, has fixed-English announcements, and no veto or rollback. Cross-container groups, keyboard moves between containers, translatable announcements, and a cancel/revert API are filed there (§10, D1–D4); card and column drag issues are blocked on them. | Plan §10.3 names the package and §11.9 rules out custom primitives; the React/Vue kanbans use dnd-kit / Vue.Draggable, so the Livewire stack needs an equivalent of its own. |
| D5 | Card order | **Manual ordering.** Cards keep the position staff drop them at, inside and across columns. The engine adds a position to board assignments and same-column reorders (E1). | Staff expect "next up" at the top of a column; sort-only would snap drops back. |
| D6 | Settings split | **Admin forms + board-native editing.** The admin keeps its CRUD forms for boards, routing rules, columns, and automations (admin D4, §7.10). The kanban adds editing on the board itself: drag to reorder columns, inline label / colour / icon / WIP limit, add a column for a sub-status, remove an empty column, a card-widget picker, and an automations panel that toggles automations and deep-links to the admin's forms. Board-native editing works without the admin installed. | Plan §10.3 asks for config UIs in every kanban; the admin already owns the full forms, and duplicating them would split one set of rules across two packages. |
| D7 | Engine release | **Engine gaps go to the engine's `v1.x` milestone for 1.2.0**; this package requires `artisanpack-ui/ecommerce ^1.2`. | Engine 1.0 and 1.1 have shipped; the additions are additive. |
| D8 | Layout | Resolved in order: `artisanpack.ecommerce-kanban-livewire.layout` config → cms-framework admin layout → the ecommerce admin's layout (when installed) → the package's standalone layout. | The board is a staff screen; it should sit inside whichever admin chrome the host already has. |
| D9 | Real-time | **Echo on the engine's private board channel** when the host has a broadcaster and Echo; **`wire:poll` fallback** (`realtime.poll_interval`) otherwise. The host owns the Echo bootstrap. | Reverb is optional (plan §10.3 says Reverb / Pusher); a board must still converge without it. |
| D10 | Non-drag moves | Every card has a **"Move to…" menu** (column + position) that calls the same action as a drop. | WCAG 2.2 SC 2.5.7 (Dragging Movements) requires a single-pointer alternative. |
| D11 | Front-end build | **No package CSS or JS build.** The host adds the package views to its Tailwind sources and imports `@artisanpack-ui/livewire-drag-and-drop`; board glue is inline Alpine in Blade. The install command prints both lines. | Same as the admin and storefront. |

## 3. Package identity

| Item | Value |
|---|---|
| Composer name | `artisanpack-ui/ecommerce-kanban-livewire` |
| Namespace | `ArtisanPackUI\EcommerceKanbanLivewire\` |
| Service provider | `ArtisanPackUI\EcommerceKanbanLivewire\EcommerceKanbanLivewireServiceProvider` |
| Config file / key | `config/artisanpack/ecommerce-kanban-livewire.php` → `artisanpack.ecommerce-kanban-livewire` |
| View namespace | `ecommerce-kanban` |
| Livewire component names | `artisanpack-ecommerce-kanban-{screen}` (e.g. `artisanpack-ecommerce-kanban-board`) |
| Blade component prefix | `<x-artisanpack-ec-kb-…>` (plan §20 naming, `kb-` to avoid collisions with admin/storefront components) |
| Route names | `artisanpack.ecommerce.kanban.*`; `artisanpack.ecommerce.kanban.boards.show` (param `board`) is fixed — the admin's `Support\KanbanBoards::boardUrl()` links to it |
| Hook prefix | `ap.ecommerceKanbanLivewire.` |
| Translations | `lang/{en,es,fr,de}.json`, loaded with `loadJsonTranslationsFrom()` |
| Publish tags | `ecommerce-kanban-config`, `ecommerce-kanban-views`, `ecommerce-kanban-lang` |
| License | MIT |

## 4. Dependencies

### 4.1 `require`

```json
{
    "php": "^8.3",
    "illuminate/support": "^12.0|^13.0",
    "livewire/livewire": "^3.6|^4.0",
    "artisanpack-ui/ecommerce": "^1.2",
    "artisanpack-ui/livewire-ui-components": "^2.1",
    "artisanpack-ui/core": "^1.0",
    "artisanpack-ui/hooks": "^1.2",
    "artisanpack-ui/security": "^1.0.3|^2.0"
}
```

The scaffold requires `^1.1` (the published engine) so CI is green today; K25 raises it to `^1.2` when the §9 issues ship.

### 4.2 Soft integrations (`suggest` + runtime detection)

| Package | What it enables |
|---|---|
| `artisanpack-ui/ecommerce-admin-livewire` | Layout (D8), "Open in admin" links from a card, deep links from the automations panel and column settings to the admin's forms, the admin nav entry. |
| `artisanpack-ui/cms-framework` | Layout (D8) and a cms admin menu entry. |
| `laravel/reverb` (or any broadcaster + Laravel Echo) | Real-time sync (§7.7). |
| Satellites that register widgets or triggers | Rendered and listed automatically through `KanbanCardWidgetRegistry` / `KanbanAutomationRegistry`. |

### 4.3 Front-end

- `@artisanpack-ui/livewire-drag-and-drop` (npm, version from D1–D3) imported by the host; the install command prints the import.
- Laravel Echo, when present, is read from `window.Echo`; nothing is bundled.
- A `package.json` / `vite.config.js` exists only for the browser-test workbench (export-ignored), as in the admin and storefront.

### 4.4 `require-dev`

Same as the storefront: Pest 4 + `pest-plugin-laravel`, `pest-plugin-livewire`, `pest-plugin-browser`, Testbench `^10.2|^11.0`, code-style, code-style-pint, php-cs-fixer, phpcs installer. Do not run Pint in this package.

## 5. Architecture

### 5.1 Layers

```
routes/web.php ─► BoardScreenController ─► page view (@extends resolved layout)
                                             └─ <livewire:artisanpack-ecommerce-kanban-board :board=…/>
Livewire components (src/Livewire)
  ├─ reads:  engine KanbanCardQuery (E2), KanbanCardRenderer, SubStatusRegistry, models
  ├─ writes: KanbanBoardService::move() (E1 placement), KanbanRoutingService::assign()/remove(),
  │          KanbanColumnService (E6), widget config (E5), KanbanAutomation toggles
  └─ auth:   EcommerceAuthorizer abilities (§6)
Alpine (inline) ─► livewire-drag-and-drop events ─► $wire.moveCard(...)  /  Echo listeners ─► $wire.refresh*(...)
```

- Reads live in `src/Queries/` thin wrappers around engine queries so tests can count queries.
- Every write catches `KanbanOperationException` (codes `not-on-board`, `wip-limit-reached`, `move-rejected`, `column-not-on-board`, `invalid-status-transition`, `incompatible-column`, `no-entry-column`) and maps it to a translated toast plus a client-side revert (D3 revert API).

### 5.2 Routes

| Route | Name | Screen |
|---|---|---|
| `GET /{prefix}` | `artisanpack.ecommerce.kanban.boards.index` | Board picker / default board redirect (§7.1) |
| `GET /{prefix}/boards/{board}` | `artisanpack.ecommerce.kanban.boards.show` | Board (§7.2), `?card={order}` opens the slide-out, filters in the query string |

Prefix and middleware come from `board.route_prefix` / `board.middleware` (default `ecommerce-kanban`, `['web', 'auth']`). Non-group middleware is also registered as Livewire persistent middleware, as in the admin. Livewire mutations count toward the engine's `ecommerce.admin.mutate` limiter.

### 5.3 Layout

`Support\LayoutResolver` implements D8. The standalone layout is a minimal full-width page (header with board picker, content, toasts) using `livewire-ui-components`. The board needs full width; the resolver passes a `fullWidth` flag the admin layout already honours, or the page view wraps itself.

### 5.4 Navigation and links

- Registers a "Kanban" entry in the engine's `AdminMenuRegistry` (section `orders`, permission `kanbanBoard.viewAny`, `satellite` = package name) so the admin and cms menus pick it up.
- The admin already links to `boards.show` from its kanban settings; admin #69 (A1) adds per-order links that pass `?card=`.
- A card's slide-out links to the admin order screen when the admin is installed (`artisanpack.ecommerce.admin.orders.show`).

### 5.5 Board client model

- Each column is an `x-drag-context` in group `cards`, with `data-drag-label` = column label; each card is an `x-drag-item` keyed by order id. The column strip is an outer context in group `columns` (only when the user may edit columns).
- On `drag:drop` the client vetoes drops into a column at its WIP limit (fast path; the engine still enforces). On `drag:end` it calls `$wire.moveCard(orderId, toColumnId, beforeOrderId, afterOrderId)`; on failure the component dispatches a revert for that `moveId`.
- Server state is authoritative: after every move the affected columns re-render from the query; Livewire morphing keeps focus on the moved card.

### 5.6 Config

```php
'board' => [ 'routes_enabled' => true, 'route_prefix' => 'ecommerce-kanban', 'middleware' => [ 'web', 'auth' ] ],
'layout' => null,                                   // D8 override (view name)
'cards' => [ 'per_column' => 25 ],                  // cursor page size per column
'realtime' => [ 'enabled' => true, 'poll_interval' => 30 ],
```

## 6. Authorization

All checks go through the engine's `EcommerceAuthorizer`:

| Action | Ability |
|---|---|
| See the board picker / a board / its cards | `kanbanBoard.viewAny` / `kanbanBoard.view` (also authorizes the broadcast channel) |
| Move or reorder a card | `kanbanCard.move` |
| Add / remove an order on a board | `kanbanBoard.update` |
| Board-native column, widget, and automation edits | `kanbanBoard.update` |
| Card slide-out order details | `order.view`; customer PII follows the admin's masking rules |

Mutating Livewire actions re-check abilities server-side and use one-time action tokens for destructive actions (remove column, remove card from board), as the admin does.

## 7. Screens

### 7.1 Board picker (`boards.index`)

- Lists active boards the user can view, ordered by `position`, with card counts; inactive boards hidden.
- Remembers the last board per user (session) and redirects there; `?pick=1` shows the list.
- Empty state when there are no boards, linking to the admin's "Create board" when installed (`livewire-ui-components` #111 when it lands).
- The board header carries the same picker as a dropdown.

### 7.2 Board (`boards.show`)

- Horizontal scrolling columns in `position` order, each with label / colour / icon (overrides then sub-status), card count, WIP limit indicator (`n / limit`, warning tone at the limit), and a collapse toggle (remembered per board in `localStorage`).
- Cards per column from `KanbanCardQuery` (E2), ordered by position (E1), cursor-paginated ("Show more" per column); counts are the filtered counts with the real count shown when a filter hides cards.
- Card: order number, customer, placed date, and the widget payloads from `KanbanCardRenderer` (column widgets → board defaults → config). Payload → Blade: `tone` maps to badge colours, `icon` to `x-artisanpack-icon`, `tooltip` to a tooltip, `href` to a link. Unknown widget keys render the generic payload view; satellites can override a widget's view through `ap.ecommerceKanbanLivewire.widgetView`.
- Card actions menu: Open, Move to…, Remove from board, plus actions from `ap.ecommerceKanbanLivewire.cardActions`.
- `?card={order}` scrolls to and focuses the card and opens §7.6.

### 7.3 Moving cards

- Drag between columns (D1 #4) and within a column (E1 #198) through `KanbanBoardService::move()` with a placement.
- Optimistic: the card stays where it was dropped while the request runs (loading state on the card); on rejection it reverts (D3 #6) and a toast explains why (WIP limit, transition not allowed, vetoed by a hook).
- Keyboard: grab with Space, move between columns with Left/Right and within with Up/Down, drop with Space, cancel with Escape (D2 #5); announcements are translated and name the order, column, and position.
- "Move to…" (D10): dialog with column select and position (top / bottom / after card) — works without drag-and-drop entirely.
- Moving to an exit column (`cancelled`, `refunded`, `failed`) asks for confirmation first, because it changes the whole order's status (docs/kanban.md "Status roll-up").

### 7.4 Card ordering

- New cards appear at the engine's configured end of the entry column (E1).
- Reorder within a column by drag or "Move to…" with a position; no status change, no automations.

### 7.5 Filter bar

- Search (order number, email, customer name), customer (`x-artisanpack-choices` with server search), tags (options from the E2 tag helper), products (server-searched choices), placed date range (`livewire-ui-components` #113; until then two date pickers), "stale only" (moved before `stale_after_days`), payment and fulfillment status.
- Filters live in the query string (`#[Url]`), so links are shareable; "Clear filters"; active-filter count on mobile where the bar collapses into a drawer.
- Satellites add filters through `ap.ecommerceKanbanLivewire.filters` paired with the engine's `ap.ecommerce.kanban.cardQuery` (E2).

### 7.6 Card detail slide-out

- `x-artisanpack-drawer` (right) with: order number, status (system + this board's column), customer and contact (masked per admin rules), shipping address, lines (name, SKU, qty, total), totals, payment and fulfillment status, the order's other board assignments (board → column), and the latest timeline entries.
- Actions: move to column, add to / remove from boards (§7.6.1), "Open in admin" when the admin is installed.
- Lazy-loaded when opened; `?card=` keeps it linkable; focus is trapped and returned to the card on close.

#### 7.6.1 Board assignments

- Add the order to another board (`KanbanRoutingService::assign()`, optional entry column) or remove it from this board (`remove()`); confirmations and toasts.
- "Add order" on the board header: search orders not on this board and assign them.

### 7.7 Real-time

- When `realtime.enabled`, Echo is present, and the engine's `kanban.broadcast` is on: subscribe to `private-ecommerce.kanban.board.{board}` and handle the E3 events — card moved / reordered / added / removed refresh the affected columns; config changed refreshes the board shell; automation fired / failed shows a toast to the actor (§7.10); card updated (E4) re-renders that card.
- Events carrying the current user's `actor_user_id` for an in-flight optimistic move are ignored.
- A card being dragged when a remote move for it arrives: the drag is cancelled with an announcement and the board refreshes.
- Without Echo: `wire:poll.{poll_interval}s` on the columns (pauses while dragging or while the tab is hidden). A small connection indicator shows live / polling / offline.

### 7.8 Board-native column editing (D6)

For users with `kanbanBoard.update`, an "Edit board" mode:

- Drag column headers to reorder (`KanbanColumnService::reorder()`, E6; D1 #4 for nested contexts); keyboard alternative "Move left / right".
- Inline edit label override, colour, icon, WIP limit.
- Add a column: pick a sub-status not yet on the board (grouped by system status); remove a column (refused with the card count when cards are on it, or move them first).
- Card widget picker per column and for the board default: ordered list of registered widgets (drag or up/down), per-instance config form generated from `configSchema()` (E5).
- "Advanced settings" deep-links to the admin's board edit screen when the admin is installed.

### 7.9 Automations panel (D6)

- Per board and per column ("into this column" / "out of this column"): trigger label, from → to, conditions summary, active toggle.
- Toggle active and delete in place; "Add" / "Edit" deep-link to the admin's automation forms when the admin is installed. Without the admin the panel is list + toggle only (open question 1).

### 7.10 Automation feedback

- After the current user's move, toasts for automations that fired or failed for that card (from E3 events, or from the timeline entries written in the same request when broadcasting is off).

## 8. Shared components

| Component | Purpose |
|---|---|
| `x-artisanpack-ec-kb-column` | Column header (label, colour, icon, counts, WIP indicator, collapse, menu) + card list context |
| `x-artisanpack-ec-kb-card` | Card shell (number, customer, date, widgets, actions menu, loading/reverting states) |
| `x-artisanpack-ec-kb-widget` | Generic widget payload renderer; per-key view override through the filter |
| `x-artisanpack-ec-kb-wip` | `n / limit` indicator with text equivalent |
| `x-artisanpack-ec-kb-connection` | Live / polling / offline indicator |
| `x-artisanpack-ec-kb-empty` | Empty states (no boards, empty column, no matches) — swaps to `livewire-ui-components` #111 |

## 9. Engine prerequisites

Verified against engine `release/1.x` (HEAD `8a78eb0`, v1.1.0). Milestone `v1.x` → 1.2.0 (D7).

| # | Gap | Blocks |
|---|---|---|
| E1 ([ecommerce#198](https://github.com/ArtisanPack-UI/ecommerce/issues/198)) | No card position on `order_board_assignments`; same-column move is a no-op | K07 (soft), K08 |
| E2 ([ecommerce#199](https://github.com/ArtisanPack-UI/ecommerce/issues/199)) | No filterable card query (search, customer, tag, product, date) or filtered per-column counts | K05 (soft), K10 |
| E3 ([ecommerce#200](https://github.com/ArtisanPack-UI/ecommerce/issues/200)) | Only card moves are broadcast | K13, K18 |
| E4 ([ecommerce#201](https://github.com/ArtisanPack-UI/ecommerce/issues/201)) | No card refresh when the order changes; `refreshSubscription()` unused | K14 |
| E5 ([ecommerce#202](https://github.com/ArtisanPack-UI/ecommerce/issues/202)) | No per-instance widget config; `default_config` unused | K16 |
| E6 ([ecommerce#203](https://github.com/ArtisanPack-UI/ecommerce/issues/203)) | Column writes only in the REST controller; no bulk reorder | K15 |

Not blocked: board picker, board view without filters, card rendering, "Move to…" across columns, slide-out, assignments, automations panel. These use existing models and services (`KanbanBoardService::move()`, `KanbanRoutingService`, `KanbanCardRenderer`, `KanbanAutomation`).

Noted, not filed for this package: the engine spec §10 GraphQL kanban queries and mutations (`kanbanBoards`, `kanbanCards`, `moveKanbanCard`) are missing; only the `kanbanCardMoved` subscription exists. The Livewire kanban doesn't need them (D1); they belong with the React/Vue kanban trackers.

## 10. `livewire-drag-and-drop` prerequisites

Package `@artisanpack-ui/livewire-drag-and-drop` v2.0.1, milestone `v2.1`.

| # | Missing | Used by |
|---|---|---|
| D1 ([livewire-drag-and-drop#4](https://github.com/ArtisanPack-UI/livewire-drag-and-drop/issues/4)) | Cross-container groups, drop into empty containers, source/target in `drag:end`, nested contexts, pointer/touch, edge auto-scroll | K07, K08, K15 |
| D2 ([livewire-drag-and-drop#5](https://github.com/ArtisanPack-UI/livewire-drag-and-drop/issues/5)) | Keyboard moves between containers, translatable announcements, no `aria-grabbed` | K09 |
| D3 ([livewire-drag-and-drop#6](https://github.com/ArtisanPack-UI/livewire-drag-and-drop/issues/6)) | `drag:start` / `drag:cancel` / cancelable `drag:drop`, revert API | K07 |
| D4 ([livewire-drag-and-drop#7](https://github.com/ArtisanPack-UI/livewire-drag-and-drop/issues/7)) | README doesn't match the code (bug) | — |

## 11. `livewire-ui-components` prerequisites

No new issues. The board composes Card, Badge, Avatar, Drawer, Dropdown, Choices, DatePicker, Toast, Tooltip, and Icon. Existing open issues it benefits from (all soft, with in-package fallbacks): empty state [#111](https://github.com/ArtisanPack-UI/livewire-ui-components/issues/111), date-range picker [#113](https://github.com/ArtisanPack-UI/livewire-ui-components/issues/113), skeleton [#124](https://github.com/ArtisanPack-UI/livewire-ui-components/issues/124).

## 12. Localization, accessibility, performance

- **Localization:** all strings through `__()` / `trans_choice()`; `es`, `fr`, `de` catalogues complete; drag announcements passed from Blade (D2); dates through the engine's localized date helper; translation lint in CI.
- **Accessibility (WCAG 2.2 AA):** columns are labelled regions; cards are focusable with an accessible name ("Order 1042, Jane Doe, 3 items, $124.00"); drag has a keyboard path (D2) and a single-pointer alternative (D10, SC 2.5.7); WIP and tone are never colour-only; toasts are announced; focus management for the slide-out and dialogs; reduced-motion respected; target size ≥ 24px (SC 2.5.8).
- **Performance:** fixed query count per column render (E2 eager loading); columns paginate; collapsed columns don't load cards; widgets render once per card per request; polling and Echo refreshes only re-render affected columns; a 1,000-card board stays interactive (K20 budget).

## 13. Testing

- **Feature (Pest + `Livewire::test()`):** every component — render, abilities, moves (happy path and each `KanbanOperationException` code), filters, slide-out, assignments, board-native edits, automation toggles, uninstalled satellite wires nothing.
- **Query budgets:** query-count assertions for board and column renders.
- **Browser (Pest 4 browser plugin, `--group=browser`, CI job):** drag a card between columns (plan §15.4) and see the automation fire (toast + timeline); reorder within a column; keyboard move; "Move to…"; WIP limit refusal reverts; filter by tag + date; open the slide-out from `?card=`; two-browser real-time move (with a fake broadcaster or polling); axe checks on the board and slide-out.
- CI matrix as scaffolded (PHP 8.3/8.4 × Laravel 12/13 × Livewire 3/4 + lowest), `composer lint`, translation lint, PCI column lint, satellite verification.

## 14. Satellite lifecycle

```php
[
    'package_name'    => 'artisanpack-ui/ecommerce-kanban-livewire',
    'version'         => EcommerceKanbanLivewireServiceProvider::VERSION,
    'label'           => __( 'Kanban board (Livewire)' ),
    'migration_paths' => [],
    'config_keys'     => [ 'artisanpack.ecommerce-kanban-livewire' ],
    'meta_namespaces' => [],
    'tables'          => [],
    'columns'         => [],
    'product_types'   => [],
]
```

- Boards, columns, automations, widgets, and assignments live in the engine's tables, so the package owns no data and ships no uninstaller. Uninstalling removes the board routes, nav entry, and listeners; boards keep working through the admin's settings and the REST API.
- It registers no engine contract implementations (no widgets, no triggers), so satellite verification runs with `--allow-empty`. A future widget shipped here must extend `KanbanCardWidgetContractTest`.

## 15. Delivery plan

K-numbers match issue numbers in this repo. "Needs" lists §9/§10 prerequisites; "soft" means the issue can ship with a fallback first.

### M0 — Foundation

| # | Issue | Type | Needs |
|---|---|---|---|
| K01 | Package scaffold: composer, provider, config, code style, CI, SatelliteRegistry registration | task | — (done) |
| K02 | Board shell: routes, screen controller, layout resolver, standalone layout, navigation | feature | — |
| K03 | Install command: `ecommerce-kanban:install` and front-end setup | task | — |
| K04 | Board picker and board switcher | feature | — |

### M1 — Board and moves

| # | Issue | Type | Needs |
|---|---|---|---|
| K05 | Board view: columns, card lists, counts, WIP indicators, per-column paging | feature | E2 (soft) |
| K06 | Card and widget rendering, card actions menu, widget view overrides | feature | — |
| K07 | Drag cards between columns with optimistic moves and rollback | feature | D1, D3, E1 (soft) |
| K08 | Reorder cards within a column | feature | E1, D1 |
| K09 | Keyboard moves and the "Move to…" dialog | feature | D2 (drag path only) |
| K10 | Filter bar with URL state | feature | E2 |
| K11 | Card detail slide-out | feature | — |
| K12 | Add and remove orders on a board | feature | — |

### M2 — Real-time

| # | Issue | Type | Needs |
|---|---|---|---|
| K13 | Real-time board sync via Echo with polling fallback | feature | E3 |
| K14 | Live card refresh when an order changes | feature | E4 |

### M3 — Board-native editing

| # | Issue | Type | Needs |
|---|---|---|---|
| K15 | Edit board mode: reorder, add, edit, and remove columns | feature | E6, D1 |
| K16 | Card widget picker with per-instance config | feature | E5 |
| K17 | Automations panel: list, toggle, admin deep links | feature | — |
| K18 | Automation feedback toasts after a move | feature | E3 (soft) |

### M4 — Hardening and release

| # | Issue | Type | Needs |
|---|---|---|---|
| K19 | Extension surface: hooks, filters, card actions, documented and tested | feature | — |
| K20 | Performance: query budgets, lazy columns, large boards | task | E2 |
| K21 | `es`, `fr`, `de` catalogues + translation lint | task | — |
| K22 | Accessibility audit (WCAG 2.2 AA) and fixes | task | D2 |
| K23 | Browser test suite (Pest 4 browser plugin) and CI job | task | — |
| K24 | Documentation: README, installation, configuration, real-time setup, extending | documentation | — |
| K25 | v1.0 release: engine `^1.2`, changelog, verify-satellite, engine docs | task | E1–E6 |

**Critical path:** D1/D3 (drag-and-drop) → K07 → K08/K15; E2 → K10/K20; E3 → K13. K02–K06, K09's "Move to…", K11, K12, and K17 can start now.

## 16. Open questions

1. Without the admin installed, should the automations panel (§7.9) get a compact create/edit form built from `configSchema()`, or stay list + toggle? Defaulted to list + toggle for 1.0.
2. Per-user board preferences (last board, collapsed columns) are session + `localStorage`. A persisted per-user store would follow the engine's settings API if one lands.
3. Swimlanes (rows by customer, priority, or tag) are out of scope for 1.0.

## 17. Traceability against tracker #54

| Acceptance criterion | Status |
|---|---|
| Repo created at `ArtisanPack-UI/ecommerce-kanban-livewire` | Done — scaffold pushed (K01) |
| Per-package spec drafted | This document |
| Comprehensive issue set opened on the new repo (with `ecosystem: ecommerce` label) | K01–K25, milestone v1.0 |
| Tracker closed with a link to the new repo | On filing |

## 18. Changelog

- 2026-10-13 — First draft. Decisions D4–D11 confirmed; engine #198–#203, livewire-drag-and-drop #4–#7, and admin #69 filed.
