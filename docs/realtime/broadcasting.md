---
title: Broadcasting with Echo
---

# Broadcasting with Echo

Live boards need four things: the engine broadcasting board events, a Laravel broadcaster, a queue worker, and Laravel Echo in the browser. The board package itself needs no JavaScript of its own for this: it reads `window.Echo`.

## 1. Turn on engine broadcasting

In your `.env`:

```dotenv
ECOMMERCE_KANBAN_BROADCAST=true
```

This sets the engine's `artisanpack.ecommerce.kanban.broadcast`. With it on, the engine:

- broadcasts every board change as a `kanbanBoardEvent` on the private channel `private-ecommerce.kanban.board.{board}`;
- authorizes that channel for users who hold `kanbanBoard.view` on the board. You don't register the channel yourself.

The event carries ids, never rendered HTML: a `type` (`card-added`, `card-removed`, `card-moved`, `card-reordered`, `card-updated`, `config-changed`, `automation-fired`, `automation-failed`), the board, order, and column ids, and the acting user's id. The board re-reads what it shows through its own authorization.

Broadcasts are queued. Set `ECOMMERCE_KANBAN_BROADCAST_CONNECTION` and `ECOMMERCE_KANBAN_BROADCAST_QUEUE` to queue them somewhere other than the defaults. A broadcaster failure is logged and never fails the change that caused it.

## 2. Install a broadcaster and Echo

Laravel's installer sets up broadcasting, the channel authorization route, and Echo in one step:

```bash
php artisan install:broadcasting
```

It offers to install [Reverb](https://laravel.com/docs/reverb), installs `laravel-echo` and `pusher-js`, and creates `resources/js/echo.js`, which your main script imports. That file sets `window.Echo`. For Reverb it looks like this:

```js
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

window.Echo = new Echo( {
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
    wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
    forceTLS: ( import.meta.env.VITE_REVERB_SCHEME ?? 'https' ) === 'https',
    enabledTransports: [ 'ws', 'wss' ],
} );
```

For Pusher Channels, set `BROADCAST_CONNECTION=pusher` and your Pusher credentials, and configure Echo with `broadcaster: 'pusher'`. Any broadcaster Laravel supports works. See Laravel's [broadcasting documentation](https://laravel.com/docs/broadcasting).

Make sure the script that sets `window.Echo` loads on the board pages: it is part of your main script, which the standalone layout loads (see [Front-End Setup](Installation-Front-End#which-entries-the-board-loads)). Rebuild your assets afterwards.

## 3. Run the services

```bash
php artisan reverb:start   # with Reverb
php artisan queue:work     # delivers the queued broadcasts
```

## 4. Check it

Run the install command again. With broadcasting on and a broadcaster configured, it says:

```text
Make sure your main script sets up Laravel Echo (window.Echo); the board subscribes to the engine's private board channel.
```

Open a board in two browsers and move a card in one. The other's header should say "Live", and the card should move there without a reload.

## How the board listens

When a board loads with `realtime.enabled` on, the engine's flag on, and `window.Echo` present, it calls `window.Echo.private( 'ecommerce.kanban.board.{board}' )` and listens for `.kanbanBoardEvent`. It leaves the channel when you navigate away.

| Event type | What the board does |
| --- | --- |
| `card-added`, `card-removed`, `card-moved`, `card-reordered` | Re-renders the columns the event names |
| `config-changed` (or an event without columns) | Re-renders the board and re-reads its columns |
| `card-updated` | Re-renders just those cards, batched so each card re-renders once. With a filter set, the whole board re-renders, since the change may show or hide the card. |
| `automation-fired`, `automation-failed` | Collected for cards this browser moved, then shown as one toast to the mover |

The board watches the Echo connection. When it drops, or the channel refuses the subscription, the board polls until the connection is back. See [Polling Fallback](Realtime-Polling).

## Troubleshooting

If the header says "Updates every N seconds" (for example "Updates every 30 seconds") instead of "Live":

- Check `ECOMMERCE_KANBAN_BROADCAST=true` and that the config cache is fresh (`php artisan config:clear`).
- Check `realtime.enabled` is `true` in this package's config.
- Check that `window.Echo` exists in the browser console on a board page.
- Check the user holds `kanbanBoard.view` on the board: the channel authorization uses it.
- Check the broadcaster is running and the queue worker is processing broadcast jobs.
- Check that your broadcasting setup registered the channel authorization route (`php artisan install:broadcasting` does).
