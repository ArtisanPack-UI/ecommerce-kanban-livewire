---
title: Moving Cards
---

# Moving Cards

Moving a card changes the order's sub-status on that board. There are three ways to do it, and all three call the same engine method, `KanbanBoardService::move()`, so WIP limits, the order status roll-up, the engine's hooks, automations, and broadcasting apply to every move.

Moving needs `kanbanCard.move` on the board. Without it, cards don't drag and "Move to…" is not offered. Cards don't drag in "Edit board" mode either, where the column headers drag instead.

## Dragging

Drag a card to another column, or up and down within its column. Cards keep the position you drop them at: the board stores a manual order, so "next up" stays at the top.

A drop is optimistic. The card stays where you dropped it and dims ("Moving…") while the request runs. When the move goes through, only the two columns it touched re-render. When it is refused, the card goes back where it came from, is outlined and marked "Move undone" for a moment, and a toast says why.

A drop into a column already at its WIP limit is refused in the browser before anything moves, and the engine checks the limit again on the server.

## Keyboard

Every card is focusable. With a card focused:

| Key | Does |
| --- | --- |
| Space or Enter | Pick the card up |
| Up and Down arrows | Move it within the column |
| Left and Right arrows | Move it to the previous or next column |
| Home or End | Jump to the top or bottom of the column |
| Space or Enter | Drop it |
| Escape | Cancel and put it back |

Each step is announced to screen readers with the order, the column, and the position, for example "Order 1002 moved to Packing, position 1 of 2." See [Accessibility](Accessibility).

## "Move to…"

Every card's actions menu has **Move to…**, and so does the [card slide-out](Usage-Card-Detail). It opens a dialog titled "Move order N" with:

- **Column**: every column on the board. The card's own column is marked "(current column)". A column at its WIP limit is marked "(at its WIP limit)" and can't be chosen.
- **Position**: **Top of the column**, **Bottom of the column**, or **After a card** (offered when the column has other cards), which adds an **After** list of that column's cards.

**Move** runs the move. When it is refused, the dialog stays open and a toast says why. When it goes through, focus returns to the moved card.

"Move to…" is the single-pointer alternative to dragging (WCAG 2.2 SC 2.5.7), and works the same as a drop.

## Exit columns ask first

A column whose sub-status belongs to the `cancelled`, `refunded`, or `failed` system status is an exit column. Moving a card into one changes the whole order's status, on every board the order is on, so the board asks first:

> **Move order 1003 to Cancelled?**
> This changes the whole order's status to Cancelled, on every board it is on.

**Move** confirms; **Cancel** leaves the card where it was. Moves within an exit column don't ask.

## WIP limits

A column with a WIP limit shows its count as `n / limit`. At the limit, the count turns to the warning color, adds a warning icon, and reads "n of limit cards, WIP limit reached" to screen readers. Moves into a full column are refused: by the browser on a drop, by the dialog (the column can't be chosen), and by the engine in every case.

The limit counts every card in the column, whatever the filters show. Set a column's limit in [Edit board](Usage-Editing-The-Board) mode or in the admin.

## Why a move is refused

| Code | Message |
| --- | --- |
| `wip-limit-reached` | ":column is at its WIP limit." |
| `invalid-status-transition` | "The order cannot move to :column from its current status." |
| `incompatible-column` | ":column cannot hold this order at its current status." |
| `move-rejected` | "This move was blocked." (an `ap.ecommerce.kanban.cardMoving` filter vetoed it) |
| `not-on-board` | "This order is no longer on this board." |
| `column-not-on-board` | "That column is not on this board." |
| `no-entry-column` | "This board has no column that can hold this order." |
| anything else | "The card could not be moved." |

The engine's status machine decides which moves are valid. For example, a single-board order can't go from `pending` straight to `complete`. See the engine's kanban documentation ("Moving cards" and "Status roll-up").

Every refusal also runs the `ap.ecommerceKanbanLivewire.cardMoveFailed` action with the code. See [Hooks Reference](Extending-Hooks-Reference).

## After a move

- The board's automations for the move run in the engine, and the person who moved the card gets one toast naming those that ran or failed. See [Automations](Usage-Automations#feedback-after-a-move).
- Other people's boards pick the move up live or on their next poll. See [Real-Time Updates](Realtime).
