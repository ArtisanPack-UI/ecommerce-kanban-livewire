---
title: PHP API
menu_order: 30
---

# PHP API

The public PHP classes, methods, and constants the board ships. Everything lives under the `ArtisanPackUI\EcommerceKanbanLivewire` namespace; the tables below give the class name relative to it (for example `Support\Hooks` is `ArtisanPackUI\EcommerceKanbanLivewire\Support\Hooks`).

Most hosts only need the [hooks](hooks.md), the [configuration](../installation/configuration.md), and the [components](../extending/embedding-components.md#components). The classes here are for satellites and hosts that build their own extensions: reading a widget tone, building a board link, or mapping options for a filter control.

The [internal classes](#internal-classes) at the end are public in PHP only so the package's components can share them. They can change in any minor release.

## Changes in 2.0

- `Support\WidgetViews::TONES` entries no longer have a `class` key (the daisyUI `badge-*` class). Each entry is `{ color, icon }`, where `color` is a design-system Badge color.
- `Support\WidgetViews::tone()` returns `{ color, icon, text }` and takes an optional widget key as its second argument. A neutral tag (see `TAG_KEYS`) reads in the `info` color.
- The `danger` tone's icon is `o-x-circle` (it was `o-exclamation-circle`).
- `Queries\BoardsQuery::visibleTo()` takes `$withCounts`, `Support\ActionList::normalize()` keeps one action per key and takes `$preferred`, and `Support\CardDetailPresenter::timeline()` takes the viewer.
- New: `Queries\VisibleCards`, `Livewire\Board::MAX_CARDS_PER_COLUMN`, `ThrottleKanbanMutations::READ_EVENTS`, `Support\ControlOptions`, `Support\KanbanStyles`, `Support\Statuses::tone()`, `Support\CardDetailPresenter::MARKERS` and `marker()`, `Support\WidgetViews::TAG_KEYS`, and the `InstallCommand::DESIGN_SYSTEM_*` constants.

See [Upgrading](../upgrading.md) for what to change in your extensions.

## Service provider

`EcommerceKanbanLivewireServiceProvider`

| Member | Value or signature | Description |
| --- | --- | --- |
| `VERSION` | `string` | The package version, reported to the engine's satellite registry. |
| `PACKAGE_NAME` | `'artisanpack-ui/ecommerce-kanban-livewire'` | The Composer package name. |
| `VIEW_NAMESPACE` | `'ecommerce-kanban'` | The view and translation namespace (`ecommerce-kanban::…`). |
| `LIVEWIRE_COMPONENTS` | `array<string, class-string>` | Livewire alias → class. See [Livewire components](#livewire-components). |
| `BLADE_COMPONENTS` | `array<string, string>` | Short name → view. Each is registered as `x-artisanpack-ec-kb-{name}`: `card`, `column`, `connection`, `empty`, `widget`, `wip`. |
| `MENU_KEY` | `'kanban'` | The admin menu entry key. |
| `satelliteDescriptor()` | `static (): array` | The descriptor the package registers with the engine's `SatelliteRegistry`. The board owns no tables, migrations, meta namespaces, or product types. |
| `hostPersistentMiddleware()` | `static (): array<int, class-string>` | The middleware classes behind the `board.middleware` config key, with aliases resolved and groups (such as `web`) left out. |
| `registerPersistentMiddleware()` | `(): void` | Registers those classes as Livewire persistent middleware. Called by the provider. |

## Hooks

`Support\Hooks` holds the name of every hook the board applies, as constants. The hooks, their arguments, and examples are in [Hooks](hooks.md).

| Constant | Hook |
| --- | --- |
| `WIDGET_VIEW` | `ap.ecommerceKanbanLivewire.widgetView` |
| `CARD_ACTIONS` | `ap.ecommerceKanbanLivewire.cardActions` |
| `FILTERS` | `ap.ecommerceKanbanLivewire.filters` |
| `BOARD_TOOLBAR` | `ap.ecommerceKanbanLivewire.boardToolbar` |
| `CARD_CLASSES` | `ap.ecommerceKanbanLivewire.cardClasses` |
| `BOARD_URL` | `ap.ecommerceKanbanLivewire.boardUrl` |
| `PICKER_URL` | `ap.ecommerceKanbanLivewire.pickerUrl` |
| `VITE_ENTRIES` | `ap.ecommerceKanbanLivewire.layout.viteEntries` |
| `BOARD_RENDERED` | `ap.ecommerceKanbanLivewire.boardRendered` |
| `CARD_MOVE_FAILED` | `ap.ecommerceKanbanLivewire.cardMoveFailed` |

The same names are also available on the class that applies each filter: `WidgetViews::FILTER`, `CardActions::FILTER`, `FilterControls::FILTER`, `BoardToolbar::FILTER`, and `CardPresenter::CLASSES_FILTER`.

## Widgets and tones

### `Support\WidgetViews`

Renders the engine's widget payloads (`key`, `label`, `value`, `tone`, `icon`, `tooltip`, `href`, `refresh_channel`) as Blade. See [Card Widgets](../extending/card-widgets.md).

| Member | Value or signature | Description |
| --- | --- | --- |
| `FILTER` | `'ap.ecommerceKanbanLivewire.widgetView'` | The view-override filter. |
| `GENERIC` | `'ecommerce-kanban::components.widgets.generic'` | The view used for keys without their own view. |
| `TONES` | `array<string, array{color: string, icon: string\|null}>` | The Badge color and tone icon for each tone (below). |
| `TAG_KEYS` | `[ 'tags' ]` | Widget keys whose values are tags: a neutral tag reads in the `info` color, with no tone icon or text. Since 2.0.0. |
| `view()` | `static ( array $payload ): string` | The view for a payload: the filter's view when it exists, else `GENERIC`. |
| `render()` | `static ( array $payload ): HtmlString` | Renders a payload with `$widget` set to it. A view that throws is reported and renders nothing. |
| `tone()` | `static ( ?string $tone, ?string $key = null ): array{color: string, icon: string\|null, text: string\|null}` | The Badge color, tone icon, and the translated text that names the tone for screen readers. Unknown tones read as `neutral`. `$key` added in 2.0.0. |

| Tone | `color` | `icon` | `text` |
| --- | --- | --- | --- |
| `neutral` | `neutral` | none | none |
| `success` | `success` | `o-check-circle` | "Good" |
| `warning` | `warning` | `o-exclamation-triangle` | "Needs attention" |
| `danger` | `error` | `o-x-circle` | "Problem" |
| `info` | `info` | `o-information-circle` | "Note" |

```php
use ArtisanPackUI\EcommerceKanbanLivewire\Support\WidgetViews;

$tone = WidgetViews::tone( $widget['tone'] ?? null, $widget['key'] ?? null );
// [ 'color' => 'warning', 'icon' => 'o-exclamation-triangle', 'text' => 'Needs attention' ]
```

### `Support\Statuses`

Labels and tones for the engine's order statuses. `$type` is `system`, `payment`, or `fulfillment`.

| Member | Value or signature | Description |
| --- | --- | --- |
| `EXIT` | `[ 'cancelled', 'refunded', 'failed' ]` | System statuses that move the whole order, so a move into a column holding one is confirmed first. |
| `labels()` | `static ( string $type ): array<string, string>` | The known values of `$type` with their translated labels. An unknown type returns `[]`. |
| `label()` | `static ( string $type, ?string $value ): string` | The label for one value. An unknown value is shown as a headline (`on_hold` → "On Hold"). |
| `tone()` | `static ( string $type, ?string $value ): string` | The widget tone a payment or fulfillment status reads in: `paid` and `fulfilled` are `success`, payment `pending` is `warning`, `partially_refunded` and fulfillment `partial` are `info`, payment `failed` is `danger`, and everything else is `neutral`. Since 2.0.0. |
| `options()` | `static ( string $type ): array<int, array{id: string, name: string}>` | `$type`'s statuses as options. |

### `Support\CardDetailPresenter`

The card slide-out's data.

| Member | Value or signature | Description |
| --- | --- | --- |
| `TIMELINE_ENTRIES` | `5` | Timeline entries shown in "Recent activity". |
| `MARKERS` | `array<string, array{icon: string, color: string}>` | The Timeline icon and color for each known event type, such as `order.placed` (`o-shopping-bag`, `primary`) or `kanban.automation_failed` (`o-bolt`, `error`). Since 2.0.0. |
| `marker()` | `static ( string $type ): array{icon: string\|null, color: string}` | The marker for an event type; unknown types get no icon and `neutral`. Since 2.0.0. |
| `present()` | `static ( Order $order, KanbanBoard $board, ?Authenticatable $user = null ): array` | The slide-out's view data for `$order` seen from `$board`. "Other boards" lists only boards `$user` may view. Expects the order's `items` loaded. |
| `timeline()` | `static ( Order $order, ?Authenticatable $user = null ): array` | The latest timeline entries: `id`, `text`, `date`, `date_iso`, `icon`, `color`. A board `$user` may view is named by its name; a board they may not view, or one since deleted, is never named ("Added to another board", "Removed from another board"), and a move on it shows the sub-status label instead of that board's column label. `$user` added in 2.0.0. |
| `address()` | `static ( array $address ): array<int, string>` | A stored address as display lines. |
| `describe()` | `static ( string $type, array $payload ): string` | A timeline entry in words. Unknown types are shown as a headline. |

### `Support\CardPresenter`

Turns a card (an order's assignment to a board) into what `x-artisanpack-ec-kb-card` renders.

| Member | Value or signature | Description |
| --- | --- | --- |
| `CLASSES_FILTER` | `'ap.ecommerceKanbanLivewire.cardClasses'` | The card classes filter. |
| `__construct()` | `( KanbanCardRenderer $renderer )` | Takes the engine's widget renderer. Resolve it from the container. |
| `present()` | `( OrderBoardAssignment $card, KanbanColumn $column ): array` | `order_id`, `column_id`, `number`, `customer`, `placed_at`, `placed_at_iso`, `widgets`, `label` (the card's accessible name), and `classes` (from the filter). The card's `order` must be loaded. |
| `customerName()` | `static ( Order $order ): string` | The customer's name from the billing (else shipping) address, else the order email. |

## Actions and filter controls

### `Support\CardActions`

Builds a card's actions menu. See [Card and Toolbar Actions](../extending/card-actions.md).

| Member | Value or signature | Description |
| --- | --- | --- |
| `FILTER` | `'ap.ecommerceKanbanLivewire.cardActions'` | The extension filter. |
| `OPEN_EVENT` | `'ec-kb-open-card'` | Dispatched by **Open**; the card slide-out handles it. |
| `MOVE_EVENT` | `'ec-kb-move-card'` | Dispatched by **Move to…**; the board's move dialog handles it. |
| `REMOVE_EVENT` | `'ec-kb-remove-card'` | Dispatched by **Remove from board**; the board handles it. |
| `__construct()` | `( KanbanBoard $board, ?Authenticatable $user )` | The board and the viewer. |
| `for()` | `( Order $order, KanbanColumn $column ): array` | The actions the viewer may take on a card: the core `open`, `move`, and `remove`, then the filter's additions. Each is `{ key, label, icon, url, event }`. |

### `Support\BoardToolbar`

| Member | Value or signature | Description |
| --- | --- | --- |
| `FILTER` | `'ap.ecommerceKanbanLivewire.boardToolbar'` | The extension filter. |
| `__construct()` | `( KanbanBoard $board, ?Authenticatable $user )` | The board and the viewer. |
| `actions()` | `(): array` | The header actions the viewer may take, in the same shape as card actions. |

### `Support\ActionList`

| Member | Signature | Description |
| --- | --- | --- |
| `normalize()` | `static ( mixed $actions, callable $allows, array $preferred = [] ): array` | The well-formed actions from a filtered list, in order, one per key. An action needs a `key`, a `label`, and a `url` or an `event`; an action that names an `ability` the `$allows( string $ability ): bool` callback denies is dropped; URLs pass through `SafeUrl`, and icons through `Icons`. For a duplicate key the first action wins, or, for a key in `$preferred` (event keyed by action key), the first whose event is the one named there, so a satellite action can't replace a built-in `open`, `move`, or `remove`. The kept action takes the position of the first action with its key. One-per-key and `$preferred` added in 2.0.0. |

### `Support\FilterControls`

Satellite controls for the filter bar. See [Filter Controls](../extending/filter-controls.md).

| Member | Value or signature | Description |
| --- | --- | --- |
| `FILTER` | `'ap.ecommerceKanbanLivewire.filters'` | The extension filter. |
| `TYPES` | `[ 'toggle', 'select', 'text' ]` | The allowed control types. |
| `for()` | `static ( KanbanBoard $board ): array` | The valid controls for a board, each `{ key, label, type, options }`. A control is dropped when its key isn't lowercase `[a-z][a-z0-9_]` (up to 40 characters), clashes with a built-in filter key or an earlier control, has no label, has an unknown type, or is a `select` with no valid `{ id, name }` options. |

### `Support\ControlOptions`

Maps option lists to the shape livewire-ui-components 3.0 reads. Since 2.0.0.

| Member | Signature | Description |
| --- | --- | --- |
| `from()` | `static ( ?iterable $options ): array` | Each option with `id` → `value`, `name` → `label`, and `hint` → `description`. Keys already in the 3.0 shape win, other keys (`disabled`, `icon`, `color`, `meta`) are kept, plain strings pass through, objects with `toArray()` are converted, and a Select group (`[ 'label' => …, 'options' => [ … ] ]`) is mapped recursively. |

```php
use ArtisanPackUI\EcommerceKanbanLivewire\Support\ControlOptions;

ControlOptions::from( [ [ 'id' => 'rush', 'name' => 'Rush', 'hint' => 'Ships today' ] ] );
// [ [ 'value' => 'rush', 'label' => 'Rush', 'description' => 'Ships today' ] ]
```

## Links, icons, and styles

### `Support\BoardUrls`

| Member | Value or signature | Description |
| --- | --- | --- |
| `INDEX_ROUTE` | `'artisanpack.ecommerce.kanban.boards.index'` | The board picker route. |
| `SHOW_ROUTE` | `'artisanpack.ecommerce.kanban.boards.show'` | The board route. |
| `show()` | `static ( KanbanBoard $board, array $query = [] ): ?string` | A board's URL with query-string parameters (for example `[ 'card' => 1042 ]`), passed through the `boardUrl` filter. Null when there is none. |
| `picker()` | `static (): ?string` | The board picker's URL (always the list, never a redirect), passed through the `pickerUrl` filter. |

### `Support\AdminLinks`

Links into the ecommerce admin. Every method returns null when the admin isn't installed.

| Member | Value or signature | Description |
| --- | --- | --- |
| `BOARDS_ROUTE` | `'artisanpack.ecommerce.admin.kanban-boards.index'` | The admin's board list. |
| `ORDER_ROUTE` | `'artisanpack.ecommerce.admin.orders.show'` | The admin's order screen. |
| `BOARD_EDIT_ROUTE` | `'artisanpack.ecommerce.admin.kanban-boards.edit'` | The admin's board edit screen. |
| `AUTOMATIONS_ANCHOR` | `'board-automations-heading'` | The anchor of the automations section on the board edit screen. |
| `createBoardUrl()` | `static (): ?string` | Where to create a board. |
| `orderUrl()` | `static ( int $orderId ): ?string` | An order's admin screen. |
| `boardEditUrl()` | `static ( int $boardId ): ?string` | A board's edit screen (the "Advanced settings" link). |
| `automationUrl()` | `static ( int $boardId, ?int $automationId = null ): ?string` | The board's automations section, set to edit `$automationId` or to add a new automation. |

### `Support\SafeUrl`

| Member | Value or signature | Description |
| --- | --- | --- |
| `SCHEMES` | `[ 'http', 'https', 'mailto' ]` | The schemes a link may use. |
| `of()` | `static ( mixed $url ): ?string` | `$url` when it is relative or uses an allowed scheme, else null. The board runs widget and action links through it, so a payload can't add a `javascript:` or `data:` link. |

### `Support\Icons`

| Member | Signature | Description |
| --- | --- | --- |
| `name()` | `static ( ?string $name ): ?string` | The `x-artisanpack-icon` name for an icon name the engine stores (sub-status, column, widget, and action icons). A name in livewire-ui-components 3.0's icon registry (its Lucide set, such as `settings` or `external-link`) is kept as it is, and wins when a name is in both sets (since 2.0.0). Otherwise a bare name is taken as a Heroicon and gets the `o-` style prefix (`truck` → `o-truck`). Names that don't resolve to an icon return null, because rendering them would throw. |
| `flush()` | `static (): void` | Forgets the resolved names. For tests. |

### `Support\KanbanStyles`

The board's own stylesheet (`resources/css/kanban.css`), which `ecommerce-kanban::partials.styles` inlines (the board loads it once per page through Livewire `@assets`), and the text direction the board and the standalone layout use. Since 2.0.0.

| Member | Signature | Description |
| --- | --- | --- |
| `RTL_LANGUAGES` | `[ 'ar', 'ckb', 'dv', 'fa', 'he', 'ps', 'sd', 'ug', 'ur', 'yi' ]` | The base language codes written right to left. |
| `textDirection()` | `static ( ?string $locale = null ): string` | `rtl` when the locale's base language (before `_` or `-`) is in `RTL_LANGUAGES`, `ltr` otherwise; the app's current locale when null. The standalone layout sets it on `<html>`, and the board sets `dir="rtl"` on its own root. |
| `path()` | `static (): string` | The stylesheet's path. |
| `css()` | `static (): string` | The stylesheet's contents, safe to print inside a `<style>` element. |

### `Support\LayoutResolver`

Picks the layout the board pages extend: the `layout` config key, then cms-framework's admin layout, then the ecommerce admin's layout, then the package's standalone layout. See [Layouts](../installation/layouts.md).

| Member | Value or signature | Description |
| --- | --- | --- |
| `CMS_PROBE` | `string` | The cms-framework class whose presence means cms-framework is installed. |
| `CMS_LAYOUT` | `'cms::admin.layouts.app'` | cms-framework's admin layout. |
| `ADMIN_PACKAGE` | `'artisanpack-ui/ecommerce-admin-livewire'` | The ecommerce admin's Composer package. |
| `ADMIN_LAYOUT` | `'ecommerce-admin::layouts.app'` | The ecommerce admin's layout. |
| `STANDALONE_LAYOUT` | `'ecommerce-kanban::layouts.app'` | The package's standalone layout. |
| `layout()` | `static (): string` | The layout view name the board pages extend. |
| `pushesAssets()` | `static ( string $layout ): bool` | Whether a page has to push the host's assets onto the layout's stacks (true under cms-framework's layout). |
| `cmsInstalled()` | `static (): bool` | Whether cms-framework is installed. |
| `adminInstalled()` | `static (): bool` | Whether the ecommerce admin is installed and active. |
| `fakeCms()` | `static ( ?bool $installed ): void` | Overrides the cms-framework check in tests; null restores it. |

## Authorization and boards

### `Support\Authorization`

Checks the engine's `{resource}.{action}` abilities. See [Authorization](../advanced/authorization.md).

| Member | Signature | Description |
| --- | --- | --- |
| `allows()` | `static ( ?Authenticatable $user, string $ability, mixed $subject = null ): bool` | Whether `$user` holds `$ability` (for example `kanbanCard.move`) through the engine's authorizer. A malformed ability is denied. |
| `canOpenBoard()` | `static ( ?Authenticatable $user, KanbanBoard $board ): bool` | Whether `$user` may open the board: they hold `kanbanBoard.view`, and a switched-off board opens only for people who can edit it. |

### `Support\LastBoard`

| Member | Value or signature | Description |
| --- | --- | --- |
| `SESSION_KEY` | `'artisanpack.ecommerce-kanban.last_board'` | The session key. |
| `remember()` | `static ( ?Authenticatable $user, KanbanBoard $board ): void` | Records the board `$user` last opened. |
| `id()` | `static ( ?Authenticatable $user ): ?int` | The id of the board `$user` last opened, if any. |

### `Support\Automations`

Display helpers for the automations panel and the column badges. Trigger config is never read, because it is encrypted and can hold secrets.

| Member | Signature | Description |
| --- | --- | --- |
| `countsInto()` | `static ( KanbanBoard $board ): array<int, int>` | Active automations that fire into each column, keyed by column id. Columns without any are left out. |
| `triggerLabel()` | `static ( string $key ): string` | A trigger's label, or a note that it is no longer installed. |
| `conditionsSummary()` | `static ( mixed $tree ): string` | A one-line summary of an automation's condition tree. |

### `Support\MoveErrors`

| Member | Signature | Description |
| --- | --- | --- |
| `code()` | `static ( Throwable $exception ): string` | The machine-readable code for a refused move, as the engine's REST layer names it (for example `wip-limit-reached`). These are the codes the `cardMoveFailed` action receives. |
| `message()` | `static ( string $code, string $column ): string` | The translated toast message for a code; `$column` is the target column's label. |

### `Queries\BoardsQuery`

| Member | Signature | Description |
| --- | --- | --- |
| `visibleTo()` | `static ( ?Authenticatable $user, bool $withCounts = true ): Collection` | The active boards `$user` may view, in `position` order. Switched-off boards are left out. With `$withCounts`, each gets a `cards_count` of its visible cards (see [`Queries\VisibleCards`](#queriesvisiblecards)); pass `false` to skip the count queries. `$withCounts` added in 2.0.0. |

### `Queries\VisibleCards`

The cards on a board that the engine's `ap.ecommerce.kanban.cardQuery` filter leaves visible, read through `KanbanCardQuery` with no filter-bar filters applied. This is the board's visibility boundary: moves, "Move to…", removing a card, the slide-out (including `?card=`), the customer, product, and tag filter options, the widget preview, and the board picker's counts all read their cards through it. See [Restricting which cards people see](../usage/filters.md#restricting-which-cards-people-see). Since 2.0.0.

| Member | Signature | Description |
| --- | --- | --- |
| `query()` | `static ( KanbanBoard $board ): Builder` | The board's visible active cards (`OrderBoardAssignment`), without eager loads. |
| `assignment()` | `static ( KanbanBoard $board, int $orderId ): ?OrderBoardAssignment` | The order's visible card on the board, or null when the order isn't on it or the card is hidden. |
| `orderIds()` | `static ( KanbanBoard $board ): Builder` | The order ids of the visible cards, as a subquery (for `whereIn`). |
| `ids()` | `static ( KanbanBoard $board ): Builder` | The ids of the visible cards, as a subquery. |
| `count()` | `static ( KanbanBoard $board ): int` | How many visible cards the board has. |

### `Queries\ColumnCardsQuery`

Reads a board's cards through the engine's `KanbanCardQuery`, so the engine's `ap.ecommerce.kanban.cardQuery` filter applies (see [Filters](../usage/filters.md)).

| Member | Signature | Description |
| --- | --- | --- |
| `counts()` | `static ( KanbanBoard $board, Collection $columns, ?KanbanCardFilters $filters = null ): array` | Card counts per column, keyed by column id: `count` is what the filters leave and `total` is every card (what the WIP limit is checked against). `count` never includes a card the `cardQuery` filter hides, even with no filter set, so "Show more" isn't offered for hidden cards; `total` deliberately does, because the engine checks WIP limits against every card (since 2.0.0). |
| `cards()` | `static ( KanbanColumn $column, int $limit, ?KanbanCardFilters $filters = null ): Collection` | The first `$limit` cards in the column that match `$filters`, in their manual order. |
| `signatures()` | `static ( KanbanBoard $board, Collection $columns ): array<int, string>` | A signature per column that changes when a card joins, leaves, or moves within it, or when one of its orders is updated. Polling compares them to find changed columns. Columns without cards get an empty string. |

## Livewire components

Registered under the aliases in `EcommerceKanbanLivewireServiceProvider::LIVEWIRE_COMPONENTS`. Their props are listed in [Embedding Components](../extending/embedding-components.md#components), and the events they exchange in [Hooks](hooks.md#livewire-events).

| Alias | Class | Mount props |
| --- | --- | --- |
| `artisanpack-ecommerce-kanban-board` | `Livewire\Board` | `board` (`KanbanBoard`, id, or numeric string) |
| `artisanpack-ecommerce-kanban-board-picker` | `Livewire\BoardPicker` | `pick` (bool, default `false`) |
| `artisanpack-ecommerce-kanban-card-detail` | `Livewire\CardDetail` | `boardId` (int) |
| `artisanpack-ecommerce-kanban-widget-picker` | `Livewire\WidgetPicker` | `boardId` (int) |
| `artisanpack-ecommerce-kanban-automations` | `Livewire\AutomationsPanel` | `boardId` (int) |

Every component checks its ability on mount and again on every update. The other public properties and methods are Livewire actions and state for the package's own views. They are not a stable API, and the rate limiter and ability checks treat them as requests from the board's UI.

### `Livewire\Board`

| Member | Value or signature | Description |
| --- | --- | --- |
| `perColumn()` | `static (): int` | Cards a column page holds (the `cards.per_column` config key). |
| `dragMessages()` | `static ( string $kind = 'cards' ): array<string, string>` | The translated drag-and-drop instructions and announcements for `$kind`: `cards`, `columns` (edit mode), or `widgets` (the widget picker), with the library's `{item}`, `{container}`, `{position}`, and `{total}` placeholders. `$kind` added in 2.0.0. |
| `$maxCardRefresh` | `static int`, default `25` | The most cards one live refresh re-renders; more fall back to a whole-board render. |
| `MOVED`, `REJECTED`, `CONFIRM` | `'moved'`, `'rejected'`, `'confirm'` | The results of a drag move: it went through, it was refused (the client reverts it), or it waits for confirmation. |
| `MOVE_AFTER_LIMIT` | `50` | The most cards the "Move to…" dialog offers to place a card after. |
| `MAX_CARDS_PER_COLUMN` | `500` | The most cards a column shows, however often "Show more" is pressed; the button is hidden once a column shows this many. Since 2.0.0. |

The board keeps its filters in the URL:

| Query parameter | Filter |
| --- | --- |
| `q` | Search |
| `customer` | Customer id |
| `tags[]` | Tags |
| `products[]` | Product ids |
| `from`, `to` | Placed date range |
| `stale` | Stale cards only |
| `payment[]` | Payment statuses |
| `fulfillment[]` | Fulfillment statuses |
| `f[{key}]` | Satellite filter controls |

It listens for `ec-kb-move-card`, `ec-kb-remove-card`, and `ec-kb-board-changed`.

### `Livewire\CardDetail`

Opens for `?card={order id}` on the board's URL, and listens for `ec-kb-open-card`.

### `Livewire\WidgetPicker`

| Member | Value | Description |
| --- | --- | --- |
| `FIELD_TYPES` | `[ 'text', 'textarea', 'number', 'boolean', 'select' ]` | The `configSchema()` field types the settings form renders. |

Listens for `ec-kb-edit-widgets` (`columnId`, null for the board default).

### `Livewire\AutomationsPanel`

Listens for `ec-kb-edit-automations` (`columnId`, null for the whole board).

## Install command

`Console\Commands\InstallCommand` runs `php artisan ecommerce-kanban:install` (see [Artisan Commands](commands.md)). Its constants hold the lines it prints.

| Constant | Value | Description |
| --- | --- | --- |
| `TAILWIND_SOURCES` | `array<int, string>` | The optional `@source` lines for hosts that use Tailwind. |
| `DESIGN_SYSTEM_INSTALL` | `'php artisan livewire-ui-components:install --assets=bundler'` | The component library's installer. Since 2.0.0. |
| `DESIGN_SYSTEM_PACKAGES` | `'@artisanpack-ui/css@^1 @artisanpack-ui/behavior@^1'` | The design system's npm packages. Since 2.0.0. |
| `DESIGN_SYSTEM_STYLES` | `array<int, string>` | The stylesheet lines: the `@layer` order and `@import '@artisanpack-ui/css';`. Since 2.0.0. |
| `DESIGN_SYSTEM_SCRIPT` | `array<int, string>` | The lines that register the behavior engine as an Alpine plugin. Since 2.0.0. |
| `DRAG_AND_DROP_PACKAGE` | `'@artisanpack-ui/livewire-drag-and-drop@^2.1.0'` | The drag-and-drop npm package. |
| `DRAG_AND_DROP_IMPORT` | `"import '@artisanpack-ui/livewire-drag-and-drop';"` | The line that loads drag-and-drop. |
| `MIN_ENGINE_VERSION` | `'1.2.0'` | The lowest engine version the board runs on. |
| `ENGINE_PACKAGE` | `'artisanpack-ui/ecommerce'` | The engine's Composer package. |
| `REST_BOARDS_ROUTE` | `'ecommerce.api.kanban.boards.store'` | The engine's REST route for creating a board. |
| `INERT_BROADCASTERS` | `[ 'null', 'log' ]` | Broadcasters that don't deliver to browsers, so real-time sync falls back to polling. |

## Rate limiting

`Http\Middleware\ThrottleKanbanMutations` limits the board's Livewire updates. See [Rate limits](../advanced.md#rate-limits).

| Constant | Value | Description |
| --- | --- | --- |
| `ALIAS` | `'ecommerce-kanban.throttle'` | The route middleware alias. Add it to every route that embeds the board. |
| `LIMITER` | `'ecommerce.admin.mutate'` | The engine's rate limiter it counts against. |
| `READ_CALLS` | `array<int, string>` | The component methods that only read. |
| `READ_EVENTS` | `array<string, string>` | Event name → the read-only listener method it maps to, for Livewire `__dispatch` calls: `ec-kb-open-card` (`show`), `ec-kb-move-card` (`openMoveDialog`), `ec-kb-remove-card` (`confirmRemoveCard`), `ec-kb-edit-widgets` and `ec-kb-edit-automations` (`openFor`), `ec-kb-card-updated` (`cardUpdated`), `ec-kb-board-refreshed` (`boardRefreshed`), and `ec-kb-board-changed` (`boardChanged`). Since 2.0.0. |
| `READ_PROPERTIES` | `array<int, string>` | The property updates that only change what the page shows. |

A request made only of these isn't counted against `ecommerce.admin.mutate`. A dispatched event that isn't listed, such as a satellite's own event, still counts.

## Internal classes

These are public only so the package's components can share them. Don't call them from a host.

- `Support\ActionTokens`: mints and consumes the one-time tokens that guard removing a card or a column (`TTL_SECONDS`, `ENDPOINT_PREFIX`, `mint()`, `isValid()`, `consume()`, `release()`).
- `Http\Controllers\BoardScreenController`: serves the board routes.
- The traits in `Livewire\Concerns` (`AuthorizesKanban`, `EditsColumns`, `FiltersCards`, `ManagesAssignments`, `MovesCards`, `RendersColumns`, `ShowsAutomationFeedback`, `SyncsBoard`, `WithActionToken`): the board's behavior, split by area.
