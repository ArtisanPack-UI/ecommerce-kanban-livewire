<?php

declare( strict_types=1 );

use ArtisanPackUI\EcommerceKanbanLivewire\Http\Middleware\ThrottleKanbanMutations;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Livewire\Mechanisms\HandleRequests\HandleRequests;

/**
 * A Livewire update request with one component making `$calls`.
 *
 * @param  array<int, string>    $calls    Method names.
 * @param  array<string, mixed>  $updates  Property updates.
 * @param  string                $name     The component's name.
 */
function kanbanUpdate( $user, array $calls, array $updates = [], string $name = 'artisanpack-ecommerce-kanban-board' ): Request
{
    $request = Request::create( '/livewire/update', 'POST', [
        'components' => [ [
            'snapshot' => (string) json_encode( [ 'data' => [], 'memo' => [ 'name' => $name ] ] ),
            'updates'  => $updates,
            'calls'    => array_map( static fn ( string $method ): array => [ 'path' => '', 'method' => $method, 'params' => [] ], $calls ),
        ] ],
    ] );
    $request->setUserResolver( static fn () => $user );

    return $request;
}

/**
 * Runs the middleware as if the request were Livewire's update route.
 */
function throttle( Request $request ): int
{
    test()->mock( HandleRequests::class, static fn ( $mock ) => $mock->shouldReceive( 'isLivewireRoute' )->andReturn( true ) );

    try {
        return app( ThrottleKanbanMutations::class )->handle( $request, static fn (): Response => new Response( 'ok' ) )->getStatusCode();
    } catch ( HttpResponseException $exception ) {
        return $exception->getResponse()->getStatusCode();
    }
}

beforeEach( function (): void {
    config()->set( 'artisanpack.ecommerce.rate_limits.admin.mutate.per_user', 2 );
} );

it( 'counts writing updates against ecommerce.admin.mutate and answers 429 over it', function (): void {
    $user = makeUser();

    expect( throttle( kanbanUpdate( $user, [ 'moveCard' ] ) ) )->toBe( 200 )
        ->and( throttle( kanbanUpdate( $user, [], [ 'columnForm.label_override' => 'x' ] ) ) )->toBe( 200 )
        ->and( throttle( kanbanUpdate( $user, [ 'moveCard' ] ) ) )->toBe( 429 );
} );

it( 'does not count reads', function (): void {
    $user = makeUser();

    foreach ( range( 1, 5 ) as $attempt ) {
        expect( throttle( kanbanUpdate( $user, [ 'loadBoard', 'toggleColumn', 'showMore', '$refresh' ] ) ) )->toBe( 200 );
    }
} );

it( 'does not count polls, lazy column loads, real-time refreshes, or automation feedback', function ( array $calls, array $updates ): void {
    $user = makeUser();

    foreach ( range( 1, 5 ) as $attempt ) {
        expect( throttle( kanbanUpdate( $user, $calls, $updates ) ) )->toBe( 200 );
    }
} )->with( [
    'poll'                => [ [ 'poll' ], [] ],
    'poll with live flag' => [ [ 'poll' ], [ 'liveFeedback' => true ] ],
    'lazy columns'        => [ [ 'loadColumns' ], [] ],
    'column refresh'      => [ [ 'refreshColumns' ], [] ],
    'card refresh'        => [ [ 'refreshCards' ], [] ],
    'board event'         => [ [ 'boardEvent' ], [] ],
    'automation feedback' => [ [ 'automationFeedback' ], [] ],
] );

it( 'still counts a read call that carries a changed setting', function (): void {
    $user = makeUser();

    expect( throttle( kanbanUpdate( $user, [ 'poll' ], [ 'liveFeedback' => true, 'columnForm.wip_limit' => '3' ] ) ) )->toBe( 200 )
        ->and( throttle( kanbanUpdate( $user, [ 'poll' ], [ 'columnForm.wip_limit' => '4' ] ) ) )->toBe( 200 )
        ->and( throttle( kanbanUpdate( $user, [ 'poll' ], [ 'columnForm.wip_limit' => '5' ] ) ) )->toBe( 429 );
} );

