---
title: Getting Started
---

# Getting Started

Get from `composer require` to a working board. This assumes the `artisanpack-ui/ecommerce` engine (1.2 or later) is installed and migrated.

## 1. Install

```bash
composer require artisanpack-ui/ecommerce-kanban-livewire
php artisan ecommerce-kanban:install
```

The service provider is auto-discovered. The install command:

- publishes `config/artisanpack/ecommerce-kanban-livewire.php`;
- prints the Tailwind `@source` lines, the drag-and-drop install command, and the import line;
- reports the engine version, how many active boards there are (and how to create one), and whether real-time sync is set up.

Nothing it checks stops the install. See [Installation](Installation).

## 2. Add the board to your front end

The package ships no CSS or JavaScript build. Add the printed lines to your Tailwind entry (for example `resources/css/app.css`):

```css
@source "../../vendor/artisanpack-ui/ecommerce-kanban-livewire/resources/views/**/*.blade.php";
@source "../../vendor/artisanpack-ui/ecommerce-kanban-livewire/src/**/*.php";
```

Install drag-and-drop and import it in your main script (for example `resources/js/app.js`):

```bash
npm install @artisanpack-ui/livewire-drag-and-drop@^2.1.0
```

```js
import '@artisanpack-ui/livewire-drag-and-drop';
```

Then build:

```bash
npm run build
```

See [Front-End Setup](Installation-Front-End) for the full entry, including daisyUI.

## 3. Grant access

The engine denies every ability by default, so the board returns 403 until you grant access. The smallest setup is the engine's umbrella ability, in a service provider's `boot()`:

```php
use Illuminate\Support\Facades\Gate;

Gate::define( 'ecommerce.admin', fn ( $user ): bool => $user->is_admin );
```

Or grant only what the board needs: `ecommerce.kanbanBoard.viewAny` and `ecommerce.kanbanBoard.view` to see boards, `ecommerce.kanbanCard.move` to move cards, `ecommerce.kanbanBoard.update` to edit, and `ecommerce.order.view` for the card slide-out. See [Authorization](Advanced-Authorization).

## 4. Create a board (or seed a demo store)

Boards are engine data. Create one in the ecommerce admin (`artisanpack-ui/ecommerce-admin-livewire`) or through the engine's REST API (`POST /api/ecommerce/v1/kanban/boards`). On a development database, the engine's demo seeder creates products, orders, and a pre-populated "Fulfillment" board:

```bash
php artisan ecommerce:seed-demo
```

Do not run it against a live store.

## 5. Open the board

Open `/ecommerce-kanban`. The prefix is `board.route_prefix` in the config. The picker sends you straight to your last board, or to the only board when there is one. From there you can drag cards, filter, open a card, and, with `kanbanBoard.update`, edit the board.

The board stays in step with other people's changes by polling every 30 seconds. To make it live, turn on engine broadcasting and set up Laravel Echo. See [Real-Time Updates](Realtime).

## Next steps

- [Configuration](Installation-Configuration) — every config key
- [Layouts](Installation-Layouts) — render the board inside your admin's layout
- [Real-Time Updates](Realtime) — Reverb or Pusher with Echo
- [Usage](Usage) — what staff can do on the board
- [Extending](Extending) — hooks, card actions, widgets, and embedding
