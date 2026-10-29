---
title: ArtisanPack UI Ecommerce Kanban (Livewire) Documentation Home
---

# ArtisanPack UI Ecommerce Kanban (Livewire) Documentation

Welcome to the documentation for the ArtisanPack UI Ecommerce Kanban board. The package is the Livewire order board for the `artisanpack-ui/ecommerce` engine: orders shown as cards in columns by sub-status, moved by drag-and-drop, keyboard, or a "Move to…" dialog, built from `artisanpack-ui/livewire-ui-components` and `livewire-drag-and-drop`.

It registers board routes that work right after install, and every screen is also a Livewire component you can embed in your own pages. It calls the engine's services in-process and owns no tables of its own: boards, columns, cards, widgets, and automations all live in the engine.

## Table of Contents

- **Getting Started**
  - [Quick Start Guide](Getting-Started)

- **Installation**
  - [Installation Overview](Installation)
  - [Requirements](Installation-Requirements)
  - [Configuration](Installation-Configuration)
  - [Front-End Setup](Installation-Front-End)
  - [Layouts](Installation-Layouts)

- **Real-Time Updates**
  - [Real-Time Overview](Realtime)
  - [Broadcasting with Echo](Realtime-Broadcasting)
  - [Polling Fallback](Realtime-Polling)

- **Usage**
  - [Usage Overview](Usage)
  - [Board Picker](Usage-Board-Picker)
  - [Moving Cards](Usage-Moving-Cards)
  - [Filters](Usage-Filters)
  - [Card Detail](Usage-Card-Detail)
  - [Editing the Board](Usage-Editing-The-Board)
  - [Automations](Usage-Automations)

- **Extending**
  - [Extending Overview](Extending)
  - [Hooks Reference](Extending-Hooks-Reference)
  - [Card and Toolbar Actions](Extending-Card-Actions)
  - [Card Widgets](Extending-Card-Widgets)
  - [Filter Controls](Extending-Filter-Controls)
  - [Embedding Components](Extending-Embedding-Components)

- **Quality**
  - [Accessibility](Accessibility)
  - [Localization](Localization)

- **Advanced**
  - [Advanced Overview](Advanced)
  - [Authorization](Advanced-Authorization)
  - [Artisan Commands](Advanced-Artisan-Commands)
  - [Performance](Advanced-Performance)
  - [Browser Tests](Advanced-Browser-Tests)

## Features

- **Boards of orders**: Columns left to right with card counts, WIP limits, collapse (remembered per board), and "Show more" paging. A board picker returns each user to the board they opened last.
- **Three ways to move a card**: Drag it, move it with the keyboard, or use "Move to…" (column and position). Every move goes through the engine, so WIP limits, the status roll-up, hooks, and automations always apply. A refused move is put back and the reason is shown.
- **Manual card order**: Cards stay where they are dropped, inside a column and across columns.
- **Filter bar**: Search, customer, tags, products, placed dates, stale cards, payment and fulfillment status. Filters live in the URL, so a filtered board is a shareable link.
- **Card slide-out**: A mini order view with customer, items, totals, the order's other boards, and recent activity.
- **Board-native editing**: Reorder, add, edit, and remove columns, pick card widgets, and switch automations on and off on the board itself, with or without the ecommerce admin installed.
- **Real time**: Laravel Echo on the engine's private board channel (Reverb, Pusher, or any broadcaster), with a polling fallback.
- **Extension points**: Filters for card actions, toolbar buttons, filter controls, card widget views, card classes, and board URLs, and actions after a render and a refused move.
- **Accessible and translated**: Targets WCAG 2.2 AA, checked with axe and keyboard tests in a real browser. Ships English, Spanish, French, and German.

## Quick Example

```bash
composer require artisanpack-ui/ecommerce-kanban-livewire
php artisan ecommerce-kanban:install
npm install @artisanpack-ui/livewire-drag-and-drop@^2.1.0
```

Add the package's views to your Tailwind sources and import drag-and-drop in your main script (the install command prints both lines), grant the `kanbanBoard` abilities, then open `/ecommerce-kanban`.

## Support

For support, please open an issue on the [GitHub repository](https://github.com/ArtisanPack-UI/ecommerce-kanban-livewire).

## License

This package is open-source software licensed under the MIT license.
