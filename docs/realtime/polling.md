---
title: Polling Fallback
---

# Polling Fallback

Without engine broadcasts or Echo, a board polls. No setup is needed: it is on by default, so every board converges on the latest state within `realtime.poll_interval` seconds.

## How a poll works

Each poll calls the board's `poll()` action. The server compares a fingerprint of each column's cards with the previous poll, in one query, and:

- returns nothing when nothing changed, so an idle board costs one small request per interval;
- returns just the columns whose cards changed (a card joined, left, moved within the column, or its order was updated), which the page morphs in place;
- asks for a full board render when the board's setup changed (a column added, removed, reordered, or edited, or an automation badge changed).

Polls only read, so they don't count against the board's [rate limit](Advanced#rate-limits), however short the interval.

## When it pauses

A board skips a poll while:

- a card is being dragged;
- a move is still in flight;
- the browser tab is hidden;
- the browser is offline.

It polls at once when the tab is shown again.

## Tuning

```php
// config/artisanpack/ecommerce-kanban-livewire.php
'realtime' => [
    'enabled'       => true,
    'poll_interval' => 15, // seconds; 0 turns polling off
],
```

A shorter interval shows other people's changes sooner, at the cost of one request per open board per interval. With `poll_interval` at `0` and no Echo, the header says "Not updating automatically" and the board only changes on reload. `realtime.enabled` at `false` turns off both polling and Echo.

Polling is also the fallback for live boards: when the Echo connection drops, the board polls until it reconnects. See [Broadcasting with Echo](Realtime-Broadcasting).
