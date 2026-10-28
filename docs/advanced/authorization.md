---
title: Authorization
---

# Authorization

The board asks the engine's `EcommerceAuthorizer` for every check, with `{resource}.{action}` abilities, so the board, the ecommerce admin, the REST API, and GraphQL always agree.

## Granting access

The engine denies everything by default. Define the umbrella Gate ability, individual abilities, or both, in a service provider's `boot()`:

```php
use Illuminate\Support\Facades\Gate;

// Everyone who may use the store admin may use the board.
Gate::define( 'ecommerce.admin', fn ( $user ): bool => $user->is_admin );

// Packers see and move cards, but don't edit boards.
Gate::define( 'ecommerce.kanbanBoard.viewAny', fn ( $user ): bool => $user->hasRole( 'packer' ) );
Gate::define( 'ecommerce.kanbanBoard.view', fn ( $user ): bool => $user->hasRole( 'packer' ) );
Gate::define( 'ecommerce.kanbanCard.move', fn ( $user ): bool => $user->hasRole( 'packer' ) );
```

A Gate for a specific ability decides that ability; otherwise `ecommerce.admin` decides. Satellites can also grant or revoke through the engine's `ap.ecommerce.abilities.{resource}.{action}` filter. See the engine's permissions documentation.

## Abilities the board uses

| Ability | Needed to |
| --- | --- |
| `kanbanBoard.viewAny` | Open the board picker, and see the "Kanban" admin menu entry |
| `kanbanBoard.view` (on the board) | Open a board and read its automations. Also authorizes the board's broadcast channel. |
| `kanbanCard.move` (on the board) | Drag cards, move them by keyboard, and use "Move to…" |
| `kanbanBoard.update` (on the board) | Add and remove orders, add the order to another board from the slide-out, "Edit board" (columns and card widgets), and switch automations on and off or delete them |
| `order.view` | Open a card's slide-out, and find orders in "Add order" |

Removing a column that still has cards also needs `kanbanCard.move`, since its cards move first.

## Where they are checked

| Screen or action | Check |
| --- | --- |
| Picker page (`boards.index`) | The controller answers 403 without `kanbanBoard.viewAny`, and the picker component checks again on mount and every update |
| Board page (`boards.show`) | The controller answers 403 without `kanbanBoard.view` on the board, and the board component checks again on mount and every update |
| Board list in the picker and switcher | Only active boards the user may view |
| Card actions and toolbar buttons | Actions naming an `ability` are left out for users without it (checked once per render, against the board) |
| Every move, edit, assignment, and automation change | The action checks its ability on the server before it runs, whatever the page showed |
| Card slide-out | `order.view`, and the order must be on the board |
| "Add order" search | Only orders the user may view, that aren't on the board yet |
| Customer and product filter options | Only customers and products with orders on this board |
| Broadcast channel `private-ecommerce.kanban.board.{board}` | `kanbanBoard.view` on the board (registered by the engine) |

A guest is sent to sign in by the `auth` middleware in `board.middleware`.

## Extensions

Hooks run only after the board has been authorized. An extension's action that changes data must check its own ability when it runs: hiding a menu item from a user doesn't stop them dispatching its event. Use `ArtisanPackUI\EcommerceKanbanLivewire\Support\Authorization::allows( $user, 'order.update', $order )`, which takes `{resource}.{action}` (an `ecommerce.` prefix is allowed) and denies a malformed ability. See [Card and Toolbar Actions](Extending-Card-Actions).
