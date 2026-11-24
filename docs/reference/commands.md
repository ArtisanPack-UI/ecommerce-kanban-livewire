---
title: Artisan Commands
menu_order: 20
redirect_from: advanced/artisan-commands
---

# Artisan Commands

## `ecommerce-kanban:install`

```bash
php artisan ecommerce-kanban:install [--force]
```

Sets up the board in a host application. It:

1. publishes `config/artisanpack/ecommerce-kanban-livewire.php` (tag `ecommerce-kanban-config`). A file you published before is kept; `--force` overwrites it;
2. prints the design-system setup: the component library's installer (`php artisan livewire-ui-components:install --assets=bundler`; skip it if your app already loads the design system), or the npm packages to install by hand (`@artisanpack-ui/css@^1 @artisanpack-ui/behavior@^1`), the stylesheet lines (`@layer theme, base, apui, components, utilities;`, needed only with Tailwind, and `@import '@artisanpack-ui/css';`), the lines that register the behavior engine as an Alpine plugin, and a reminder to put `@apuiThemeScript` in your layout's `<head>` before `@vite`;
3. prints the board's Tailwind `@source` lines as an optional step, under "Optional, only if your app uses Tailwind CSS (the board doesn't need it): add these lines to your main stylesheet (e.g. resources/css/app.css) so Tailwind scans the board's classes:". Tailwind isn't required; add them only if your app builds with Tailwind and you want it to scan the board's views and classes. See [Front-End Setup](../installation/front-end.md#3-tailwind-css-optional);
4. prints the drag-and-drop install command (`npm install @artisanpack-ui/livewire-drag-and-drop@^2.1.0`) and the import line for your main script (`import '@artisanpack-ui/livewire-drag-and-drop';`);
5. reports the installed `artisanpack-ui/ecommerce` version, and warns when it is older than 1.2.0 (a branch install such as `dev-main` isn't compared);
6. counts the active boards. With none, it warns and says where to create one: the ecommerce admin's board list, when the admin is installed, and the engine's REST route (`POST` to `ecommerce.api.kanban.boards.store`, `/api/ecommerce/v1/kanban/boards`);
7. reports real-time sync: the engine's `artisanpack.ecommerce.kanban.broadcast` (on or off) and the default broadcaster. With both set up, it reminds you to set up Laravel Echo on `window.Echo`. Otherwise it warns that boards will poll every `realtime.poll_interval` seconds (or only change on reload, with polling off), and says what to turn on: `ECOMMERCE_KANBAN_BROADCAST=true`, and a broadcaster (for example `php artisan install:broadcasting` for Reverb). The `null` and `log` broadcasters count as none.

It never edits your stylesheet or script, the checks only warn, and it is safe to run again.

## Engine commands a store uses with the board

| Command | Use |
| --- | --- |
| `php artisan ecommerce:seed-demo` | Seed a demo store, including a pre-populated "Fulfillment" board, to try the board. Not for a live store. |
| `php artisan ecommerce:audit-order-status` | Report cards whose sub-status is outside the order's status chain. Scheduled daily by the engine. |
| `php artisan ecommerce:prune-idempotency-records` | Clear old idempotency rows, including the board's spent action tokens. Scheduled hourly by the engine. |
| `php artisan ecommerce:verify-satellite` | Run the engine's contract suites against a satellite (such as this package) and write a verification report |
| `vendor/bin/testbench ecommerce:lint:translations` | Check translations in this package. See [Localization](../localization.md). |

The engine's scheduled commands need Laravel's scheduler running (`php artisan schedule:run` every minute). Turn the engine's schedule off with `artisanpack.ecommerce.schedule.enabled`, or change a task's cron expression in `artisanpack.ecommerce.schedule.tasks`.
