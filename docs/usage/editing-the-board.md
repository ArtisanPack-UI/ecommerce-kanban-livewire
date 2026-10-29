---
title: Editing the Board
---

# Editing the Board

Staff with `kanbanBoard.update` can change a board's columns and card widgets on the board itself. **Edit board** in the header turns edit mode on; **Done editing** turns it off. Changes save as you make them.

Every change goes through the engine's services (`KanbanColumnService` for columns), so the rules match the ecommerce admin and the REST API, the engine's validation messages show on the fields, and other open boards pick up the change as a board config change. Board-native editing works without the ecommerce admin installed. See [Usage](Usage#board-settings-the-board-and-the-admin) for what stays in the admin.

In edit mode cards don't drag, and each column header gets a toolbar:

| Button | Does |
| --- | --- |
| Move left / Move right | Moves the column one place. Focus stays on the button, so you can press it again. The first column's "left" and the last column's "right" are disabled. |
| Edit column | Opens the column editor |
| Card widgets | Opens the widget picker for this column |
| Automations | Opens the [automations panel](Usage-Automations) for this column |
| Remove column | Asks before removing the column |

The widget picker needs `kanbanBoard.update` to open, edit, and save, however it is opened. The header adds **Default card widgets** (the widget picker for the board default) and, when the ecommerce admin is installed, **Advanced settings**, which opens the admin's edit screen for the board (details, routing rules, columns, and automations).

## Reordering columns

Drag a column by its header, or use **Move left** and **Move right**. A reorder the engine refuses is put back.

## Editing a column

The column editor ("Edit column: Packing") has:

| Field | Leave empty to |
| --- | --- |
| Label | Use the sub-status name |
| Color | Use the sub-status color (`#RRGGBB`) |
| Icon | Use the sub-status icon. An icon name such as `o-truck`. |
| WIP limit | Have no limit. Otherwise the most cards the column may hold, at least 1. |

The color is only drawn as the column's top border. Column text never sits on it, so any color stays readable.

## Adding a column

**Add column**, at the end of the column strip, opens "Add a column". Choose a sub-status that isn't on the board yet (grouped by system status). The new column goes at the end of the board. When every sub-status already has a column, the dialog says so.

## Removing a column

**Remove column** asks first ("Remove column Packing?"):

- An empty column is removed after you confirm. Automations that move cards into it are removed with it.
- A column with cards asks where to move them ("Move the cards to"). The cards move to that column first, then the column is removed. Moving the cards also needs `kanbanCard.move`.
- Moving them into an exit column (cancelled, refunded, or failed) changes each of those orders' status on every board it is on, just as dropping a card there does. The dialog says so ("Moving 3 orders to Cancelled changes each order's status to Cancelled on every board it is on.") and needs **I understand** checked before it removes the column. Choosing another column asks again.
- When the engine won't move a card to the chosen column (for example, a pending order can't go straight to Refunded), nothing changes and the dialog says why under "Move the cards to". Choose another column and confirm again.

Removing runs once per confirmation, so a double click can't remove two columns. Cancel, Escape, or a click outside the dialog closes it and puts focus back on the column's **Remove column** button.

## Card widgets

Widgets are the small details on a card: total, item count, shipping method, tags, days in column, payment and fulfillment status, and any that satellites register. Which widgets a card shows is decided in this order:

1. the column's own list, when it has one;
2. otherwise the board's default list;
3. otherwise the engine's `artisanpack.ecommerce.kanban.default_card_widgets` (`total`, `item-count`, `customer`).

The card already names the customer, so the board leaves the engine's `customer` widget off a card when it would only repeat that name.

The widget picker opens from a column's **Card widgets** button (that column's list) or **Default card widgets** in the header (the board default). It shows:

- which list applies, in words, for example "This column has no widgets of its own, so it uses the board default.";
- the list, in the order cards show the widgets, with each widget's label and the package that provides it;
- **Add a widget**, to add a registered widget to the end of the list;
- for each widget, move up, move down, and remove buttons. The list also reorders by dragging;
- a settings form for widgets that declare settings (text, long text, number, yes/no, and choice fields). Settings of other kinds can be set in the admin and are kept as stored;
- a widget that is no longer installed is marked, and cards skip it;
- a preview of a card from the board with the list as it stands, once the settings are valid.

**Save widgets** stores the list through the engine, which validates the settings and shows its messages on the fields. Remove every widget from a column's list to make the column use the board default again.

To add your own widgets, see [Card Widgets](Extending-Card-Widgets).
