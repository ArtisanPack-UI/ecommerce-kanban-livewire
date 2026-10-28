---
title: Extending Overview
---

# Extending

Satellites (print providers, shipping labels, chat notifications) and host applications change the board through filters and actions, and can reuse its Livewire components on their own pages. Nothing here requires publishing views.

## In this section

- [Hooks Reference](Extending-Hooks-Reference) - Every filter and action the board offers, with signatures
- [Card and Toolbar Actions](Extending-Card-Actions) - Add items to a card's actions menu and buttons to the board header
- [Card Widgets](Extending-Card-Widgets) - Register widgets with the engine and render them with your own Blade view
- [Filter Controls](Extending-Filter-Controls) - Add controls to the filter bar and narrow the cards
- [Embedding Components](Extending-Embedding-Components) - Turn the routes off and mount the board on your own pages

## Filters and actions

The board's hooks are all named `ap.ecommerceKanbanLivewire.*`, and `ArtisanPackUI\EcommerceKanbanLivewire\Support\Hooks` lists them as constants. Register them with the `artisanpack-ui/hooks` helpers from a service provider's `boot()`:

```php
use ArtisanPackUI\Ecommerce\Models\KanbanBoard;
use ArtisanPackUI\Ecommerce\Models\KanbanColumn;
use ArtisanPackUI\Ecommerce\Models\Order;
use ArtisanPackUI\EcommerceKanbanLivewire\Support\Authorization;

addFilter( 'ap.ecommerceKanbanLivewire.cardActions', function ( array $actions, Order $order, KanbanColumn $column, KanbanBoard $board ): array {
    // `ability` is checked against the board, so check order-level abilities here, with the order.
    if ( Authorization::allows( auth()->user(), 'order.update', $order ) ) {
        $actions[] = [
            'key'   => 'reprint',
            'label' => __( 'Reprint label' ),
            'icon'  => 'o-printer',
            'event' => 'print-labels.reprint',
        ];
    }

    return $actions;
} );
```

A filter receives the value first and must return it. Every extra argument is passed to the callback, so declare only the ones you need. Entries a filter returns in the wrong shape are dropped. [Hooks Reference](Extending-Hooks-Reference) lists every one.

The engine's own kanban hooks apply on the board too, since every read and write goes through the engine: `ap.ecommerce.kanban.cardQuery` (narrow the cards), `ap.ecommerce.kanban.cardMoving` (veto or redirect a move), `ap.ecommerce.kanban.widgetRendered` (change a widget payload), and the rest in the engine's kanban documentation.

## Hooks only see what the viewer may see

The board has been authorized before any hook runs. Cards and columns passed to a hook are the ones the board shows, and an action or toolbar button that names an `ability` is dropped for viewers who don't hold it. A hook that needs more than that should check abilities itself, with `ArtisanPackUI\EcommerceKanbanLivewire\Support\Authorization::allows( $user, 'order.update', $order )` or the engine's `EcommerceAuthorizer`.

## Publishing views

When a hook isn't enough, publish the views:

```bash
php artisan vendor:publish --tag=ecommerce-kanban-views
```

and edit the copies in `resources/views/vendor/ecommerce-kanban`. Published views shadow the package's and don't receive its fixes, so delete every file you didn't change. The Blade components (`x-artisanpack-ec-kb-card`, `-column`, `-wip`, `-widget`, `-connection`, `-empty`) are views under `components/`, so they can be overridden the same way.

## Rules every extension follows

- **Translate labels per request.** Build labels with `__()` inside the filter callback, so they follow the request's locale.
- **Links must be safe.** Action and widget links are kept only when they are `http`, `https`, `mailto`, or relative. Anything else (`javascript:`, `data:`) is dropped.
- **Icons must exist.** An icon name that doesn't resolve to an SVG is dropped rather than breaking the card. Use the component library's names, such as `o-printer`.
- **Handle your events.** An action's `event` is a Livewire event dispatched on the page. Put a Livewire component on the page that listens for it, and re-check abilities there: the menu only hides what a viewer may not use, it doesn't authorize the action.
