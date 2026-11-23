---
title: Card Widgets
menu_order: 30
---

# Card Widgets

Widgets are the small details on a card. The engine owns them: a widget is a class registered with the engine, which returns a framework-agnostic payload. The board renders each payload as Blade, and a satellite can supply its own view.

## Registering a widget with the engine

Implement the engine's `ArtisanPackUI\Ecommerce\Contracts\KanbanCardWidget` (`key()`, `label()`, `render( Order $order, KanbanColumn $column ): array`, `refreshSubscription( Order $order ): ?string`) and register it:

```php
use ArtisanPackUI\Ecommerce\Registries\KanbanCardWidgetRegistry;

app( KanbanCardWidgetRegistry::class )->register( 'print-status', PrintStatusWidget::class );
```

A widget that takes settings also implements `ConfigurableKanbanCardWidget` and declares a `configSchema()`. The board's widget picker then shows a form for its `text`, `textarea`, `number`, `boolean`, and `select` fields. See [Editing the Board](../usage/editing-the-board.md#card-widgets).

Registered widgets appear in the widget picker with their label and provider. The engine's kanban documentation covers the contract, the payload, and its contract test.

## The payload

```json
{ "key": "print-status", "label": "Label", "value": "Printed", "tone": "success",
  "icon": "printer", "tooltip": "Printed at 10:42", "href": null, "refresh_channel": null }
```

## The generic view

Without a view of its own, a widget renders with `ecommerce-kanban::components.widgets.generic`: a small, soft design-system Badge (`x-artisanpack-badge`) with the payload's `value`.

| Payload key | Rendered as |
| --- | --- |
| `value` | The badge text. An empty value renders nothing. |
| `label` | Screen-reader text before the value ("Total: $124.00") |
| `tone` | The badge color, plus an icon and screen-reader text so the tone is never color alone: `success` (check, "Good"), `warning` (triangle, "Needs attention"), `danger` (x in a circle, "Problem"), `info` (info icon, "Note"), `neutral` (neutral color, no icon). Tag widgets (`WidgetViews::TAG_KEYS`) read in the info color with no tone icon. See [PHP API](../reference/php-api.md). |
| `icon` | An icon before the value. Names in livewire-ui-components 3.0's icon registry (its Lucide set, such as `printer`) are used as they are, and win when a name is in both sets. Other bare names are taken as Heroicons and get the `o-` prefix (`truck` → `o-truck`). Names that don't resolve are dropped. |
| `tooltip` | A tooltip, also read by screen readers |
| `href` | Makes the badge a link. Only `http`, `https`, `mailto`, and relative URLs are kept. |

## Your own view

Return a view name for your widget key from `ap.ecommerceKanbanLivewire.widgetView`:

```php
addFilter( 'ap.ecommerceKanbanLivewire.widgetView', function ( ?string $view, string $key, array $payload ): ?string {
    return 'print-status' === $key ? 'print-provider::kanban.status-widget' : $view;
} );
```

The view receives `$widget`, the payload array:

```blade
{{-- print-provider::kanban.status-widget --}}
<x-artisanpack-badge variant="soft" size="sm" color="neutral" leading-icon="o-printer">
    <span class="apui-sr-only">{{ $widget['label'] }}:</span>
    {{ $widget['value'] }}
</x-artisanpack-badge>
```

Build widget views from livewire-ui-components 3.0 components such as `x-artisanpack-badge` (`variant`: `solid`, `soft`, `outline`, or `ghost`; `color`: one of the design system's semantic colors; `size`: `sm`, `md`, or `lg`) and the design system's classes (`apui-sr-only` for screen-reader text), so the view follows the light and dark themes without a build step. daisyUI classes such as `badge` and `badge-sm` no longer exist in 2.0.

- Return `$view` unchanged for keys that aren't yours.
- A view that doesn't exist falls back to the generic view.
- A view that throws while rendering is reported and renders nothing, so one broken widget never breaks a card.
- Don't rely on the host's Tailwind build: Tailwind is optional for the board. Use components and design-system classes, or ship and inline your own styles.
- Keep the tone readable without color, as the generic view does.

## Styling whole cards

To style the card rather than a widget, use `ap.ecommerceKanbanLivewire.cardClasses`. See [Hooks](../reference/hooks.md#cards).
