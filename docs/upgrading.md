---
title: Upgrading
menu_order: 90
---

# Upgrading

## From 1.x to 2.0

2.0 moves the board onto the ArtisanPack UI design system through `artisanpack-ui/livewire-ui-components` 3.0 and drops daisyUI. Tailwind CSS is now optional. Every screen is rebuilt. The engine side is unchanged: boards, columns, cards, widgets, automations, abilities, routes, config keys, and hooks all carry over. Most of the upgrade is front-end setup.

### 1. Update the packages

```bash
composer require artisanpack-ui/ecommerce-kanban-livewire:^2.0 artisanpack-ui/livewire-ui-components:^3.0
```

`artisanpack-ui/ecommerce` 1.2 or later still works. If you use `artisanpack-ui/ecommerce-admin-livewire`, upgrade it to 2.0 in the same step: 1.x needs livewire-ui-components 2.x and won't install next to the board.

### 2. Load the design system

Skip this if your app already loads the design system for livewire-ui-components 3.0. Otherwise run:

```bash
php artisan livewire-ui-components:install --assets=bundler
```

or set it up by hand:

```bash
npm install --save-dev @artisanpack-ui/css@^1 @artisanpack-ui/behavior@^1
```

Main stylesheet (for example `resources/css/app.css`), without Tailwind:

```css
@import '@artisanpack-ui/css';
```

If your app builds with Tailwind CSS 4, declare the layer order first, so Tailwind's Preflight never restyles a component and your utilities still win:

```css
@layer theme, base, apui, components, utilities;
@import 'tailwindcss';
@import '@artisanpack-ui/css';
```

