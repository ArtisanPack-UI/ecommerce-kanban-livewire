---
title: Troubleshooting
menu_order: 99
---

# Troubleshooting

## The board is unstyled

**Cause:** Your application's Vite build doesn't load the design system or the board's classes. The package ships no CSS build of its own.

**Fix:**

1. Add the stylesheet lines that `php artisan ecommerce-kanban:install` prints to your main stylesheet (for example `resources/css/app.css`): the `@layer` line, `@import '@artisanpack-ui/css';`, and the board's `@source` lines. Check the relative paths from that file to `vendor/`.
2. Make sure Tailwind 4 and `@artisanpack-ui/css` are installed. `php artisan livewire-ui-components:install --assets=bundler` sets up the design system if your app doesn't load it already.
3. Rebuild: `npm run build`, or run `npm run dev`.
4. The standalone layout loads `resources/css/app.css` and `resources/js/app.js` only when `public/build/manifest.json` or a Vite dev server exists. If your entries have other names, set them with `ap.ecommerceKanbanLivewire.layout.viteEntries`.

See [Front-End Setup](installation/front-end.md).

## Components look half-styled after upgrading to 2.0

**Cause:** daisyUI is still loaded, or the design system's layer order is missing.

**Fix:** If nothing else uses daisyUI, remove its `@plugin "daisyui"` blocks from your stylesheet and uninstall the `daisyui` package, then make `@layer theme, base, apui, components, utilities;` the first line of your stylesheet, before `@import 'tailwindcss';`. Rebuild.

## Dialogs, drawers, or the card slide-out don't open

**Cause:** The design system's behavior engine isn't registered. The card actions menus, the filter bar's date pickers and comboboxes, and the tooltips need it too.

**Fix:** Register it in `resources/js/app.js`, then rebuild:

```js
import apuiBehavior from '@artisanpack-ui/behavior/alpine';

document.addEventListener( 'alpine:init', () => window.Alpine.plugin( apuiBehavior ) );
```

## Cards can't be dragged

**Cause:** Drag-and-drop isn't loaded, or it is older than 2.1.0. Keyboard moves and their announcements come from the same package, so they stop working too.

**Fix:**

```bash
npm install @artisanpack-ui/livewire-drag-and-drop@^2.1.0
```

Add `import '@artisanpack-ui/livewire-drag-and-drop';` to your main script and rebuild. If cards still don't drag, check that the user holds `kanbanCard.move` on the board: without it the board is read-only.

## Every board returns 403

**Cause:** Nothing grants access yet. The engine denies every ability by default.

**Fix:** Define the umbrella gate in a service provider's `boot()`, or grant the individual abilities:

```php
use Illuminate\Support\Facades\Gate;

Gate::define( 'ecommerce.admin', fn ( $user ): bool => $user->is_admin );
```

See [Authorization](advanced/authorization.md).

## A board returns 404

**Cause:** The board is switched off, and the user doesn't hold `kanbanBoard.update`, or the board doesn't exist.

**Fix:** Switch the board back on in the ecommerce admin or through the engine's REST API, or grant `kanbanBoard.update` to the staff who manage it. See [Board Picker](usage/board-picker.md#switched-off-boards).

## The picker says there are no boards

**Cause:** No active board exists yet. The board can't create one on its own.

**Fix:** Create one in the ecommerce admin's **Kanban boards** screen, or with `POST /api/ecommerce/v1/kanban/boards`. On a development database, `php artisan ecommerce:seed-demo` seeds a "Fulfillment" board.

## The header says "Updates every 30 seconds" instead of "Live"

**Cause:** The board can't listen over Echo, so it polls. The engine's broadcasts are off, `window.Echo` is missing, the channel refused the subscription, or the broadcaster isn't running.

**Fix:** Set `ECOMMERCE_KANBAN_BROADCAST=true`, clear the config cache, set up Laravel Echo in your main script, and run the broadcaster and a queue worker. `php artisan ecommerce-kanban:install` reports what is missing. See [Broadcasting with Echo](realtime/broadcasting.md#troubleshooting).

## A toast says "Too many changes in a short time"

**Cause:** The user went over the engine's `ecommerce.admin.mutate` rate limit (120 changes a minute by default). The change didn't run.

**Fix:** Wait the number of seconds the toast gives, then try again. Raise the limit with `ECOMMERCE_RATE_ADMIN_MUTATE_PER_USER` if your staff hit it in normal work. See [Rate limits](advanced.md#rate-limits).

## A move is refused and the card jumps back

**Cause:** The engine refused the move: the column is at its WIP limit, the order's status can't make that transition, or a satellite's `ap.ecommerce.kanban.cardMoving` hook vetoed it.

**Fix:** Read the toast. It names the reason. See [Why a move is refused](usage/moving-cards.md#why-a-move-is-refused).

## Board links and the switcher are missing

**Cause:** The package's routes are off (`board.routes_enabled` is `false`) and nothing supplies a board URL, so the picker lists boards without links and the switcher and new-tab links are left out.

**Fix:** Return your own URLs from the `ap.ecommerceKanbanLivewire.boardUrl` and `ap.ecommerceKanbanLivewire.pickerUrl` filters. See [Embedding Components](extending/embedding-components.md#pointing-links-at-your-routes).

## Classes from `cardClasses` have no effect

**Cause:** Tailwind only generates classes it finds in your sources.

**Fix:** Add the file that returns the classes to your Tailwind `@source` lines, or use classes your app already uses. Rebuild.

## A fix from a new release doesn't show up

**Cause:** A published view in `resources/views/vendor/ecommerce-kanban` shadows the package's copy.

**Fix:** Delete the published views you didn't change, and compare the ones you did with the package's current copies. See [Extending](extending.md#publishing-views).
