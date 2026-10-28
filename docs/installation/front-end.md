---
title: Front-End Setup
---

# Front-End Setup

The package ships no CSS or JavaScript build. Your application's Tailwind build styles the board, and your main script loads drag-and-drop. Until you add both, the board renders unstyled and cards can't be dragged.

The browser test suite builds a host like this. Use it as a working reference:

- `tests/Browser/workbench/resources/css/app.css`
- `tests/Browser/workbench/resources/js/app.js`
- `vite.config.js`

## 1. Tailwind 4 and daisyUI 5

The board uses Tailwind CSS 4 and daisyUI 5 class names. Install them if your application does not already:

```bash
npm install -D tailwindcss @tailwindcss/vite daisyui@~5.0
```

Pin daisyUI to `~5.0`. Later 5.x releases hide `.tab-content`, so the component library's tab panels render empty ([livewire-ui-components#119](https://github.com/ArtisanPack-UI/livewire-ui-components/issues/119)).

## 2. Add the board to your Tailwind sources

`php artisan ecommerce-kanban:install` prints the board's two lines. Add them, and the component library's views if your app does not list them already, to your Tailwind entry, for example `resources/css/app.css`:

```css
@import 'tailwindcss';

@source "../../vendor/artisanpack-ui/ecommerce-kanban-livewire/resources/views/**/*.blade.php";
@source "../../vendor/artisanpack-ui/ecommerce-kanban-livewire/src/**/*.php";

/* The component library's views, if your app does not list them already. */
@source "../../vendor/artisanpack-ui/livewire-ui-components/resources/views/**/*.php";
@source "../../vendor/artisanpack-ui/livewire-ui-components/src/**/*.php";

@custom-variant dark (&:where(.dark, .dark *));

@plugin "daisyui" {
    themes: light --default, dark --prefersdark;
}
```

The paths are relative to the CSS file. Adjust them if your entry lives elsewhere. If you publish the board's views, your app's `resources/views` source usually covers `resources/views/vendor/ecommerce-kanban`.

Classes you add to cards with the `ap.ecommerceKanbanLivewire.cardClasses` filter must be in your Tailwind sources too. See [Hooks Reference](Extending-Hooks-Reference).

## 3. Install and import drag-and-drop

```bash
npm install @artisanpack-ui/livewire-drag-and-drop@^2.1.0
```

Add the import to your main script, for example `resources/js/app.js`:

```js
import '@artisanpack-ui/livewire-drag-and-drop';
```

Cards, column headers (in edit mode), and the widget picker's list drag through it, and the keyboard moves and their screen-reader announcements come from it too.

## 4. Build

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

Return an empty array to load nothing. When the board renders in the ecommerce admin's layout or a layout you configured, that layout loads its own assets. See [Layouts](Installation-Layouts).

## Real-time

For live boards, your main script also sets up Laravel Echo on `window.Echo`. See [Broadcasting with Echo](Realtime-Broadcasting).

## Checklist

| Symptom | Missing step |
| --- | --- |
| Board is unstyled | Steps 2 and 4, or a Vite entry name that does not match |
| Some board classes have no effect | The `src/**/*.php` source line (classes built in PHP) |
| Cards can't be dragged, and keyboard moves don't work | Step 3, or a `livewire-drag-and-drop` older than 2.1.0 |
| Tab panels in the component library are blank | daisyUI newer than 5.0.x (step 1) |
| Card classes from `cardClasses` have no effect | Those classes aren't in your Tailwind sources |
