---
title: Artisan Commands
---

# Artisan Commands

## `ecommerce-kanban:install`

```bash
php artisan ecommerce-kanban:install [--force]
```

Sets up the board in a host application. It:

1. publishes `config/artisanpack/ecommerce-kanban-livewire.php` (tag `ecommerce-kanban-config`). A file you published before is kept; `--force` overwrites it;
2. prints the Tailwind `@source` lines to add to your main stylesheet, so your build includes the board's classes;
3. prints the drag-and-drop install command (`npm install @artisanpack-ui/livewire-drag-and-drop@^2.1.0`) and the import line for your main script (`import '@artisanpack-ui/livewire-drag-and-drop';`);
4. reports the installed `artisanpack-ui/ecommerce` version, and warns when it is older than 1.2.0 (a branch install such as `dev-main` isn't compared);
5. counts the active boards. With none, it warns and says where to create one: the ecommerce admin's board list, when the admin is installed, and the engine's REST route (`POST` to `ecommerce.api.kanban.boards.store`, `/api/ecommerce/v1/kanban/boards`);
6. reports real-time sync: the engine's `artisanpack.ecommerce.kanban.broadcast` (on or off) and the default broadcaster. With both set up, it reminds you to set up Laravel Echo on `window.Echo`. Otherwise it warns that boards will poll every `realtime.poll_interval` seconds (or only change on reload, with polling off), and says what to turn on: `ECOMMERCE_KANBAN_BROADCAST=true`, and a broadcaster (for example `php artisan install:broadcasting` for Reverb). The `null` and `log` broadcasters count as none.

It never edits your stylesheet or script, the checks only warn, and it is safe to run again.

## Engine commands a store uses with the board

| Command | Use |
| --- | --- |
| `php artisan ecommerce:seed-demo` | Seed a demo store, including a pre-populated "Fulfillment" board, to try the board. Not for a live store. |
| `php artisan ecommerce:audit-order-status` | Report cards whose sub-status is outside the order's status chain. Scheduled daily by the engine. |
| `php artisan ecommerce:prune-idempotency-records` | Clear old idempotency rows, including the board's spent action tokens. Scheduled hourly by the engine. |
| `php artisan ecommerce:verify-satellite` | Run the engine's contract suites against a satellite (such as this package) and write a verification report |
| `vendor/bin/testbench ecommerce:lint:translations` | Check translations in this package. See [Localization](Localization). |

The engine's scheduled commands need Laravel's scheduler running (`php artisan schedule:run` every minute). Turn the engine's schedule off with `artisanpack.ecommerce.schedule.enabled`, or change a task's cron expression in `artisanpack.ecommerce.schedule.tasks`.
