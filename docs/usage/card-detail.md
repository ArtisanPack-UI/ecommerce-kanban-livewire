---
title: Card Detail
menu_order: 40
---

# Card Detail

**Open** in a card's actions menu opens the card slide-out: a drawer on the right with a mini view of the order. A plain click opens it in place; a modified click (Ctrl, Cmd, Shift, or Alt; Cmd-click on macOS, where Ctrl-click opens the context menu) opens the board with the card in a new tab.

The open card is in the URL as `?card={order id}`, so a link to it reopens it, for example `/ecommerce-kanban/boards/3?card=1004`.

Opening a card needs `order.view`, and the order must be on the board and not hidden from the viewer by an `ap.ecommerce.kanban.cardQuery` filter (see [Filters](filters.md#restricting-which-cards-people-see)). Otherwise a toast says why and nothing opens.

## What it shows

- the order's status and the column it is in on this board;
- the customer, email, phone, and when the order was placed;
- the shipping address;
- the items, with SKU, quantity, and line total, and the order totals;
- payment and fulfillment status;
- the other boards the order is on that you can view, and the column on each;
- the five latest timeline entries ("Recent activity"). Board events name the board by its name when you can view it; a board you can't view, or one that was deleted, shows as "Added to another board" or "Removed from another board", and a move on it shows the sub-status instead of that board's column.

## Actions

| Action | Needs | Does |
| --- | --- | --- |
| **Move to…** | `kanbanCard.move` | Closes the drawer and opens the board's [Move to… dialog](moving-cards.md#move-to) for the card |
| **Remove from board** | `kanbanBoard.update` | Closes the drawer and asks before taking the order off this board |
| **Open in admin** | The ecommerce admin installed | Opens the order's admin screen |
| **Add to another board** | `kanbanBoard.update` | Puts the order on another active board, in a chosen column or the board's entry column ("Entry column (automatic)"). A column that isn't on the chosen board is refused ("Choose a column on that board."). If the order is already on that board, an info toast says so ("Order 1004 is already on Fulfillment.") and nothing changes. |

The drawer closes with Escape, its close button, or a click outside it. Focus then returns to the card.

## Adding and removing orders

Orders usually reach a board through the engine's routing rules. Staff with `kanbanBoard.update` can also do it by hand:

- **Add order** in the board header opens "Add an order to this board". Search by order number or email; the results are orders that aren't on the board yet and that you may view. Pick one and a column, or leave the column on the entry column: the first column that fits the order's status. If someone else added the order in the meantime, an info toast says "Order N is already on" the board instead of reporting it as added.
- **Remove from board**, in a card's menu or the slide-out, asks "Remove order N from this board?" The order stays on any other boards it is on, and its status doesn't change.

Both go through the engine's `KanbanRoutingService`, so its hooks fire and other viewers see the change. The engine refuses an order that no column can hold ("This board has no column that can hold this order.").

## Staying current

An open slide-out re-renders when its order changes elsewhere: a live `card-updated` event, a poll, or a board event. When the order's status or column changes, screen readers hear just that ("Order 1042 is now Complete in Shipped"), not the whole drawer. See [Real-Time Updates](../realtime.md).
