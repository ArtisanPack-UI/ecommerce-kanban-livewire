---
title: Requirements
menu_order: 10
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
| `artisanpack-ui/livewire-ui-components` | `^3.0` | Every button, input, select, combobox, dialog, drawer, dropdown menu, and toast, on the ArtisanPack UI design system |
| `artisanpack-ui/core` | `^1.0` | Shared helpers |
| `artisanpack-ui/hooks` | `^1.2` | The filters and actions used for [extension](../extending.md) |

`php artisan ecommerce-kanban:install` warns when the installed engine is older than 1.2.0.

## Front-end requirements

The package ships no build. Your application needs:

- **The ArtisanPack UI design system**: `@artisanpack-ui/css` 1.x and `@artisanpack-ui/behavior` 1.x, the design system livewire-ui-components 3.0 renders with, with the behavior engine registered as an Alpine plugin and `@apuiThemeScript` in your layout's `<head>`. See [Front-End Setup](front-end.md).
- **Vite** with `laravel-vite-plugin`
- **`@artisanpack-ui/livewire-drag-and-drop` 2.1.0 or later**, imported in your main script. 2.1 adds what the board relies on: dragging between columns, keyboard moves between columns, translatable announcements, and putting a refused drop back.

Not required:

- **Tailwind CSS.** The board's views use only the package's `ec-kb-*` classes and the design system's own classes, and the package inlines the board's stylesheet itself. If your app builds with Tailwind CSS 4, the design system's `@layer` line goes first, and the board's `@source` lines are optional. See [Tailwind CSS (optional)](front-end.md#3-tailwind-css-optional).
- **daisyUI.** 2.0 doesn't use it. Remove it if nothing else in your app does.

Alpine comes with Livewire. Do not import it again.

## Content Security Policy

The board needs Alpine's standard build (its inline expressions need `'unsafe-eval'` in `script-src`) and inline `style` attributes (`'unsafe-inline'` in `style-src-attr`). It doesn't work with Alpine's CSP build or Livewire 4's `csp_safe` mode. See [Content Security Policy](../advanced.md#content-security-policy).

## Access

The engine denies every ability by default. Users need at least `kanbanBoard.viewAny` and `kanbanBoard.view` to see a board. See [Authorization](../advanced/authorization.md).

## Real-time updates (optional)

For live boards, a Laravel broadcaster (for example Reverb or Pusher), Laravel Echo on `window.Echo`, a queue worker, and the engine's `ECOMMERCE_KANBAN_BROADCAST=true`. Without them the board polls. See [Real-Time Updates](../realtime.md).

## Optional packages

The board detects these at runtime and uses them when present:

| Package | What it adds |
| --- | --- |
| `artisanpack-ui/ecommerce-admin-livewire` (2.0 or later) | The board renders inside the admin's layout. "Open in admin" on the card slide-out, "Advanced settings" in edit mode, "Create board" on an empty picker, and "Add" / "Edit" in the automations panel link to the admin's screens. The admin links to the board from its navigation and order screens. |
| `artisanpack-ui/cms-framework` | The board renders inside the cms-framework admin layout, and the "Kanban" menu entry appears in its admin menu. |
| `laravel/reverb` | A broadcaster for real-time sync. Any Laravel broadcaster works. |

`artisanpack-ui/ecommerce-admin-livewire` must be 2.0 or later: it is also on livewire-ui-components 3.0, and 1.x won't install next to the board.

See [Layouts](layouts.md) for how the layout is chosen.
