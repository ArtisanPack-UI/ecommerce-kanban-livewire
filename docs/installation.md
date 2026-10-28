---
title: Installation Overview
---

# Installation

Everything you need to install the board and wire it into your application.

## In this section

- [Requirements](Installation-Requirements) - PHP, Laravel, Livewire, the engine, npm packages, and optional packages
- [Configuration](Installation-Configuration) - Every configuration key
- [Front-End Setup](Installation-Front-End) - Tailwind sources, daisyUI, and the drag-and-drop import
- [Layouts](Installation-Layouts) - Which layout the board pages use, and how to use your own

## Install via Composer

```bash
composer require artisanpack-ui/ecommerce-kanban-livewire
```

The service provider is auto-discovered. The package has no migrations: boards, columns, cards, widgets, and automations are the engine's tables, read and written through the engine's services.

## Run the install command

```bash
php artisan ecommerce-kanban:install
```

It:

1. Publishes the config to `config/artisanpack/ecommerce-kanban-livewire.php` (tag `ecommerce-kanban-config`). A file you already published is kept; `--force` overwrites it.
2. Prints the Tailwind `@source` lines to add to your main stylesheet. See [Front-End Setup](Installation-Front-End).
3. Prints the drag-and-drop install command (`npm install @artisanpack-ui/livewire-drag-and-drop@^2.1.0`) and the line to add to your main script (`import '@artisanpack-ui/livewire-drag-and-drop';`).
4. Reports the installed `artisanpack-ui/ecommerce` version and warns when it is older than 1.2.0.
5. Counts the active boards. With none, it says where to create one: the ecommerce admin's board list (when the admin is installed) and the engine's REST route (`POST /api/ecommerce/v1/kanban/boards`).
6. Reports whether real-time sync can work: the engine's `artisanpack.ecommerce.kanban.broadcast` flag and the default broadcaster. When either is missing, it says that boards will poll instead and what to turn on. See [Real-Time Updates](Realtime).

The command never edits your stylesheet or script, and the checks only warn. See [Artisan Commands](Advanced-Artisan-Commands).

## Publishing

| Tag | Publishes to | Use it to |
| --- | --- | --- |
| `ecommerce-kanban-config` | `config/artisanpack/ecommerce-kanban-livewire.php` | Change defaults (the install command does this) |
| `ecommerce-kanban-views` | `resources/views/vendor/ecommerce-kanban` | Override Blade views |
| `ecommerce-kanban-lang` | `lang/vendor/ecommerce-kanban` | Override translations |

```bash
php artisan vendor:publish --tag=ecommerce-kanban-views
```

The config loads under `artisanpack.ecommerce-kanban-livewire`:

```php
config( 'artisanpack.ecommerce-kanban-livewire.board.route_prefix' ); // 'ecommerce-kanban'
```

Views load under the `ecommerce-kanban::` namespace. Translations are JSON catalogues (`lang/{locale}.json`) keyed by their English text. Prefer the [extension points](Extending) over published views: a published view does not pick up fixes in later releases.

## Routes

| Path | Route name | Shows | Ability |
| --- | --- | --- | --- |
| `/{route_prefix}` | `artisanpack.ecommerce.kanban.boards.index` | The board picker. Sends the user to their last board, or to the only board. `?pick=1` always shows the list. | `kanbanBoard.viewAny` |
| `/{route_prefix}/boards/{board}` | `artisanpack.ecommerce.kanban.boards.show` | A board. `?card={order}` opens that card's slide-out. | `kanbanBoard.view` on the board |

Every route runs `board.middleware` (`[ 'web', 'auth' ]` by default) followed by the `ecommerce-kanban.throttle` rate limiter. Every page is a controller action returning a page view, so `php artisan route:cache` works. `boards.show` is a fixed name: the ecommerce admin links to it.

Set `board.routes_enabled` to `false` to register your own routes and embed the components. See [Embedding Components](Extending-Embedding-Components).

## Admin menu entry

When the routes are on, the board adds a "Kanban" entry (key `kanban`, icon `o-view-columns`) to the engine's `AdminMenuRegistry`, in the `orders` section at position 15, shown to users with `kanbanBoard.viewAny`. The ecommerce admin and the cms-framework admin menus read that registry. A `kanban` entry registered first by someone else is left alone.

## Uninstalling

The board registers itself with the engine's `SatelliteRegistry`. It owns no tables, columns, migrations, or product types, so it ships no uninstaller. When the engine marks the satellite uninstalled, the provider wires nothing: no routes, views, components, menu entry, or hooks.

## Next steps

- [Front-End Setup](Installation-Front-End) — required before the board is styled and cards drag
- [Layouts](Installation-Layouts) — render the board inside your admin
- [Configuration](Installation-Configuration) — every key you can tune
- [Real-Time Updates](Realtime) — live boards with Echo
