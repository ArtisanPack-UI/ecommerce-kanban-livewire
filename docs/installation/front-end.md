---
title: Front-End Setup
menu_order: 30
---

# Front-End Setup

The package ships no CSS or JavaScript build. The board's controls come from `livewire-ui-components` 3.0, which renders with the ArtisanPack UI design system, and your main script loads drag-and-drop. Until you add both, the component library's controls render unstyled and cards can't be dragged.

The board's own styles (card, column, filter bar) don't need a build: the board inlines them itself, once per page. See [Embedding the board](#embedding-the-board-on-your-own-pages).

The browser test suite builds a host like this. Use it as a working reference:

- `tests/Browser/workbench/resources/css/app.css`
- `tests/Browser/workbench/resources/js/app.js`
- `vite.config.js`

## 1. The ArtisanPack UI design system

If your app already loads the design system for livewire-ui-components 3.0, skip this step. Otherwise run the component library's installer:

```bash
php artisan livewire-ui-components:install --assets=bundler
```

or set it up by hand:

```bash
npm install --save-dev @artisanpack-ui/css@^1 @artisanpack-ui/behavior@^1
```

Put `@apuiThemeScript` in your layout's `<head>`, before `@vite`, so the light or dark theme applies before the first paint. The package's standalone layout already does this.

## 2. Your main stylesheet

Import the design system in your main stylesheet, for example `resources/css/app.css`:

```css
@import '@artisanpack-ui/css';
```

That is all the board needs. Remove daisyUI if nothing else in your app uses it.

## 3. Tailwind CSS (optional)

Tailwind isn't required. The board's views use only the package's `ec-kb-*` classes and the design system's own classes. If your app builds with Tailwind CSS 4 anyway, declare the layer order as the first line, before Tailwind:

```css
@layer theme, base, apui, components, utilities;
@import 'tailwindcss';
@import '@artisanpack-ui/css';

/* Optional: let Tailwind scan the board's views and PHP classes. */
@source "../../vendor/artisanpack-ui/ecommerce-kanban-livewire/resources/views/**/*.blade.php";
@source "../../vendor/artisanpack-ui/ecommerce-kanban-livewire/src/**/*.php";
```

The `@layer` line puts the design system between Tailwind's Preflight and its utilities, so Preflight never restyles a component and utilities still win. The `@source` lines are optional: `php artisan ecommerce-kanban:install` prints them for Tailwind hosts. Add them if you publish the board's views and use Tailwind utilities in your copies, or if your own code returns Tailwind classes through the `ap.ecommerceKanbanLivewire.cardClasses` filter (in that case, the file that returns them must be in your sources). The paths are relative to the CSS file. Adjust them if your entry lives elsewhere.

The design system also ships an optional Tailwind preset that generates utilities from its tokens (`@import '@artisanpack-ui/css/tailwind';`). The board doesn't need it.

## 4. Your main script

Install drag-and-drop:

```bash
npm install @artisanpack-ui/livewire-drag-and-drop@^2.1.0
```

Then register the design system's behavior engine and import drag-and-drop in your main script, for example `resources/js/app.js`:

```js
import apuiBehavior from '@artisanpack-ui/behavior/alpine';
import '@artisanpack-ui/livewire-drag-and-drop';

document.addEventListener( 'alpine:init', () => window.Alpine.plugin( apuiBehavior ) );
```

The behavior engine runs the component library's dialogs, drawers, dropdown menus, comboboxes, date pickers, and tooltips. Cards, column headers (in edit mode), and the widget picker's list drag through drag-and-drop, and the keyboard moves and their screen-reader announcements come from it too.

Alpine comes with Livewire. Do not import it again. The board needs Alpine's standard build, not its CSP build. See [Content Security Policy](../advanced.md#content-security-policy).

## 5. Build

```bash
npm run build   # or npm run dev
```

## Which entries the board loads

The standalone layout, and the board pages under cms-framework's admin layout, load your Vite entries `resources/css/app.css` and `resources/js/app.js`, but only when a Vite build (`public/build/manifest.json`) or a running dev server is present. If your entries have other names, change them with the `ap.ecommerceKanbanLivewire.layout.viteEntries` filter:

```php
addFilter( 'ap.ecommerceKanbanLivewire.layout.viteEntries', function ( array $entries ): array {
    return [ 'resources/css/admin.css', 'resources/js/admin.js' ];
} );
```

Return an empty array to load nothing. When the board renders in the ecommerce admin's layout or a layout you configured, that layout loads its own assets. See [Layouts](layouts.md).

## Embedding the board on your own pages

The board component inlines its own stylesheet (`ecommerce-kanban::partials.styles`, with the Vite CSP nonce) once per page on its first render, so a page of yours that embeds `<livewire:artisanpack-ecommerce-kanban-board>` needs no extra include. A page that embeds only the board picker should add `@include( 'ecommerce-kanban::partials.styles' )` once. See [Embedding Components](../extending/embedding-components.md#a-board-on-your-own-route).

## Real-time

For live boards, your main script also sets up Laravel Echo on `window.Echo`. See [Broadcasting with Echo](../realtime/broadcasting.md).

## Checklist

| Symptom | Missing step |
| --- | --- |
| Controls look unstyled, or dark mode flashes on load | The design system isn't loaded (steps 1 and 2), the `@layer` line isn't first in a Tailwind stylesheet (step 3), `@apuiThemeScript` is missing, or a Vite entry name doesn't match |
| The board picker's cards are unstyled on your own page | `@include( 'ecommerce-kanban::partials.styles' )` is missing on a page that embeds only the picker (see [Embedding the board](#embedding-the-board-on-your-own-pages)) |
| The board is styled, but menus, dialogs, and comboboxes don't open | The behavior engine isn't registered (step 4) |
| Cards can't be dragged, and keyboard moves don't work | Drag-and-drop isn't imported (step 4), or a `livewire-drag-and-drop` older than 2.1.0 |
| Card classes from `cardClasses` have no effect | Nothing in your stylesheet styles those classes, or (for Tailwind utilities) the file that returns them isn't in your Tailwind sources |
