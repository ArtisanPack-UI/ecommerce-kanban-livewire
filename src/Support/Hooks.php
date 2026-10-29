<?php

/**
 * Extension hooks.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceKanbanLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceKanbanLivewire\Support;

/**
 * Every `ap.ecommerceKanbanLivewire.*` hook the board offers (spec §7.2,
 * §7.5, §8), so satellites (print providers, shipping labels, Slack) can
 * extend it without forking views. Register them with `addFilter()` /
 * `addAction()` from a service provider's `boot()`.
 *
 * Hooks only run for what the viewer may already see: the board has been
 * authorized before any of them run, cards and columns are the ones the
 * board shows, and actions that name an `ability` are dropped for viewers
 * who don't hold it. A hook that needs more than that should check
 * abilities itself.
 *
 * When a hook isn't enough, publish the views (`php artisan vendor:publish
 * --tag=ecommerce-kanban-views`) and edit the copies in
 * `resources/views/vendor/ecommerce-kanban`. Published views shadow the
 * package's and don't receive its updates, so publish only the files you
 * change.
 *
 * Filters:
 *
 * - {@see self::WIDGET_VIEW} — `( ?string $view, string $key, array $payload ): ?string`,
 *   a Blade view for a card widget key ({@see WidgetViews}).
 *
 *   ```php
 *   addFilter( 'ap.ecommerceKanbanLivewire.widgetView', fn ( ?string $view, string $key ): ?string => 'print-status' === $key ? 'print-provider::kanban.status' : $view );
 *   ```
 *
 * - {@see self::CARD_ACTIONS} — `( array $actions, Order $order, KanbanColumn $column, KanbanBoard $board ): array`,
 *   a card's actions menu ({@see CardActions}).
 *
 *   ```php
 *   addFilter( 'ap.ecommerceKanbanLivewire.cardActions', function ( array $actions ): array {
 *       $actions[] = [ 'key' => 'reprint', 'label' => __( 'Reprint' ), 'icon' => 'o-printer', 'ability' => 'order.update', 'event' => 'print-provider.reprint' ];
 *
 *       return $actions;
 *   } );
 *   ```
 *
 * - {@see self::FILTERS} — `( array $controls, KanbanBoard $board ): array`,
 *   extra filter bar controls, paired with the engine's
 *   `ap.ecommerce.kanban.cardQuery` ({@see FilterControls}).
 *
 *   ```php
 *   addFilter( 'ap.ecommerceKanbanLivewire.filters', fn ( array $controls ): array => [ ...$controls, [ 'key' => 'rush', 'label' => __( 'Rush orders' ), 'type' => 'toggle' ] ] );
 *   ```
 *
 * - {@see self::BOARD_TOOLBAR} — `( array $actions, KanbanBoard $board, ?Authenticatable $user ): array`,
 *   extra buttons in the board header ({@see BoardToolbar}).
 *
 *   ```php
 *   addFilter( 'ap.ecommerceKanbanLivewire.boardToolbar', function ( array $actions ): array {
 *       $actions[] = [ 'key' => 'rush', 'label' => __( 'Rush mode' ), 'icon' => 'o-bolt', 'ability' => 'kanbanBoard.update', 'event' => 'rush.toggle' ];
 *
 *       return $actions;
 *   } );
 *   ```
 *
 * - {@see self::CARD_CLASSES} — `( array $classes, Order $order, KanbanColumn $column, KanbanBoard $board ): array`,
 *   extra CSS classes on a card ({@see CardPresenter}). Add the classes to
 *   the host's Tailwind sources.
 *
 *   ```php
 *   addFilter( 'ap.ecommerceKanbanLivewire.cardClasses', fn ( array $classes, Order $order ): array => $order->total_amount > 50_000 ? [ ...$classes, 'border-l-4 border-l-warning' ] : $classes );
 *   ```
 *
 * - {@see self::BOARD_URL} — `( ?string $url, KanbanBoard $board, array $query ): ?string`
 *   and {@see self::PICKER_URL} — `( ?string $url ): ?string`, where board
 *   and picker links point when the host serves the board itself
 *   ({@see BoardUrls}).
 *
 * - {@see self::VITE_ENTRIES} — `( array $entries ): array`, the Vite
 *   entries the standalone layout loads.
 *
 * Actions:
 *
 * - {@see self::BOARD_RENDERED} — `( KanbanBoard $board, array $columns, ?Authenticatable $user )`,
 *   after the board renders; `$columns` is what it shows (labels, counts,
 *   and the presented cards).
 *
 *   ```php
 *   addAction( 'ap.ecommerceKanbanLivewire.boardRendered', fn ( KanbanBoard $board ) => Metrics::increment( 'kanban.views', [ 'board' => $board->id ] ) );
 *   ```
 *
 * - {@see self::CARD_MOVE_FAILED} — `( string $code, ?Order $order, ?KanbanColumn $column, KanbanBoard $board, ?Authenticatable $user )`,
 *   when the board refuses or the engine rejects a move; `$code` is a
 *   `KanbanOperationException` code (`wip-limit-reached`, …).
 *
 *   ```php
 *   addAction( 'ap.ecommerceKanbanLivewire.cardMoveFailed', fn ( string $code, ?Order $order ) => Log::info( 'Kanban move refused', [ 'code' => $code, 'order' => $order?->id ] ) );
 *   ```
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceKanbanLivewire
 *
 * @since      1.0.0
 */
final class Hooks
{
    /**
     * The card widget view filter.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public const WIDGET_VIEW = WidgetViews::FILTER;

    /**
     * The card actions filter.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public const CARD_ACTIONS = CardActions::FILTER;

    /**
     * The filter bar controls filter.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public const FILTERS = FilterControls::FILTER;

    /**
     * The board header actions filter.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public const BOARD_TOOLBAR = BoardToolbar::FILTER;

    /**
     * The card CSS classes filter.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public const CARD_CLASSES = CardPresenter::CLASSES_FILTER;

    /**
     * The board URL filter.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public const BOARD_URL = 'ap.ecommerceKanbanLivewire.boardUrl';

    /**
     * The board picker URL filter.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public const PICKER_URL = 'ap.ecommerceKanbanLivewire.pickerUrl';

    /**
     * The standalone layout's Vite entries filter.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public const VITE_ENTRIES = 'ap.ecommerceKanbanLivewire.layout.viteEntries';

    /**
     * The action run after the board renders.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public const BOARD_RENDERED = 'ap.ecommerceKanbanLivewire.boardRendered';

    /**
     * The action run when a card move is refused.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public const CARD_MOVE_FAILED = 'ap.ecommerceKanbanLivewire.cardMoveFailed';
}
