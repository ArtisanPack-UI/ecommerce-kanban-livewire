---
title: Usage
menu_order: 30
---

# Usage

What staff can do on the board, and the behavior every board shares.

## In this section

- [Board Picker](usage/board-picker.md) - Choosing a board, and switching between boards
- [Moving Cards](usage/moving-cards.md) - Dragging, the keyboard, "Move to…", exit columns, WIP limits, and refused moves
- [Filters](usage/filters.md) - The filter bar and its URL parameters
- [Card Detail](usage/card-detail.md) - The card slide-out, adding and removing orders
- [Editing the Board](usage/editing-the-board.md) - Columns and card widgets, on the board itself
- [Automations](usage/automations.md) - The automations panel and feedback after a move

## The board at a glance

A board shows its columns left to right. Each column is one order sub-status on that board, with:

- a colored top border and icon (from the column, or its sub-status);
- the column label;
- the card count, as `n / limit` when the column has a WIP limit, with a warning icon and text at the limit;
- how many cards the filters leave, when a filter hides some ("3 shown");
- how many automations run when a card moves in (a lightning icon with a count, which opens the automations panel);
- a collapse button. Collapsed columns are remembered per board in the browser and never load their cards.

Each card shows the order number, the customer, the placed date, and the column's widgets (total, item count, shipping method, days in column, and so on), with an actions menu: **Open**, **Move to…**, **Remove from board**, and anything satellites add.

A column shows its first `cards.per_column` cards (25 by default). **Show more** adds the same number again, up to 500 cards per column (`Board::MAX_CARDS_PER_COLUMN`); then the button goes away. Use the filters to find a card further down.

The header holds the board name and switcher, the connection indicator, toolbar buttons from satellites, **Automations**, and, with `kanbanBoard.update`, **Add order** and **Edit board**.

## Board settings: the board and the admin

Boards are engine data, so the admin and the board edit the same records under the same rules.

| Where | What you can change |
| --- | --- |
| Ecommerce admin (`artisanpack-ui/ecommerce-admin-livewire`), **Kanban boards** | The full forms: create, delete, reorder, and set the default board; a board's details and routing rules; columns; and creating and editing automations |
| The board's **Edit board** mode | Reorder, add, edit (label, color, icon, WIP limit), and remove columns; choose card widgets for each column and the board default |
| The board's **Automations** panel | List automations, switch them on and off, and delete them |

The board's own editing works without the admin installed. What it can't do on its own is create a board, edit routing rules, or create and edit automations: with the admin installed, the board links to the admin's screens for those ("Create board", "Advanced settings", "Add automation", "Edit"); without it, create boards and automations through the engine's REST API.

## Behavior every board shares

- **Shareable URLs.** Filters and the open card (`?card=`) live in the query string, so a link reopens the same view, and back and forward work.
- **Every move goes through the engine.** Dragging, the keyboard, and "Move to…" all call the engine's `KanbanBoardService::move()`, so WIP limits, the order status roll-up, hooks, automations, and broadcasting always apply.
- **Checked on every request.** Each Livewire update checks the user's abilities again, not only the page load. See [Authorization](advanced/authorization.md).
- **Destructive actions run once.** Removing a card from a board, removing a column, and deleting an automation ask first and carry a one-time token, so a double click can't run them twice.
- **Rate limits.** Board changes count against the engine's `ecommerce.admin.mutate` limiter. Over the limit, the change doesn't run and a toast says how long to wait. See [Rate limits](advanced.md#rate-limits).
- **Kept current.** Changes made elsewhere arrive live over Echo or by polling. See [Real-Time Updates](realtime.md).
