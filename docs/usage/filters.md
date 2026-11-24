---
title: Filters
menu_order: 30
---

# Filters

The filter bar above the columns narrows the cards on a board. Its first row has **Search**, **Customer**, **Tags**, and **Products**. **More filters** opens a second row with the placed date range, payment and fulfillment status, **Stale cards only**, and any satellite controls. That row opens by itself when one of its controls is set, and the button shows how many are. While filters are set, the bar shows how many orders match ("5 of 47 orders shown") and a **Clear filters** button that clears them all.

On small screens the bar moves into a **Filters** drawer opened from the header, whose button shows how many filters are active.

## Controls

| Control | Query-string key | Matches |
| --- | --- | --- |
| Search | `q` | Order number, email, or customer name. Every word must match. |
| Customer | `customer` | One customer. Type to search by name or email; the search runs on the server. Only customers with a card on this board that the viewer may see are offered, labeled with their name and email (see [Who sees customer emails](#who-sees-customer-emails)). |
| Tags | `tags` | Orders with any of the chosen tags. The options are the tags on the board's cards. |
| Products | `products` | Orders containing any of the chosen products. Type to search; the search runs on the server. Only products in orders on this board that the viewer may see are offered. |
| Placed from | `from` | Orders placed on or after this date (`Y-m-d`) |
| Placed to | `to` | Orders placed on or before this date, the whole day included |
| Payment status | `payment` | Orders with any of the chosen payment statuses |
| Fulfillment status | `fulfillment` | Orders with any of the chosen fulfillment statuses |
| Stale cards only | `stale` | Cards that reached their column more than the engine's `kanban.stale_after_days` (3 by default) days ago |
| Satellite controls | `f[{key}]` | Whatever the satellite's card query filter does. See [Filter Controls](../extending/filter-controls.md). |

Every value lives in the query string, so a filtered board is a shareable link:

```text
/ecommerce-kanban/boards/3?q=smith&tags[]=rush&from=2026-03-01&stale=1
```

Invalid values in the URL are dropped rather than failing the page.

## What a filter changes

- Columns show only the matching cards. Each column's header keeps its full count and adds how many are shown ("3 shown") when the filter hides some. A column with no matches says "No matching orders in this column".
- WIP limits still count every card in the column, filtered or not.
- Changing a filter resets "Show more" paging, so each column starts again from its first page of matches.
- While a filter is set, a live update to a card re-renders the whole board instead of one card, since the change may show or hide it.

Filters are applied by the engine's card query (`KanbanCardQuery`).

## Restricting which cards people see

A host or satellite can narrow a board's cards with the engine's `ap.ecommerce.kanban.cardQuery` filter, for example to show each packer only their warehouse's orders. The board treats that filter as a visibility boundary: a card it hides is hidden on every path, not only in the columns.

| Path | What happens to a hidden card |
| --- | --- |
| Column card lists, "Show more" and the count it uses, polls, and live refreshes | Left out |
| Dragging, reordering, and "Move to…" | Refused, as if the card weren't on the board |
| "Remove from board" | Refused |
| The card slide-out, opened from the card, an `ec-kb-open-card` event, or a `?card={order}` link | Doesn't open |
| The Customer, Products, and Tags filter options | Its customer, products, and tags aren't offered (unless another visible card has them) |
| The widget picker's preview | Never used as the preview card |
| The board picker's card counts | Not counted |

The filter runs with the board's own filters cleared, so a hidden card stays hidden whatever the viewer filters by, and a visible card the viewer filtered out can still be moved or opened. `cardQuery` narrows what the board shows and acts on. It doesn't replace the engine's abilities: use `ap.ecommerce.abilities.*` and the `kanbanBoard`/`kanbanCard` abilities to decide who may use a board at all. See [Authorization](../advanced/authorization.md) and [Hooks](../reference/hooks.md#engine-hooks).

**Column totals and WIP limits still count hidden cards.** The engine enforces WIP limits against every card in a column, so a column's header count and its WIP badge ("4 / 5") include cards the viewer can't see. That keeps the board honest about why a column is full, but it means a viewer can tell that hidden cards exist and how many there are in each column. No hidden card's content (order number, customer, items) is shown. If even the numbers are sensitive, give those viewers a separate board instead.

Keep the filter to indexed columns: it runs on every card read. See [Performance](../advanced/performance.md).

## Who sees customer emails

The Customer filter's options show each customer's name and email, and the search matches emails, for anyone who can view the board (`kanbanBoard.view`). That is wider than the card slide-out, which shows contact details only with `order.view`. If some board viewers shouldn't see the emails of every customer on the board, narrow the cards they see with `ap.ecommerce.kanban.cardQuery`: the Customer filter offers and searches only customers with a card the viewer may see.
