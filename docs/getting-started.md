---
title: Getting Started
menu_order: 10
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
- prints the design-system setup (the component library's installer, or the npm packages, stylesheet lines, and behavior-engine lines to add by hand, and a reminder about `@apuiThemeScript`), the board's optional Tailwind `@source` lines, the drag-and-drop install command, and the import line;
- reports the engine version, how many active boards there are (and how to create one), and whether real-time sync is set up.

Nothing it checks stops the install. See [Installation](installation.md).

## 2. Add the board to your front end

The package ships no CSS or JavaScript build. The board renders with the ArtisanPack UI design system, which livewire-ui-components 3.0 uses. If your app doesn't load it yet, run the component library's installer:

```bash
php artisan livewire-ui-components:install --assets=bundler
```

Or install the packages by hand, with drag-and-drop:

```bash
npm install --save-dev @artisanpack-ui/css@^1 @artisanpack-ui/behavior@^1
npm install @artisanpack-ui/livewire-drag-and-drop@^2.1.0
```

Import the design system in your main stylesheet (for example `resources/css/app.css`):

```css
@import '@artisanpack-ui/css';
```

Register the behavior engine and import drag-and-drop in your main script (for example `resources/js/app.js`):

```js
import apuiBehavior from '@artisanpack-ui/behavior/alpine';
import '@artisanpack-ui/livewire-drag-and-drop';

document.addEventListener( 'alpine:init', () => window.Alpine.plugin( apuiBehavior ) );
```

Put `@apuiThemeScript` in your layout's `<head>`, before `@vite`. The package's standalone layout already does. Then build:

```bash
npm run build
```

Tailwind CSS is optional. If your app builds with it, put `@layer theme, base, apui, components, utilities;` first in your stylesheet, before `@import 'tailwindcss';`. See [Front-End Setup](installation/front-end.md) for the full entry, including the design system and the optional Tailwind lines.

## 3. Grant access

The engine denies every ability by default, so the board returns 403 until you grant access. The smallest setup is the engine's umbrella ability, in a service provider's `boot()`:

```php
use Illuminate\Support\Facades\Gate;

Gate::define( 'ecommerce.admin', fn ( $user ): bool => $user->is_admin );
```

Or grant only what the board needs: `ecommerce.kanbanBoard.viewAny` and `ecommerce.kanbanBoard.view` to see boards, `ecommerce.kanbanCard.move` to move cards, `ecommerce.kanbanBoard.update` to edit, and `ecommerce.order.view` for the card slide-out. See [Authorization](advanced/authorization.md).

## 4. Create a board (or seed a demo store)

Boards are engine data. Create one in the ecommerce admin (`artisanpack-ui/ecommerce-admin-livewire`) or through the engine's REST API (`POST /api/ecommerce/v1/kanban/boards`). On a development database, the engine's demo seeder creates products, orders, and a pre-populated "Fulfillment" board:

```bash
php artisan ecommerce:seed-demo
```

Do not run it against a live store.

## 5. Open the board

Open `/ecommerce-kanban`. The prefix is `board.route_prefix` in the config. The picker sends you straight to your last board, or to the only board when there is one. From there you can drag cards, filter, open a card, and, with `kanbanBoard.update`, edit the board.

The board stays in step with other people's changes by polling every 30 seconds. To make it live, turn on engine broadcasting and set up Laravel Echo. See [Real-Time Updates](realtime.md).

## Next steps

- [Configuration](installation/configuration.md) — every config key
- [Layouts](installation/layouts.md) — render the board inside your admin's layout
- [Real-Time Updates](realtime.md) — Reverb or Pusher with Echo
- [Upgrading](upgrading.md) — moving from 1.x to 2.0
- [Usage](usage.md) — what staff can do on the board
- [Extending](extending.md) — hooks, card actions, widgets, and embedding
