---
title: Real-Time Overview
---

# Real-Time Updates

An open board keeps up with changes made elsewhere: another person's move, an order added to the board, a column edited, an order paid or shipped. It does this one of two ways:

- **Live**, over Laravel Echo on the engine's private board channel, when the engine broadcasts and the page has Echo.
- **Polling**, every `realtime.poll_interval` seconds (30 by default), otherwise.

Both re-render only what changed: the columns a move touched, or the cards an order update touched. Nothing else on the board redraws, so a person in the middle of something keeps their place.

## In this section

- [Broadcasting with Echo](Realtime-Broadcasting) - Turning on engine broadcasts, Reverb or Pusher, and Echo
- [Polling Fallback](Realtime-Polling) - How polling works and how to tune or turn it off

## Which mode a board uses

| `realtime.enabled` | Engine `kanban.broadcast` | `window.Echo` on the page | Mode |
| --- | --- | --- | --- |
| `true` | `true` | Yes, and connected | Live |
| `true` | `true` | Missing, not connected, or channel refused | Polling |
| `true` | `false` | Any | Polling |
| `false` | Any | Any | Off: the board only changes on reload |

A `realtime.poll_interval` of `0` turns polling off, so the board is either live or off.

A board drops to polling by itself when the Echo connection goes down, and goes back to live when it reconnects. When the browser is offline it says so and waits.

## The connection indicator

The board header shows which mode applies, in text:

| Text | Meaning |
| --- | --- |
| Live | Listening over Echo |
| Updates every few seconds | Polling |
| Offline | The browser has no network |
| Not updating automatically | Neither Echo nor polling: changes show on reload |

## What every mode does

- **Your own moves don't bounce.** The board ignores the broadcast of a move you made while it is still in flight.
- **A drag in progress is protected.** When someone else moves the card you are dragging, your drag is canceled and the board says so: "This card was moved by someone else, so your move was canceled."
- **The card slide-out stays current.** An open slide-out re-renders when its order changes.
- **Automation results reach the mover.** The person who moved a card gets one toast naming the automations that ran or failed. See [Automations](Usage-Automations#feedback-after-a-move).

`php artisan ecommerce-kanban:install` reports which mode new boards will use and what is missing for live updates.
