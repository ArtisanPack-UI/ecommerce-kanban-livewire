<?php

declare( strict_types=1 );

namespace Tests;

use ArtisanPackUI\Ecommerce\Kanban\Triggers\UpdateOrderFieldTrigger;
use ArtisanPackUI\Ecommerce\Models\KanbanAutomation;
use ArtisanPackUI\Ecommerce\Models\KanbanBoard;
use ArtisanPackUI\Ecommerce\Models\KanbanColumn;
use ArtisanPackUI\Ecommerce\Models\Order;
use ArtisanPackUI\Ecommerce\Models\OrderBoardAssignment;
use ArtisanPackUI\Ecommerce\Models\OrderSubstatus;
use Illuminate\Support\Facades\Gate;
use RuntimeException;
use Tests\Fixtures\User;

/**
 * Base case for the browser suite.
 *
 * Serves the Testbench app from `tests/Browser/workbench/public`, where
 * `npm run build:browser` writes the asset bundle, loads that bundle in
 * the standalone board layout, signs in a staff user who may do everything
 * on a board, and seeds a small, repeatable demo board (see
 * {@see self::seedDemoBoard()}).
 */
abstract class BrowserTestCase extends TestCase
{
    /**
     * The workbench entries the board layout loads.
     *
     * @var array<int, string>
     */
    public const VITE_ENTRIES = [
        'tests/Browser/workbench/resources/css/app.css',
        'tests/Browser/workbench/resources/js/app.js',
    ];

    /**
     * The demo board.
     *
     * @var KanbanBoard
     */
    public KanbanBoard $board;

    /**
     * The demo board's columns, keyed by label: `To pack`, `Packing`,
     * `Ready to ship`, `Cancelled`.
     *
     * @var array<string, KanbanColumn>
     */
    public array $columns = [];

    /**
     * The demo orders, keyed by order number.
     *
     * @var array<string, Order>
     */
    public array $orders = [];

    /**
     * The signed-in staff user.
     *
     * @var User
     */
    protected User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        if ( ! is_file( public_path( 'build/manifest.json' ) ) ) {
            throw new RuntimeException( 'Build the browser asset bundle first: npm ci && npm run build:browser' );
        }

        addFilter( 'ap.ecommerceKanbanLivewire.layout.viteEntries', static fn (): array => self::VITE_ENTRIES );

        $this->staff = User::query()->create( [ 'name' => 'Sam Staff', 'email' => 'staff@example.test', 'password' => 'secret' ] );

        Gate::define( 'ecommerce.admin', static fn ( $user ): bool => 'staff@example.test' === $user->email );

        $this->actingAs( $this->staff );

        $this->seedDemoBoard();
    }

    protected function tearDown(): void
    {
        removeAllFilters( 'ap.ecommerceKanbanLivewire.layout.viteEntries' );

        parent::tearDown();
    }

    protected function defineEnvironment( $app ): void
    {
        parent::defineEnvironment( $app );

        $app->usePublicPath( __DIR__ . '/Browser/workbench/public' );

        $app['config']->set( 'app.name', 'Demo Store' );
        $app['config']->set( 'auth.providers.users.model', User::class );
        $app['config']->set( 'artisanpack.ecommerce.base_currency', 'USD' );
    }

    /**
     * Seeds the demo board, "Fulfillment":
     *
     * - `To pack` holds orders 1001 (tagged `rush`, placed 2026-03-02),
     *   1002 (tagged `wholesale`, placed 2026-03-10), and 1003 (placed
     *   2026-03-20), top to bottom.
     * - `Packing` has a WIP limit of 1 and holds order 1004.
     * - `Ready to ship` is empty. Moving a card into it runs an automation
     *   that sets the order's `meta.priority`.
     * - `Cancelled` is an exit column (the order's status becomes
     *   cancelled), so a move into it asks first.
     *
     * @return void
     */
    protected function seedDemoBoard(): void
    {
        $this->board = KanbanBoard::factory()->create( [ 'name' => 'Fulfillment', 'description' => 'Orders from payment to the carrier.', 'position' => 0 ] );

        $specs = [
            'To pack'       => [ 'system_status' => 'processing', 'color' => '#2563eb', 'icon' => 'o-inbox' ],
            'Packing'       => [ 'system_status' => 'processing', 'color' => '#f59e0b', 'icon' => 'o-cube', 'wip_limit' => 1 ],
            'Ready to ship' => [ 'system_status' => 'processing', 'color' => '#16a34a', 'icon' => 'o-truck' ],
            'Cancelled'     => [ 'system_status' => 'cancelled', 'color' => '#dc2626', 'icon' => 'o-x-circle' ],
        ];

        foreach ( array_keys( $specs ) as $position => $label ) {
            $spec      = $specs[ $label ];
            $substatus = OrderSubstatus::factory()->create( [
                'label'         => $label,
                'system_status' => $spec['system_status'],
                'color'         => $spec['color'],
                'icon'          => $spec['icon'],
                'position'      => $position,
            ] );

            $this->columns[ $label ] = KanbanColumn::factory()->create( [
                'board_id'     => $this->board->id,
                'substatus_id' => $substatus->id,
                'position'     => $position,
                'wip_limit'    => $spec['wip_limit'] ?? null,
            ] );
        }

        $cards = [
            '1001' => [ 'To pack', [ 'tags' => [ 'rush' ] ], '2026-03-02 10:00:00' ],
            '1002' => [ 'To pack', [ 'tags' => [ 'wholesale' ] ], '2026-03-10 10:00:00' ],
            '1003' => [ 'To pack', [], '2026-03-20 10:00:00' ],
            '1004' => [ 'Packing', [], '2026-03-21 10:00:00' ],
        ];

        $position = 0;

        foreach ( $cards as $number => [ $column, $meta, $placedAt ] ) {
            $order = Order::factory()->guest()->create( [
                'order_number'   => $number,
                'email'          => 'buyer' . $number . '@example.test',
                'system_status'  => 'processing',
                'payment_status' => 'paid',
                'meta'           => $meta,
                'placed_at'      => $placedAt,
            ] );

            OrderBoardAssignment::factory()->create( [
                'order_id'     => $order->id,
                'board_id'     => $this->board->id,
                'substatus_id' => $this->columns[ $column ]->substatus_id,
                'position'     => $position++,
            ] );

            $this->orders[ $number ] = $order;
        }

        KanbanAutomation::factory()->create( [
            'board_id'       => $this->board->id,
            'to_column_id'   => $this->columns['Ready to ship']->id,
            'trigger_key'    => UpdateOrderFieldTrigger::KEY,
            'trigger_config' => [ 'field' => 'meta.priority', 'value' => 'high' ],
            'is_active'      => true,
        ] );
    }

    /**
     * The demo board's URL, with optional query parameters.
     *
     * @param  array<string, mixed>  $query  Query parameters.
     *
     * @return string
     */
    protected function boardUrl( array $query = [] ): string
    {
        return route( 'artisanpack.ecommerce.kanban.boards.show', [ 'board' => $this->board->id, ...$query ] );
    }
}
