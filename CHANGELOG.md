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

### Changed

- Requires `artisanpack-ui/ecommerce` `^1.2` (manual card order and the filterable card query, ArtisanPack-UI/ecommerce#198 and #199).
- Cards are read through the engine's `KanbanCardQuery`, in their manual order.
- A card action may have both a `url` and an `event`: a plain click dispatches the event and a modified click follows the link. "Open" now opens the slide-out in place.
