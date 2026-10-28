---
title: Automations
---

# Automations

Automations are engine rules that run when a card moves between columns, like emailing the customer when an order reaches Shipped or creating a shipment when it reaches Packed. The engine runs them as part of the move; the board shows what is set up and tells the mover what ran. The engine's kanban documentation lists the core triggers.

## The automations panel

The panel is a drawer on the right. Anyone who can view the board can open it:

- **Automations** in the board header lists every automation on the board.
- The lightning count in a column header ("2 automations run when a card moves into Packing"), or **Automations** in the column's edit toolbar, lists that column's automations in two groups: **Into this column** (automations whose target is the column) and **Out of this column** (automations that start from it).

Each row shows the trigger ("Unavailable trigger" when its satellite is gone), from → to ("Any column" when the automation doesn't care where the card came from), a one-line summary of its conditions, and whether it is on.

With `kanbanBoard.update`:

| Action | Does |
| --- | --- |
| On / Off | Switches the automation on or off |
| Delete | Asks first ("It stops running right away. This cannot be undone."), then deletes it, once per confirmation |
| Add automation | Opens the admin's automation form for the board (admin installed only) |
| Edit | Opens the admin's form for that automation (admin installed only) |

Without the ecommerce admin, the panel is list, toggle, and delete. Create and edit automations through the engine's REST API. A trigger's settings are never shown in the panel: they are encrypted and can hold webhook signing secrets.

Changes update the column badges at once and reach other open boards as a board config change.

## Feedback after a move

The person who moved a card gets one toast for that move, however many automations ran:

- When every automation succeeded, a success toast names those that ran.
- When any failed, an error toast ("1 automation failed for order 1003") names each failed trigger, says how many others ran, and links to the order's slide-out ("Open order 1003"), where the failure is in "Recent activity".

Only the mover sees the toast. Other viewers see the move itself. The board reads the results from the order timeline entries the move wrote (`kanban.automation_fired` and `kanban.automation_failed`) or, while it is live over Echo, from the engine's `automation-fired` and `automation-failed` broadcasts. See [Real-Time Updates](Realtime).
