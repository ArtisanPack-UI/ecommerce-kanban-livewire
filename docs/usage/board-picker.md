---
title: Board Picker
---

# Board Picker

The picker is the board package's front door, at `/{route_prefix}` (route `artisanpack.ecommerce.kanban.boards.index`). It needs `kanbanBoard.viewAny`.

## Where it sends you

Opening the picker takes you straight to a board when there is an obvious one:

1. the board you opened last, if you can still see it;
2. otherwise, the only board, when you can see exactly one.

Otherwise it lists the boards. `?pick=1` always shows the list; the "All boards" link in the board switcher and the "Kanban" brand link in the standalone layout use it.

The last board is remembered per user in the session, each time a board opens.

## The list

The picker lists the active boards you may view (`kanbanBoard.view`), in their configured order. Each shows its name, its description, how many cards are on it, and "Last opened" on the board you opened last. Inactive boards are left out.

With no boards, the picker says so. When the ecommerce admin is installed, it shows a **Create board** button linking to the admin's board list.

## Switching boards

On a board, the **Switch board** menu next to the board name lists the other boards you can open, with the current one marked, and an **All boards** link to the picker. It appears when you can see more than one board or the picker has a URL.
