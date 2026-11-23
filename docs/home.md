---
title: Overview
menu_order: 0
---

# Overview

A Livewire order board for the `artisanpack-ui/ecommerce` engine: orders shown as cards in columns by sub-status, moved by drag-and-drop, keyboard, or a "Move to…" dialog, built on the ArtisanPack UI design system through `artisanpack-ui/livewire-ui-components` and `livewire-drag-and-drop`.

It registers board routes that work right after install, and every screen is also a Livewire component you can embed in your own pages. It calls the engine's services in-process and owns no tables of its own: boards, columns, cards, widgets, and automations all live in the engine.

## Features

- **Boards of orders**: Columns left to right with card counts, WIP limits, collapse (remembered per board), and "Show more" paging. A board picker returns each user to the board they opened last.
- **Three ways to move a card**: Drag it, move it with the keyboard, or use "Move to…" (column and position). Every move goes through the engine, so WIP limits, the status roll-up, hooks, and automations always apply. A refused move is put back and the reason is shown.
- **Manual card order**: Cards stay where they are dropped, inside a column and across columns.
- **Filter bar**: Search, customer, tags, products, placed dates, stale cards, payment and fulfillment status. Filters live in the URL, so a filtered board is a shareable link.
- **Card slide-out**: A mini order view with customer, items, totals, the order's other boards, and recent activity.
- **Board-native editing**: Reorder, add, edit, and remove columns, pick card widgets, and switch automations on and off on the board itself, with or without the ecommerce admin installed.
- **Real time**: Laravel Echo on the engine's private board channel (Reverb, Pusher, or any broadcaster), with a polling fallback.
- **Extension points**: Filters for card actions, toolbar buttons, filter controls, card widget views, card classes, and board URLs, and actions after a render and a refused move.
- **Accessible and translated**: Targets WCAG 2.2 AA, checked with axe and keyboard tests in a real browser. Ships English, Spanish, French, German, Brazilian Portuguese, Japanese, Simplified Chinese, Italian, and Arabic.

## Requirements

PHP 8.3+, Laravel 12 or 13, Livewire 3.6+ or 4, the `artisanpack-ui/ecommerce` engine 1.2+, and a Vite build with Tailwind CSS 4. See [Requirements](installation/requirements.md).

## Quick install

```bash
composer require artisanpack-ui/ecommerce-kanban-livewire
php artisan ecommerce-kanban:install
npm install @artisanpack-ui/livewire-drag-and-drop@^2.1.0
```

Add the lines the install command prints to your main stylesheet and script, grant the `kanbanBoard` abilities, then open `/ecommerce-kanban`.

## Next steps

- [Getting started](getting-started.md): from `composer require` to a working board.
- [Installation](installation.md): requirements, configuration, the front-end build, and layouts.
- [Usage](usage.md): the board picker, moving cards, filters, the card slide-out, editing the board, and automations.
- [Extending](extending.md): card actions, toolbar buttons, widgets, filter controls, and embedding the board.
- [Reference](reference.md): hooks, Artisan commands, and configuration.
