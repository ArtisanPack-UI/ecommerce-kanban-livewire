---
title: Performance
---

# Performance

The board is built to stay fast with thousands of cards. Every card query is fixed per column, so a column costs the same however many cards it holds, and only what changed is re-rendered.

## Loading a board

- **Headers first.** The first render draws the column headers only. The page then loads the cards of the columns on screen.
- **Lazy columns.** Columns further along show skeletons and load their cards when they scroll into view, in batches.
- **Collapsed columns never load.** A column the user collapsed (remembered per board in the browser) runs no card queries until it is expanded.
- **Paging.** A column shows `cards.per_column` cards (25 by default); "Show more" adds the same number again.
- **Tag options load with the cards**, not on every request.

## Re-rendering only what changed

| Change | What re-renders |
| --- | --- |
| A drop or keyboard move | The two columns it touched. The board render is skipped. |
| A live card move, reorder, addition, or removal | The columns the event names |
| A live `card-updated` event | Just those cards, batched so each re-renders once (up to 25 at a time; more fall back to a board render) |
| A poll | Only the columns whose cards changed; nothing when nothing changed |
| A column, widget, or automation change | The board |
| A filter change | The board |

Column and card HTML is morphed into the page in place, and morphs wait while a card is being dragged. In "Edit board" mode, where columns depend on their neighbors, changes re-render the whole board.

## Query budgets

`tests/Feature/Performance/QueryBudgetTest.php` fixes the number of queries for each operation, for any number of cards:

| Operation | Queries |
| --- | --- |
| Loading a board | 9, plus 3 per open column |
| Paging a column ("Show more") | 15 |
| Re-rendering cards after `card-updated` | 6 |
| Polling a board that hasn't changed | 6 |
| Opening the card slide-out | 19, for one order line or many |

A 1,000-card board with five columns loads in 24 queries (9 + 5 × 3) and renders 25 cards per column.

## Polling cost

A poll compares a fingerprint of each column's cards with the last poll, in one query of a few narrow columns per card, and renders nothing when nothing changed. Lengthen `realtime.poll_interval` on stores with many open boards, or turn on broadcasting so boards don't poll at all. See [Real-Time Updates](Realtime).

## Writing extensions

- Keep `ap.ecommerceKanbanLivewire.boardRendered` cheap: it runs on every board render.
- Card action and toolbar abilities are checked once per render, not per card. Do the same in your own filters: don't query per card in `cardActions` or `cardClasses` when you can work from the order you are given.
- An `ap.ecommerce.kanban.cardQuery` filter runs on every card read, including polls and card refreshes. Keep it to indexed columns.
