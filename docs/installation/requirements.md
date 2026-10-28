---
title: Requirements
---

# Requirements

## Core requirements

- **PHP 8.3+**
- **Laravel 12 or 13**
- **Livewire 3.6+ or 4**

Composer installs these ArtisanPack UI packages with it:

| Package | Version | Why |
| --- | --- | --- |
| `artisanpack-ui/ecommerce` | `^1.2` | The engine: boards, columns, cards, moves, widgets, automations, and the board broadcasts. 1.2 adds manual card order, the filterable card query, board events, card refresh, widget config, and the column service. |
| `artisanpack-ui/livewire-ui-components` | `^2.1` | Every button, input, select, modal, drawer, dropdown, and toast |
| `artisanpack-ui/core` | `^1.0` | Shared helpers |
| `artisanpack-ui/hooks` | `^1.2` | The filters and actions used for [extension](Extending) |
| `artisanpack-ui/security` | `^1.0.3` or `^2.0` | Output escaping and sanitization |

`php artisan ecommerce-kanban:install` warns when the installed engine is older than 1.2.0.

## Front-end requirements

The package ships no build. Your application needs:

- **Tailwind CSS 4** with **daisyUI 5** (pin `daisyui` to `~5.0`; see [Front-End Setup](Installation-Front-End))
- **Vite** with `laravel-vite-plugin`
- **`@artisanpack-ui/livewire-drag-and-drop` 2.1.0 or later**, imported in your main script. 2.1 adds what the board relies on: dragging between columns, keyboard moves between columns, translatable announcements, and putting a refused drop back.

Alpine comes with Livewire. Do not import it again.

## Access

The engine denies every ability by default. Users need at least `kanbanBoard.viewAny` and `kanbanBoard.view` to see a board. See [Authorization](Advanced-Authorization).

## Real-time updates (optional)

For live boards, a Laravel broadcaster (for example Reverb or Pusher), Laravel Echo on `window.Echo`, a queue worker, and the engine's `ECOMMERCE_KANBAN_BROADCAST=true`. Without them the board polls. See [Real-Time Updates](Realtime).

## Optional packages

The board detects these at runtime and uses them when present:

| Package | What it adds |
| --- | --- |
| `artisanpack-ui/ecommerce-admin-livewire` | The board renders inside the admin's layout. "Open in admin" on the card slide-out, "Advanced settings" in edit mode, "Create board" on an empty picker, and "Add" / "Edit" in the automations panel link to the admin's screens. The admin links to the board from its navigation and order screens. |
| `artisanpack-ui/cms-framework` | The board renders inside the cms-framework admin layout, and the "Kanban" menu entry appears in its admin menu. |
| `laravel/reverb` | A broadcaster for real-time sync. Any Laravel broadcaster works. |

See [Layouts](Installation-Layouts) for how the layout is chosen.
