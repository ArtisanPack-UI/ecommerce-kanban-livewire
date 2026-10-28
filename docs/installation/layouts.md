---
title: Layouts
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
- Livewire's assets and your Tailwind build, as on any Livewire page.
- `<x-artisanpack-toast />`: the board shows refused moves, automation results, and rate-limit notices as toasts.

cms-framework's admin layout loads none of the host's assets, so under it each page pushes your Vite entries and `@livewireStyles` onto `styles`, and the toast container, `@livewireScripts`, and the rate-limit notice onto `scripts` (`ecommerce-kanban::partials.page-assets`). The ecommerce admin's layout and the standalone layout load them themselves, and a layout you configure is expected to as well.

## 1. Standalone layout

`ecommerce-kanban::layouts.app` is used when nothing else applies. It includes:

- `<html lang>` set from the app locale, and `scroll-padding-top` so a focused control never scrolls under the sticky header;
- a "Skip to content" link;
- a sticky header with "Kanban" linking to the board picker, an optional `header` section for your own buttons, and the theme toggle;
- your Vite entries (see [Front-End Setup](Installation-Front-End#which-entries-the-board-loads)), `@livewireStyles`, `@livewireScripts`, the `styles` and `scripts` stacks, the toast container, and the rate-limit notice.

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
<html lang="{{ str_replace( '_', '-', app()->getLocale() ) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield( 'title' ) &middot; {{ config( 'app.name' ) }}</title>
    @vite( [ 'resources/css/app.css', 'resources/js/app.js' ] )
    @livewireStyles
    @stack( 'styles' )
</head>
<body>
    <a href="#content" class="sr-only focus:not-sr-only">{{ __( 'Skip to content' ) }}</a>

    @include( 'partials.staff-header' )

    <main id="content">
        @yield( 'content' )
    </main>

    <x-artisanpack-toast />

    @livewireScripts
    @include( 'ecommerce-kanban::partials.rate-limit-notice' )
    @stack( 'scripts' )
</body>
</html>
```

`ecommerce-kanban::partials.rate-limit-notice` turns a rate-limited board update (HTTP 429) into a toast with the wait time, instead of Livewire's error dialog. Include it after `@livewireScripts`.

If your header is sticky or fixed, give the page enough `scroll-padding-top` that a focused card or control never sits under it (WCAG 2.2 SC 2.4.11). The standalone layout uses `5rem`. Your layout replaces the package's, so its skip link, header, and scroll padding are yours to provide.

## Component layouts

Page views use `@extends`, which can't extend a Blade component such as `<x-app-layout>` or `<x-layouts.app>`. Create a wrapper view that does, and point `layout` at it. For example, with a starter kit's `resources/views/components/layouts/app.blade.php`, save this as `resources/views/layouts/kanban.blade.php`:

```blade
<x-layouts.app :title="$__env->yieldContent( 'title' )">
    @yield( 'content' )

    <x-artisanpack-toast />
    @include( 'ecommerce-kanban::partials.rate-limit-notice' )
</x-layouts.app>
```

The component layout must load your Vite entries and Livewire's assets, and render the `styles` and `scripts` stacks if you use them.
