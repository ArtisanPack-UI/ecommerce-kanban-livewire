# ArtisanPack UI Ecommerce Kanban (Livewire) Changelog

## [Unreleased]

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

### Changed

- Requires `artisanpack-ui/ecommerce` `^1.2` (manual card order and the filterable card query, ArtisanPack-UI/ecommerce#198 and #199; board events, card refresh, widget config, and the column service, #200–#203).
- Cards are read through the engine's `KanbanCardQuery`, in their manual order.
- A card action may have both a `url` and an `event`: a plain click dispatches the event and a modified click follows the link. "Open" now opens the slide-out in place.
- Large boards (#20): columns load as they scroll into view (collapsed columns never query), a drop re-renders only its two columns, real-time card events only the columns they name, and polls only the columns whose cards changed. Board load, column paging, card re-renders, polls, and the slide-out have fixed query budgets; a 1,000-card board loads in 24 queries.
