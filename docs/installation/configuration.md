---
title: Configuration
---

# Configuration

`php artisan ecommerce-kanban:install` publishes the config to `config/artisanpack/ecommerce-kanban-livewire.php`. You can also publish it with `php artisan vendor:publish --tag=ecommerce-kanban-config`. It loads under the runtime key `artisanpack.ecommerce-kanban-livewire`:

```php
config( 'artisanpack.ecommerce-kanban-livewire.cards.per_column' ); // 25
```

Every key has a default, so you only need the keys you change.

## Board routes

| Key | Default | What it controls |
| --- | --- | --- |
| `board.routes_enabled` | `true` | Register the board routes and the "Kanban" admin menu entry. Set to `false` to register your own routes and embed the Livewire components. See [Embedding Components](Extending-Embedding-Components). |
| `board.route_prefix` | `'ecommerce-kanban'` | URL prefix for the picker and board pages. Leading and trailing slashes are trimmed. |
| `board.middleware` | `[ 'web', 'auth' ]` | Middleware on every board route. Add your 2FA or `verified` middleware here. Every entry that names a middleware class or alias is also registered as Livewire persistent middleware, so it runs again on each Livewire update from a board page. Middleware groups (such as `web`) are left out; Livewire's update route has its own. |

Route names are always `artisanpack.ecommerce.kanban.*` (`boards.index` and `boards.show`). The `ecommerce-kanban.throttle` rate limiter is added after `board.middleware` on every route. See [Rate limits](Advanced#rate-limits).

## Layout

| Key | Default | What it controls |
| --- | --- | --- |
| `layout` | `null` | The Blade layout the board pages extend. Leave `null` to use, in order, cms-framework's admin layout, the ecommerce admin's layout (when that package is installed and active), or this package's standalone layout. A view name that doesn't exist is ignored. See [Layouts](Installation-Layouts). |

## Cards

| Key | Default | What it controls |
| --- | --- | --- |
| `cards.per_column` | `25` | How many cards a column shows at first, and how many more each "Show more" adds. Values below 1 count as 1. |

## Real-time updates

| Key | Default | What it controls |
| --- | --- | --- |
| `realtime.enabled` | `true` | Keep open boards in step with changes made elsewhere. When on, and the engine broadcasts board events and the page has Laravel Echo, boards listen on the engine's private board channel. Set to `false` to turn off both Echo and polling: boards then only change on reload. |
| `realtime.poll_interval` | `30` | Seconds between polls when the board can't listen over Echo. `0` turns polling off. |

The board's real-time setting is only half the switch. The engine must broadcast too:

| Engine key | Env | Default | What it controls |
| --- | --- | --- | --- |
| `artisanpack.ecommerce.kanban.broadcast` | `ECOMMERCE_KANBAN_BROADCAST` | `false` | Broadcast board events on `private-ecommerce.kanban.board.{board}` and register the channel's authorization |
| `artisanpack.ecommerce.kanban.broadcast_connection` | `ECOMMERCE_KANBAN_BROADCAST_CONNECTION` | `null` | Queue connection the broadcasts are queued on (the default when null) |
| `artisanpack.ecommerce.kanban.broadcast_queue` | `ECOMMERCE_KANBAN_BROADCAST_QUEUE` | `null` | Queue the broadcasts are queued on (the default when null) |

See [Real-Time Updates](Realtime).

## Engine settings the board reads

These belong to the engine, but change what the board shows:

| Engine key | Default | Effect on the board |
| --- | --- | --- |
| `artisanpack.ecommerce.kanban.default_card_widgets` | `[ 'total', 'item-count', 'customer' ]` | Widgets on cards in columns and boards that don't choose their own. See [Editing the Board](Usage-Editing-The-Board#card-widgets). |
| `artisanpack.ecommerce.kanban.stale_after_days` | `3` | What "Stale cards only" means: cards that reached their column more than this many days ago. The `days-in-column` widget also turns to a warning after this many days. |
| `artisanpack.ecommerce.kanban.new_card_position` | `'bottom'` | Where cards that join a board land in their column: `bottom` or `top` |
| `per_user` of `'admin.mutate'` in `artisanpack.ecommerce.rate_limits` (`ECOMMERCE_RATE_ADMIN_MUTATE_PER_USER`) | `120` | Board changes a user may make per minute. See [Rate limits](Advanced#rate-limits). |
