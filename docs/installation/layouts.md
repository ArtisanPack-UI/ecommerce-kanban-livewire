---
title: Layouts
menu_order: 40
---

# Layouts

The picker and every board are controller actions that return a page view embedding one Livewire component. Each page view starts with `@extends( $ecommerceKanbanLayout )`. A view composer on `ecommerce-kanban::pages.*` sets `$ecommerceKanbanLayout` to the first of these that exists:

| Order | Layout | When |
| --- | --- | --- |
| 1 | The `layout` config value | It is set and the view exists |
| 2 | `cms::admin.layouts.app` | `artisanpack-ui/cms-framework` is installed |
| 3 | `ecommerce-admin::layouts.app` | `artisanpack-ui/ecommerce-admin-livewire` is installed and active |
| 4 | `ecommerce-kanban::layouts.app` | Always: the package's standalone layout |

The board is a staff screen, so by default it sits inside whichever admin the host already has.

## The layout contract

Every candidate shares one contract:

- `@yield( 'content' )`: the page body.
- `@yield( 'title' )`: each page sets a plain-text `title` section (the board's name, or "Kanban boards" on the picker). Optional.
- `@stack( 'styles' )` and `@stack( 'scripts' )`.
- `@apuiThemeScript` in `<head>`, before `@vite`, so the design system's light or dark theme applies before the first paint.
- Livewire's assets and your Vite entries, with the design system's CSS and its behavior engine (see [Front-End Setup](front-end.md)), as on any Livewire page.
- `<x-artisanpack-toaster />`: the board shows refused moves, automation results, and rate-limit notices as toasts. In livewire-ui-components 3.0, `<x-artisanpack-toast />` is a single static toast, not the container, so a layout that uses it shows no board toasts at all.

The board inlines its own stylesheet once per page (and the page views include `ecommerce-kanban::partials.styles` for the picker), so a layout doesn't need to.

For right-to-left locales, set `dir="rtl"` on `<html>` (for example with `KanbanStyles::textDirection()`; see [PHP API](../reference/php-api.md#supportkanbanstyles)). The board sets `dir` on its own root, but its dialogs and drawers render outside it and follow the page.

cms-framework's admin layout loads none of the host's assets, so under it each page pushes your Vite entries and `@livewireStyles` onto `styles`, and the toaster (`<x-artisanpack-toaster />`), `@livewireScripts`, and the rate-limit notice onto `scripts` (`ecommerce-kanban::partials.page-assets`). The ecommerce admin's layout and the standalone layout load them themselves, and a layout you configure is expected to as well.

## 1. Standalone layout

`ecommerce-kanban::layouts.app` is used when nothing else applies. It includes:

- `<html lang>` set from the app locale, and `scroll-padding-top` so a focused control never scrolls under the sticky header;
- a "Skip to content" link;
- `dir="rtl"` for right-to-left locales (Arabic, Hebrew, Persian, Urdu, and others);
- a sticky header with the "Kanban" brand tile linking to the board picker, an optional `header` section for your own buttons, and the theme toggle;
- `@apuiThemeScript`, your Vite entries (see [Front-End Setup](front-end.md#which-entries-the-board-loads)), the board's inlined styles, `@livewireStyles`, `@livewireScripts`, the `styles` and `scripts` stacks, the toaster, and the rate-limit notice.

The page title is the page's `title` section followed by `· {app.name}`.

To change the markup itself, publish the views and edit `resources/views/vendor/ecommerce-kanban/layouts/app.blade.php`, keeping the contract above.

## 2. Your own layout

Point `layout` at your layout:

```php
// config/artisanpack/ecommerce-kanban-livewire.php
'layout' => 'layouts.staff',
```

and make it meet the contract. A minimal example:

```blade
<!DOCTYPE html>
<html lang="{{ str_replace( '_', '-', app()->getLocale() ) }}" dir="{{ \ArtisanPackUI\EcommerceKanbanLivewire\Support\KanbanStyles::textDirection() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield( 'title' ) &middot; {{ config( 'app.name' ) }}</title>
    @apuiThemeScript
    @vite( [ 'resources/css/app.css', 'resources/js/app.js' ] )
    @livewireStyles
    @stack( 'styles' )
</head>
<body>
    <x-artisanpack-button href="#content" size="sm" class="staff-skip-link">{{ __( 'Skip to content' ) }}</x-artisanpack-button>

    @include( 'partials.staff-header' )

    <main id="content">
        @yield( 'content' )
    </main>

    <x-artisanpack-toaster />

    @livewireScripts
    @include( 'ecommerce-kanban::partials.rate-limit-notice' )
    @stack( 'scripts' )
</body>
</html>
```

`ecommerce-kanban::partials.rate-limit-notice` turns a rate-limited board update (HTTP 429) into a toast with the wait time, instead of Livewire's error dialog. It dispatches the design system's `toast` browser event (`{ title, description, color }`), so the page needs `<x-artisanpack-toaster />`. Include it after `@livewireScripts`.

The skip link in the example is yours to style (for example, visually hidden until focused).

If your header is sticky or fixed, give the page enough `scroll-padding-top` that a focused card or control never sits under it (WCAG 2.2 SC 2.4.11). The standalone layout uses `5rem`. Your layout replaces the package's, so its skip link, header, and scroll padding are yours to provide.

## Component layouts

Page views use `@extends`, which can't extend a Blade component such as `<x-app-layout>` or `<x-layouts.app>`. Create a wrapper view that does, and point `layout` at it. For example, with a starter kit's `resources/views/components/layouts/app.blade.php`, save this as `resources/views/layouts/kanban.blade.php`:

```blade
<x-layouts.app :title="$__env->yieldContent( 'title' )">
    @yield( 'content' )

    <x-artisanpack-toaster />
    @include( 'ecommerce-kanban::partials.rate-limit-notice' )
</x-layouts.app>
```

The component layout must print `@apuiThemeScript`, load your Vite entries and Livewire's assets, and render the `styles` and `scripts` stacks if you use them. If it already renders `<x-artisanpack-toaster />`, leave it out of the wrapper: the page needs only one.