The `@layer` line must be the first line. Tailwind isn't required: the board's views use only the package's `ec-kb-*` classes and the design system's own classes. The board's `@source` lines from 1.x are optional now. Keep them only if you want Tailwind to scan the package's views and PHP classes. See [Front-End Setup](installation/front-end.md#3-tailwind-css-optional).

Main script (for example `resources/js/app.js`):

```js
import apuiBehavior from '@artisanpack-ui/behavior/alpine';
import '@artisanpack-ui/livewire-drag-and-drop';

document.addEventListener( 'alpine:init', () => window.Alpine.plugin( apuiBehavior ) );
```

Run `php artisan ecommerce-kanban:install` again to print these lines for your setup, then `npm run build`.

### 3. Remove daisyUI

If nothing else in your app uses daisyUI, delete the `@plugin "daisyui" { … }` block (and the `@custom-variant dark` line the 1.x docs added) from your stylesheet, and run `npm uninstall daisyui`. The `~5.0` pin from 1.x is no longer needed. If other parts of your app still use daisyUI, keep it, but make sure the `@layer` line comes first. See [Troubleshooting](troubleshooting.md#components-look-half-styled-after-upgrading-to-20).

### 4. Update your layout

This applies only if you set `layout` to your own layout, or embed the board on your own pages:

- Replace `<x-artisanpack-toast />` with `<x-artisanpack-toaster />`. In livewire-ui-components 3.0, `toast` is a single static toast, so the old tag shows no board toasts at all.
- Add `@apuiThemeScript` to `<head>`, before `@vite`, so the light or dark theme applies before the first paint.
- Nothing to add for the board's own styles: they now ship as a stylesheet the board inlines itself, instead of Tailwind classes. Only a page that embeds the board picker on its own needs `@include( 'ecommerce-kanban::partials.styles' )`.
- Keep setting `dir="rtl"` on `<html>` for right-to-left locales. The board sets `dir` on its own root, but its dialogs and drawers render at the end of the page and follow the page's direction.

The package's standalone layout, and its pages under the cms-framework and ecommerce admin layouts, do all of this already. See [Layouts](installation/layouts.md).

### 5. Re-publish overridden views

Every view was rebuilt on new markup, `ec-kb-*` classes, and livewire-ui-components 3.0 components. Views published from 1.x (`resources/views/vendor/ecommerce-kanban`) render the old daisyUI markup and miss 2.0 fixes. Delete them, then publish again and re-apply only the changes you still need:

```bash
php artisan vendor:publish --tag=ecommerce-kanban-views --force
```

Where you can, use the [extension points](extending.md) instead of published views.

### 6. Check your translation overrides

2.0 adds Brazilian Portuguese, Japanese, Simplified Chinese, Italian, and Arabic. These strings were removed, so overrides for them do nothing now:

- ":board: :column"
- "Editing the board. Drag column headers or use the arrows to reorder columns; changes save as you make them." (now "Editing the board" plus "Drag column headers or use the arrows to reorder columns. Changes save as you make them.")
- "Open order :number"
- "Order :number — :email" (now "Order :number · :customer")
- "This board is switched off. Only people who can edit it see it." (now "This board is switched off" plus "Only people who can edit it see it.")
- "Toggle dark mode" (the livewire-ui-components 3.0 theme toggle brings its own label)

Some German strings changed to match the engine, and the German catalogue now addresses the user as "Sie" throughout. Re-check `lang/vendor/ecommerce-kanban/de.json` if you published it.

### 7. Extensions

- **Card classes** (`ap.ecommerceKanbanLivewire.cardClasses`): daisyUI color utilities such as `border-l-warning` no longer exist. Return your own class names and style them in your stylesheet with the design tokens, or use Tailwind utilities if your app builds with Tailwind. See [Hooks](reference/hooks.md#cards).
- **Widget views** (`ap.ecommerceKanbanLivewire.widgetView`): replace daisyUI classes (`badge`, `badge-sm`, …) with `<x-artisanpack-badge>`. See [Card Widgets](extending/card-widgets.md#your-own-view).
- **`WidgetViews::TONES` / `WidgetViews::tone()`**: entries no longer have a `class` key. Read `color` (the design system Badge color) and `icon`. `tone()` takes an optional widget key as its second argument, and neutral tags read in `info`. See [PHP API](reference/php-api.md).
- **Filter controls** (`ap.ecommerceKanbanLivewire.filters`): option arrays in `[ 'id' => …, 'name' => … ]` keep working, and options may also use livewire-ui-components 3.0's `value` / `label` keys. The board maps either shape to the 3.0 `value` / `label` options it renders. Options with neither a scalar `id`/`value` nor a scalar `name`/`label` are dropped.
- **Card query filters** (`ap.ecommerce.kanban.cardQuery`): a card your filter hides is now hidden everywhere on the board, not only in the columns. It can't be moved, removed, or opened in the slide-out (including `?card=`), and it is left out of the Customer, Products, and Tags filter options, the widget preview, and the picker's card counts. Column totals and WIP badges still count it, since the engine enforces WIP limits against every card. A filter that only reads the filter bar's `$filters->extra` values is unaffected. See [Filters](usage/filters.md#restricting-which-cards-people-see).
- **Card actions** (`ap.ecommerceKanbanLivewire.cardActions`): keys are now unique. The first action per key wins, and an action keyed `open`, `move`, or `remove` no longer replaces the built-in one. Give your actions their own keys. See [Card and Toolbar Actions](extending/card-actions.md#unique-keys).
- **Toast text**: board toasts are plain text, so HTML in a column label or engine message shows literally instead of being escaped. The automation-failure toast no longer links to the order. Open the card to see the failure.

### 8. Content Security Policy

The board still needs Alpine's standard build and inline style attributes, so it doesn't run under a strict Content Security Policy or Livewire 4's `csp_safe` mode. See [Content Security Policy](advanced.md#content-security-policy).

### What hasn't changed

Routes and route names, config keys, abilities, the `ap.ecommerceKanbanLivewire.*` hooks and their arguments, the Livewire component aliases (`artisanpack-ecommerce-kanban-*`), the Blade component aliases (`x-artisanpack-ec-kb-*`, where `empty` gains an optional `firstUse` prop), real-time channels and events, and rate limiting.
