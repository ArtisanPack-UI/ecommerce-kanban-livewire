---
title: Filter Controls
---

# Filter Controls

A satellite adds a control to the filter bar in two parts: the control, from `ap.ecommerceKanbanLivewire.filters`, and what it does to the cards, from the engine's `ap.ecommerce.kanban.cardQuery`.

## 1. Add the control

```php
use ArtisanPackUI\Ecommerce\Models\KanbanBoard;

addFilter( 'ap.ecommerceKanbanLivewire.filters', function ( array $controls, KanbanBoard $board ): array {
    $controls[] = [
        'key'   => 'gift',
        'label' => __( 'Gift orders' ),
        'type'  => 'toggle',
    ];

    $controls[] = [
        'key'     => 'carrier',
        'label'   => __( 'Carrier' ),
        'type'    => 'select',
        'options' => [
            [ 'id' => 'ups', 'name' => __( 'UPS' ) ],
            [ 'id' => 'usps', 'name' => __( 'USPS' ) ],
        ],
    ];

    return $controls;
} );
```

| Key | Rules |
| --- | --- |
| `key` | Lowercase letters, digits, and underscores, starting with a letter, up to 40 characters. It can't be one of the engine's own filter keys (`search`, `customer_id`, `tags`, `tag_match`, `product_ids`, `placed_from`, `placed_to`, `moved_before`, `stale`, `payment_status`, `fulfillment_status`). The first control with a key wins. |
| `label` | Required. Translate it with `__()`. |
| `type` | `toggle`, `select`, or `text` (the default) |
| `options` | For `select`: a list of `[ 'id' => …, 'name' => … ]`. A `select` without options is dropped. |

A control that breaks a rule is dropped. Controls appear after the built-in ones, in the inline bar and the phone drawer alike.

## 2. Narrow the cards

The control's value reaches the engine in `KanbanCardFilters::$extra`, keyed by the control's key, as a string (a toggle that is on is `'1'`). Read it in an `ap.ecommerce.kanban.cardQuery` filter, which receives the card (board assignment) query builder, the filters, and the board:

```php
use ArtisanPackUI\Ecommerce\Kanban\KanbanCardFilters;
use ArtisanPackUI\Ecommerce\Models\KanbanBoard;
use Illuminate\Database\Eloquent\Builder;

addFilter( 'ap.ecommerce.kanban.cardQuery', function ( Builder $query, KanbanCardFilters $filters, KanbanBoard $board ): Builder {
    if ( ! empty( $filters->extra['gift'] ?? null ) ) {
        $query->whereHas( 'order', fn ( Builder $orders ) => $orders->where( 'meta->gift', true ) );
    }

    return $query;
} );
```

Because the board reads every card through the engine's card query, the filter applies to column counts, "Show more" paging, polls, and live card refreshes alike.

## In the URL

Satellite values live in the query string under `f`, for example `?f[gift]=1&f[carrier]=ups`, so a filtered board stays a shareable link. Values for keys no control declares are ignored. See [Filters](Usage-Filters).
