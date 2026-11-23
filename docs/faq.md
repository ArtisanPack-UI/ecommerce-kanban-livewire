---
title: FAQ
menu_order: 95
---

# FAQ

## Do I need the ecommerce admin?

No. The board works on its own, with its standalone layout and its own column, widget, and automation editing. With `artisanpack-ui/ecommerce-admin-livewire` installed, the board renders inside the admin's layout and links to the admin's screens for creating boards, routing rules, and creating and editing automations. See [Usage](usage.md#board-settings-the-board-and-the-admin).

## Do I need cms-framework?

No. With it, the board renders inside the cms-framework admin layout and its "Kanban" entry joins the CMS menu. See [Layouts](installation/layouts.md).

## Why does every board return 403?

The engine denies every ability by default. Define the `ecommerce.admin` gate, or the individual `ecommerce.kanbanBoard.*`, `ecommerce.kanbanCard.move`, and `ecommerce.order.view` abilities. See [Authorization](advanced/authorization.md).

## Can packers move cards without editing the board?

Yes. Grant `kanbanBoard.viewAny`, `kanbanBoard.view`, and `kanbanCard.move`, and leave out `kanbanBoard.update`. They can drag and move cards, but not add or remove orders, edit columns and widgets, or change automations. See [Authorization](advanced/authorization.md#abilities-the-board-uses).

## How do I create a board?

In the ecommerce admin's **Kanban boards** screen when the admin is installed, or through the engine's REST API (`POST /api/ecommerce/v1/kanban/boards`) otherwise. The board itself can edit a board's columns, widgets, and automations, but not create one. To try the board with data, run `php artisan ecommerce:seed-demo`. See [Getting Started](getting-started.md).

## Does the board have its own database tables?

No. Boards, columns, cards, widgets, and automations are the engine's tables, read and written through the engine's services. The package ships no migrations.

## Why is the board unstyled?

The package ships no CSS build. Load the design system (`@artisanpack-ui/css`), add the board's `@source` lines to your stylesheet, and rebuild. See [Front-End Setup](installation/front-end.md).

## Does it still need daisyUI?

No. Since 2.0 the board renders on the ArtisanPack UI design system through `livewire-ui-components` 3.0. Remove daisyUI if nothing else uses it.

## Why can't I drag cards?

Your main script doesn't import `@artisanpack-ui/livewire-drag-and-drop` 2.1.0 or later. See [Troubleshooting](troubleshooting.md#cards-cant-be-dragged).

## Do I need Reverb or Pusher?

No. Without a broadcaster the board polls every `realtime.poll_interval` seconds (30 by default). With the engine's broadcasts turned on and Laravel Echo on the page, boards update live. See [Real-Time Updates](realtime.md).

## Does it work with Livewire 4?

Yes. It supports Livewire 3.6+ and 4, and CI runs both.

## Can I change the URL?

Set `board.route_prefix`. Route names stay `artisanpack.ecommerce.kanban.*`. To serve the board from your own routes, turn the package's routes off and embed the component. See [Embedding Components](extending/embedding-components.md).

## Can my package add actions or widgets to cards?

Yes. Add card actions, toolbar buttons, filter controls, and widget views with filters. See [Extending](extending.md).

## How do I add two-factor authentication?

Add your 2FA middleware to `board.middleware`. Every class or alias in it also runs on the board's Livewire updates. See [Advanced](advanced.md#host-middleware).

## Which languages does it ship?

English, Spanish, French, German, Brazilian Portuguese, Japanese, Simplified Chinese, Italian, and Arabic. Right-to-left locales get a right-to-left layout in the standalone layout. See [Localization](localization.md).

## Is it accessible?

It targets WCAG 2.2 AA, and the browser suite runs axe on every screen and dialog. Some known issues come from the component library. See [Accessibility](accessibility.md).

## What versions are supported?

PHP 8.3+, Laravel 12 or 13, Livewire 3.6+ or 4, and the `artisanpack-ui/ecommerce` engine 1.2+. See [Requirements](installation/requirements.md).
