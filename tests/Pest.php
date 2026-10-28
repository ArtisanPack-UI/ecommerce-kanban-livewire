<?php

use ArtisanPackUI\Ecommerce\Models\KanbanBoard;
use ArtisanPackUI\Ecommerce\Models\KanbanColumn;
use ArtisanPackUI\Ecommerce\Models\Order;
use ArtisanPackUI\Ecommerce\Models\OrderBoardAssignment;
use ArtisanPackUI\Ecommerce\Models\OrderSubstatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Tests\Fixtures\User;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Feature tests boot the package, the engine, and Livewire in Testbench.
| Unit tests run without a Laravel application.
|
| Browser tests (Pest 4 browser plugin) run the board in a real browser
| against a seeded demo board, with the asset bundle built by `npm run
| build:browser`. CI runs them in their own job; the unit / feature job
| runs only the Unit and Feature suites, since loading the Browser suite
| starts Playwright.
|
*/

pest()->extend( Tests\TestCase::class )
    ->use( RefreshDatabase::class )
    ->in( 'Feature' );

pest()->extend( Tests\BrowserTestCase::class )
    ->use( RefreshDatabase::class )
    ->group( 'browser' )
    ->in( 'Browser' );

pest()->browser()->timeout( 10_000 );

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
*/

if ( ! function_exists( 'makeUser' ) ) {
    /**
     * Creates a host-app user.
     *
     * @param  array<string, mixed>  $attributes  Attribute overrides.
     */
    function makeUser( array $attributes = [] ): User
    {
        static $sequence = 0;
        $sequence++;

        return User::query()->create( $attributes + [
            'name'     => 'User ' . $sequence,
            'email'    => 'user' . $sequence . '-' . uniqid() . '@example.test',
            'password' => 'secret',
        ] );
    }
}

if ( ! function_exists( 'grantAbilities' ) ) {
    /**
     * Defines `ecommerce.{ability}` gates that allow every user.
     *
     * Undefined abilities stay denied (the engine's default), as long as the
     * umbrella `ecommerce.admin` gate is not defined.
     *
     * @param  array<int, string>  $abilities  `{resource}.{action}` abilities.
     */
    function grantAbilities( array $abilities ): void
    {
        foreach ( $abilities as $ability ) {
            Gate::define( 'ecommerce.' . $ability, static fn (): bool => true );
        }
    }
}

if ( ! function_exists( 'denyAbilities' ) ) {
    /**
     * Defines `ecommerce.{ability}` gates that deny every user.
     *
     * @param  array<int, string>  $abilities  `{resource}.{action}` abilities.
     */
    function denyAbilities( array $abilities ): void
    {
        foreach ( $abilities as $ability ) {
            Gate::define( 'ecommerce.' . $ability, static fn (): bool => false );
        }
    }
}

if ( ! function_exists( 'useFixtureViews' ) ) {
    /**
     * Registers the fixture layouts: `cms::admin.layouts.app`,
     * `ecommerce-admin::layouts.app`, and `host::layout` / `host::widget`.
     */
    function useFixtureViews(): void
    {
        $views = __DIR__ . '/Fixtures/views';

        View::addNamespace( 'cms', $views . '/cms' );
        View::replaceNamespace( 'ecommerce-admin', $views . '/ecommerce-admin' );
        View::addNamespace( 'host', $views . '/host' );
    }
}

if ( ! function_exists( 'makeBoard' ) ) {
    /**
     * Creates a board with one column per entry in `$columns`, each holding
     * that many cards. A column entry is a card count, or an array of column
     * attributes plus a `cards` count.
     *
     * @param  array<int, array<string, mixed>|int>  $columns     Columns, left to right.
     * @param  array<string, mixed>                  $attributes  Board attributes.
     *
     * @return array{0: KanbanBoard, 1: Illuminate\Support\Collection<int, KanbanColumn>}
     */
    function makeBoard( array $columns = [ 1 ], array $attributes = [] ): array
    {
        $board = KanbanBoard::factory()->create( $attributes );
        $made  = collect();

        foreach ( array_values( $columns ) as $position => $spec ) {
            $spec      = is_array( $spec ) ? $spec : [ 'cards' => $spec ];
            $cards     = (int) ( $spec['cards'] ?? 0 );
            $substatus = OrderSubstatus::factory()->create( [ 'label' => 'Column ' . ( $position + 1 ), 'position' => $position ] );
            $column    = KanbanColumn::factory()->create( array_merge(
                [ 'board_id' => $board->id, 'substatus_id' => $substatus->id, 'position' => $position ],
                Illuminate\Support\Arr::except( $spec, [ 'cards' ] ),
            ) );

            for ( $i = 0; $i < $cards; $i++ ) {
                addCard( $column );
            }

            $made->push( $column );
        }

        return [ $board, $made ];
    }
}

if ( ! function_exists( 'addCard' ) ) {
    /**
     * Places a new order on `$column`'s board, in that column.
     *
     * @param  KanbanColumn          $column      The column.
     * @param  array<string, mixed>  $order       Order attributes.
     * @param  array<string, mixed>  $assignment  Assignment attributes.
     */
    function addCard( KanbanColumn $column, array $order = [], array $assignment = [] ): OrderBoardAssignment
    {
        return OrderBoardAssignment::factory()->create( $assignment + [
            'order_id'     => Order::factory()->create( $order )->id,
            'board_id'     => $column->board_id,
            'substatus_id' => $column->substatus_id,
        ] );
    }
}
