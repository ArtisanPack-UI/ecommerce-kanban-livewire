# ArtisanPack UI Ecommerce Kanban (Livewire) Changelog

## [Unreleased]

### Added

- Package scaffold: service provider, config, and `SatelliteRegistry` registration.
- Board routes (`artisanpack.ecommerce.kanban.boards.index` / `.show`), screen controller, layout resolver (config → cms-framework → ecommerce admin → standalone), standalone layout, rate-limited Livewire updates, and the "Kanban" admin menu entry (#2).
- `php artisan ecommerce-kanban:install`: publishes the config, prints the Tailwind and drag-and-drop lines, and checks the engine, boards, and broadcasting (#3).
- Board picker that returns to the last board, plus a board switcher in the board header (#4).
- Board view: columns with counts, WIP indicators, collapse (remembered per board), and "Show more" paging (#5).
- Cards and widget rendering with tone, icon, tooltip, and link; the `ap.ecommerceKanbanLivewire.widgetView` and `ap.ecommerceKanbanLivewire.cardActions` filters; and the card actions menu (#6).
