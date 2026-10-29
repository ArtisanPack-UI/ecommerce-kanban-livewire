# ArtisanPack UI Ecommerce Kanban (Livewire) Changelog

## [Unreleased]

## [1.0.0] - 2026-10-29

The first release of the Livewire order board for the ArtisanPack UI ecommerce
engine. Orders show as cards in columns by sub-status and move by drag-and-drop,
keyboard, or a "Move to…" dialog. The package also covers filtering, a card
slide-out, adding and removing orders, board-native column and card-widget
editing, an automations panel, and real-time sync. Every change goes through
the engine's services.

### Requirements

- PHP 8.3+, Laravel 12 or 13, Livewire 3.6+ or 4.
- `artisanpack-ui/ecommerce` `^1.2`: manual card order, the filterable card query, board events, card refresh, widget config, and the column service (ArtisanPack-UI/ecommerce#198–#203).
- `artisanpack-ui/livewire-ui-components` `^2.1`, `artisanpack-ui/core` `^1.0`, `artisanpack-ui/hooks` `^1.2`, and `@artisanpack-ui/livewire-drag-and-drop` `^2.1` on the front end.

### Added

- Package scaffold: service provider, config, and `SatelliteRegistry` registration.
- Board routes (`artisanpack.ecommerce.kanban.boards.index` / `.show`), screen controller, layout resolver (config → cms-framework → ecommerce admin → standalone), standalone layout, rate-limited Livewire updates, and the "Kanban" admin menu entry (#2).
- `php artisan ecommerce-kanban:install`: publishes the config, prints the Tailwind and drag-and-drop lines, and checks the engine, boards, and broadcasting (#3).
- Board picker that returns to the last board, plus a board switcher in the board header (#4).
- Board view: columns with counts, WIP indicators, collapse (remembered per board), and "Show more" paging (#5).
- Cards and widget rendering with tone, icon, tooltip, and link; the `ap.ecommerceKanbanLivewire.widgetView` and `ap.ecommerceKanbanLivewire.cardActions` filters; and the card actions menu (#6).
- Drag cards between columns through `KanbanBoardService::move()`: optimistic drops that revert with a translated toast when the engine refuses, a client-side WIP-limit veto, and a confirmation before a move into an exit column (cancelled, refunded, failed) (#7).
- Reorder cards within a column by drag, kept in the engine's manual card order (#8).
- "Move to…" dialog (column + top / bottom / after a card) for every card, and translated drag-and-drop keyboard instructions and announcements (#9).
- Filter bar with search, customer, tags, products, placed date range, stale cards, and payment and fulfillment status, kept in the URL; filtered and real column counts; a drawer on small screens; and the `ap.ecommerceKanbanLivewire.filters` filter for satellite controls (#10).
- Card slide-out (`artisanpack-ecommerce-kanban-card-detail`) with the order summary, its other boards, recent activity, "Move to…", and "Open in admin"; `?card={order}` opens it (#11).
- Add and remove orders on a board (#12): "Add order" in the board header searches orders that aren't on the board and assigns one to a chosen column or the engine's entry column; "Remove from board" (card menu and slide-out) asks first and runs once per action token; the slide-out adds the order to another board. Engine refusals show as toasts.
- Real-time board sync (#13): with `realtime.enabled`, Laravel Echo, and the engine's `kanban.broadcast`, the board listens for `kanbanBoardEvent` on `private-ecommerce.kanban.board.{board}`, ignores the echo of the user's own in-flight move, cancels a drag whose card moved elsewhere, and leaves the channel on navigation; otherwise it polls every `realtime.poll_interval` seconds (paused while dragging or when the tab is hidden; 0 turns it off). `x-artisanpack-ec-kb-connection` shows live / polling / offline.
- Live card refresh (#14): `card-updated` events re-render just the affected cards (batched, so a card re-renders once) and the slide-out when it shows one of them.
- "Edit board" mode (#15) with `kanbanBoard.update`: drag column headers or use "Move left / right" to reorder, edit a column's label, color, icon, and WIP limit with the engine's validation messages inline, add a column for a sub-status not on the board (grouped by system status), remove a column (its cards moved to a chosen column first; once per action token), and an "Advanced settings" link to the admin's board screen.
- Card widget picker (`artisanpack-ecommerce-kanban-widget-picker`, #16) for each column and the board default: registered widgets with label and provider, add / remove / reorder (drag or up / down), settings forms from each widget's `configSchema()`, which list applies, and a live preview on a card from the board.
- Automations panel (`artisanpack-ecommerce-kanban-automations`, #17) for the whole board and per column ("into this column" / "out of this column"): trigger label, from → to, a conditions summary, and whether it is on. With `kanbanBoard.update`, switch an automation on or off and delete it (confirmed, once per action token); "Add" and "Edit" link to the admin's automation forms when the admin is installed. Column headers show how many active automations fire into the column.
- Automation feedback after a move (#18): one toast per move naming the automations that ran, or an error naming each failed trigger with a link to the order's slide-out. Read from the timeline entries the move wrote, or from the engine's `automation-fired` / `automation-failed` broadcasts while the board is live; only the person who moved the card sees it.
- Extension surface (#19), listed in `Support\Hooks`: the new `ap.ecommerceKanbanLivewire.boardToolbar` (header buttons) and `ap.ecommerceKanbanLivewire.cardClasses` (card CSS classes) filters, and the `ap.ecommerceKanbanLivewire.boardRendered` and `ap.ecommerceKanbanLivewire.cardMoveFailed` actions.
- Spanish, French, and German translations for every new string, and tests that every catalogue has every key and placeholder and that plural strings resolve for 0, 1, and many (#21).
- Browser test suite (#23): a workbench (`package.json`, `vite.config.js`, `npm run build:browser`, `composer test:browser`) with a seeded demo board; journeys for dragging a card between columns with the automation firing, reordering within a column, keyboard moves, "Move to…", the exit-column confirmation, WIP refusals, filtering by tag and date, `?card=`, reordering columns in edit mode, and a move made in another browser; axe checks on every screen and dialog (light, dark, phone); and a CI `browser` job for Livewire 3 and 4.
- Documentation in `docs/` and a new README with screenshots (#24).
- The card slide-out describes the engine's automation timeline entries ("Automation ran: …", "Automation failed: …", "Order field … updated") in every catalogue.
- Cards read through the engine's `KanbanCardQuery`, in their manual order.
- A card action may have both a `url` and an `event`: a plain click dispatches the event and a modified click follows the link. "Open" opens the slide-out in place.
- Large boards (#20):
  - Columns load as they scroll into view, and collapsed columns never query.
  - A drop re-renders only its two columns, real-time card events only the columns they name, and polls only the columns whose cards changed.
  - Board load, column paging, card re-renders, polls, and the slide-out have fixed query budgets; a 1,000-card board loads in 24 queries.
- WCAG 2.2 AA (#22):
  - Cards are focusable groups, and columns are labelled regions.
  - Neutral widget badges use an outline in the text color (dark-mode contrast).
  - The date filters are labelled, and the card pulse and transitions stop under reduced motion.
  - The standalone layout's sticky header has scroll padding so focus isn't hidden under it.
  - The "Filters" toggle only shows on small screens.
  - The tags filter is replaced rather than morphed when its options load (livewire-ui-components#135).
- Rate limiting that counts only changes: page loads, polls, lazy column loads, real-time refreshes, automation feedback, filtering, searching, and opening or closing dialogs don't count against `ecommerce.admin.mutate`. The read lists only apply to this package's components.

### Fixed

Found in the 1.0.0 release-readiness review (#31) and fixed before release:

- **Removing columns:**
  - When the engine won't move a removed column's cards to the chosen column (for example, an illegal status change), the dialog says why instead of failing with a server error.
  - Moving them into an exit column (cancelled, refunded, failed) needs an extra confirmation, as a drop does.
- **Rate limiting:** the rate limiter reads the Livewire update from the current request. As persistent middleware it receives a copy without the request body, so before this fix every board update counted, reads included.
- **Failed requests:** a drop or column reorder whose request fails (rate limited, server error) goes back where it was, and a move that never gets an answer is put back after 20 seconds.
- **Access:**
  - A switched-off board opens only for people who can edit it, with a notice, and is never remembered as the last board.
  - The widget picker needs `kanbanBoard.update` to open and edit, however it is opened.
  - The slide-out's "Other boards" lists only boards the viewer can see.
  - The filter option lists are locked to the server.
  - The rate-limit toast escapes its text.
- **Accessibility:**
  - The automation switch is labelled "Active" and sets the state rather than flipping it.
  - The automation delete confirmation takes focus and gives it back.
  - Every dialog returns focus to what opened it, however it is closed (Escape and backdrop included).
  - The slide-out announces only status and column changes; empty columns are no longer live regions, and the board announces how many orders match the filters.
  - Read-only card lists are named groups, and "Show more" moves focus to the first new card.
  - The connection status shows the real poll interval ("Updates every 30 seconds").
- **Smaller fixes:**
  - "After a card" in "Move to…" says when its list is cut short at 50 cards.
  - Widget tone badge classes are written out in full for Tailwind.
  - `textarea` widget settings render as a textarea.
