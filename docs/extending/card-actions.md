---
title: Card and Toolbar Actions
---

# Card and Toolbar Actions

Add items to every card's actions menu with `ap.ecommerceKanbanLivewire.cardActions`, and buttons to the board header with `ap.ecommerceKanbanLivewire.boardToolbar`. Both use the same action shape.

## The action shape

| Key | Required | Meaning |
| --- | --- | --- |
| `key` | Yes | A unique key. The menu item carries it as `data-ec-kb-action="{key}"` (`data-ec-kb-toolbar-action` in the header). |
| `label` | Yes | The visible text. Translate it with `__()`. |
| `icon` | No | A component library icon name, such as `o-printer`. An unknown icon is dropped. |
| `ability` | No | `{resource}.{action}`, such as `kanbanBoard.update`. The action is left out for viewers who don't hold it with the board as the subject. For an order-level ability such as `order.update`, check it in your filter callback with the order instead (see the example below). |
| `url` | One of `url` and `event` | A link. Only `http`, `https`, `mailto`, and relative URLs are kept. |
| `event` | One of `url` and `event` | A Livewire event dispatched on the page when the action is clicked |

An action with neither a `url` nor an `event`, or without a `key` or `label`, is dropped. With both a `url` and an `event`, a plain click dispatches the event and a modified click (Ctrl, Cmd, Shift, or Alt) follows the link, so "open in a new tab" still works.

Abilities are checked once per board render, not per card. The action itself must check again when it runs.

## Card actions

The menu starts with three core actions:

| Key | Label | Ability | Does |
| --- | --- | --- | --- |
| `open` | Open | — | Opens the card slide-out (`ec-kb-open-card`); a modified click opens `?card=` in a new tab |
| `move` | Move to… | `kanbanCard.move` | Opens "Move to…" (`ec-kb-move-card`) |
| `remove` | Remove from board | `kanbanBoard.update` | Asks before removing the card (`ec-kb-remove-card`) |

Add, reorder, or remove entries from the filter:

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

A card event is dispatched with `{ orderId, columnId, boardId }`. Handle it in a Livewire component on the page, and authorize there:

```php
use ArtisanPackUI\Ecommerce\Models\Order;
use ArtisanPackUI\EcommerceKanbanLivewire\Support\Authorization;
use Livewire\Attributes\On;
use Livewire\Component;

class ReprintLabel extends Component
{
    #[On( 'print-labels.reprint' )]
    public function reprint( int $orderId, int $columnId, int $boardId ): void
    {
        $order = Order::query()->findOrFail( $orderId );

        abort_unless( Authorization::allows( auth()->user(), 'order.update', $order ), 403 );

        // Queue the reprint.
    }

    public function render(): string
    {
        return '<div></div>';
    }
}
```

Render that component on the board pages, for example from your layout or by [embedding](Extending-Embedding-Components) the board in your own page.

## Toolbar actions

`ap.ecommerceKanbanLivewire.boardToolbar` starts empty. Its buttons appear in the board header, next to **Automations**:

```php
use ArtisanPackUI\Ecommerce\Models\KanbanBoard;

addFilter( 'ap.ecommerceKanbanLivewire.boardToolbar', function ( array $actions, KanbanBoard $board ): array {
    $actions[] = [
        'key'     => 'pick-list',
        'label'   => __( 'Pick list' ),
        'icon'    => 'o-printer',
        'ability' => 'order.viewAny',
        'url'     => route( 'pick-lists.show', [ 'board' => $board->id ] ),
    ];

    $actions[] = [
        'key'     => 'rush',
        'label'   => __( 'Rush mode' ),
        'icon'    => 'o-bolt',
        'ability' => 'kanbanBoard.update',
        'event'   => 'rush.toggle',
    ];

    return $actions;
} );
```

A toolbar event is dispatched with `{ boardId }`. The filter also receives the viewer (`?Authenticatable $user`) as its third argument.
