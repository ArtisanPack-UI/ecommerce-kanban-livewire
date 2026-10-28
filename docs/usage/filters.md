---
title: Filters
---

# Filters

The filter bar above the columns narrows the cards on a board. On small screens it moves into a **Filters** drawer opened from the header, whose button shows how many filters are active. **Clear filters** in the header clears them all.

## Controls

| Control | Query-string key | Matches |
| --- | --- | --- |
| Search | `q` | Order number, email, or customer name. Every word must match. |
| Customer | `customer` | One customer, searched by name or email. Only customers with an order on this board are offered. |
| Tags | `tags` | Orders with any of the chosen tags. The options are the tags on the board's cards. |
| Products | `products` | Orders containing any of the chosen products. Only products in this board's orders are offered. |
| Placed from | `from` | Orders placed on or after this date (`Y-m-d`) |
| Placed to | `to` | Orders placed on or before this date, the whole day included |
| Payment status | `payment` | Orders with any of the chosen payment statuses |
| Fulfillment status | `fulfillment` | Orders with any of the chosen fulfillment statuses |
| Stale cards only | `stale` | Cards that reached their column more than the engine's `kanban.stale_after_days` (3 by default) days ago |
| Satellite controls | `f[{key}]` | Whatever the satellite's card query filter does. See [Filter Controls](Extending-Filter-Controls). |

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

Filters are applied by the engine's card query (`KanbanCardQuery`), so an `ap.ecommerce.kanban.cardQuery` filter that restricts cards applies here too.