it( 'does not count filtering, searching, or opening and closing dialogs', function ( array $calls, array $updates ): void {
    $user = makeUser();

    foreach ( range( 1, 5 ) as $attempt ) {
        expect( throttle( kanbanUpdate( $user, $calls, $updates ) ) )->toBe( 200 );
    }
} )->with( [
    'search filter'      => [ [], [ 'search' => 'jane' ] ],
    'satellite filter'   => [ [], [ 'extraFilters.priority' => 'high' ] ],
    'customer search'    => [ [ 'searchCustomers' ], [] ],
    'product search'     => [ [ 'searchProducts' ], [] ],
    'clear filters'      => [ [ 'clearFilters' ], [] ],
    'add order search'   => [ [], [ 'addOrderSearch' => '10' ] ],
    'open move dialog'   => [ [ 'openMoveDialog' ], [] ],
    'dismiss a dialog'   => [ [ 'cancelMove' ], [ 'confirmingMove' => false ] ],
] );

it( 'counts another component\'s updates, whatever they are named', function (): void {
    $user = makeUser();

    expect( throttle( kanbanUpdate( $user, [], [ 'search' => 'x' ], 'host-search' ) ) )->toBe( 200 )
        ->and( throttle( kanbanUpdate( $user, [ 'poll' ], [], 'host-search' ) ) )->toBe( 200 )
        ->and( throttle( kanbanUpdate( $user, [], [ 'search' => 'y' ], 'host-search' ) ) )->toBe( 429 );
} );

it( 'counts writes from the slide-out and panels, but not their reads', function (): void {
    $user = makeUser();

    foreach ( range( 1, 3 ) as $attempt ) {
        expect( throttle( kanbanUpdate( $user, [ 'show' ], [], 'artisanpack-ecommerce-kanban-card-detail' ) ) )->toBe( 200 );
    }

    expect( throttle( kanbanUpdate( $user, [ 'addToBoard' ], [], 'artisanpack-ecommerce-kanban-card-detail' ) ) )->toBe( 200 )
        ->and( throttle( kanbanUpdate( $user, [ 'setActive' ], [], 'artisanpack-ecommerce-kanban-automations' ) ) )->toBe( 200 )
        ->and( throttle( kanbanUpdate( $user, [ 'save' ], [], 'artisanpack-ecommerce-kanban-widget-picker' ) ) )->toBe( 429 );
} );

it( 'ignores page loads', function (): void {
    $user    = makeUser();
    $request = kanbanUpdate( $user, [ 'moveCard' ] );

    test()->mock( HandleRequests::class, static fn ( $mock ) => $mock->shouldReceive( 'isLivewireRoute' )->andReturn( false ) );

    foreach ( range( 1, 4 ) as $attempt ) {
        expect( app( ThrottleKanbanMutations::class )->handle( $request, static fn (): Response => new Response( 'ok' ) )->getStatusCode() )->toBe( 200 );
    }
} );

it( 'describes the wait in the 429 body', function (): void {
    $response = ThrottleKanbanMutations::tooManyRequests( 5 );

    expect( $response->getStatusCode() )->toBe( 429 )
        ->and( $response->headers->get( 'Retry-After' ) )->toBe( '5' )
        ->and( $response->getData( true ) )->toBe( [
            'message'     => 'Too many changes in a short time. Try again in 5 seconds.',
            'retry_after' => 5,
        ] );
} );

it( 'reads the update from the current request when Livewire hands it a copy without the body', function (): void {
    $user = makeUser();

    // As persistent middleware it is given a GET copy carrying the page's path and no JSON body.
    $copy = Request::create( '/ecommerce-kanban/boards/1', 'GET' );
    $copy->setUserResolver( static fn () => $user );

    foreach ( range( 1, 5 ) as $attempt ) {
        app()->instance( 'request', kanbanUpdate( $user, [ 'poll' ] ) );

        expect( throttle( $copy ) )->toBe( 200 );
    }

    app()->instance( 'request', kanbanUpdate( $user, [ 'moveCard' ] ) );

    expect( throttle( $copy ) )->toBe( 200 )
        ->and( throttle( $copy ) )->toBe( 200 )
        ->and( throttle( $copy ) )->toBe( 429 );
} );
