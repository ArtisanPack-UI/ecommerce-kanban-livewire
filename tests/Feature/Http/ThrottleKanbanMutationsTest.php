<?php

declare( strict_types=1 );

use ArtisanPackUI\EcommerceKanbanLivewire\EcommerceKanbanLivewireServiceProvider;
use ArtisanPackUI\EcommerceKanbanLivewire\Http\Middleware\ThrottleKanbanMutations;
use ArtisanPackUI\EcommerceKanbanLivewire\Support\CardActions;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Livewire\Attributes\On;
use Livewire\Mechanisms\HandleRequests\HandleRequests;

/**
 * A Livewire update request with one component making `$calls`.
 *
 * @param  array<int, array<string, mixed>|string>  $calls  Method names, or raw call payloads.
 * @param  array<string, mixed>  $updates  Property updates.
 * @param  string                $name     The component's name.
 */
function kanbanUpdate( $user, array $calls, array $updates = [], string $name = 'artisanpack-ecommerce-kanban-board' ): Request
{
    $request = Request::create( '/livewire/update', 'POST', [
        'components' => [ [
            'snapshot' => (string) json_encode( [ 'data' => [], 'memo' => [ 'name' => $name ] ] ),
            'updates'  => $updates,
            'calls'    => array_map( static fn ( string|array $call ): array => is_array( $call ) ? $call : [ 'path' => '', 'method' => $call, 'params' => [] ], $calls ),
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

/**
 * The call Livewire sends when a component's listener for `$event` runs.
 *
 * @return array<string, mixed>
 */
function listenerCall( string $event, array $params = [] ): array
{
    return [ 'path' => '', 'method' => '__dispatch', 'params' => [ $event, $params ] ];
}

it( 'does not count the package\'s own event listeners, sent as __dispatch calls', function ( string $event, array $params, string $component ): void {
    $user = makeUser();

    foreach ( range( 1, 5 ) as $attempt ) {
        expect( throttle( kanbanUpdate( $user, [ listenerCall( $event, $params ) ], [], $component ) ) )->toBe( 200 );
    }
} )->with( [
    'open card'          => [ CardActions::OPEN_EVENT, [ 'orderId' => 1 ], 'artisanpack-ecommerce-kanban-card-detail' ],
    'card updated'       => [ 'ec-kb-card-updated', [ 'orderId' => 1 ], 'artisanpack-ecommerce-kanban-card-detail' ],
    'board refreshed'    => [ 'ec-kb-board-refreshed', [], 'artisanpack-ecommerce-kanban-card-detail' ],
    'move card'          => [ CardActions::MOVE_EVENT, [ 'orderId' => 1 ], 'artisanpack-ecommerce-kanban-board' ],
    'remove card'        => [ CardActions::REMOVE_EVENT, [ 'orderId' => 1 ], 'artisanpack-ecommerce-kanban-board' ],
    'board changed'      => [ 'ec-kb-board-changed', [], 'artisanpack-ecommerce-kanban-board' ],
    'edit widgets'       => [ 'ec-kb-edit-widgets', [], 'artisanpack-ecommerce-kanban-widget-picker' ],
    'edit automations'   => [ 'ec-kb-edit-automations', [], 'artisanpack-ecommerce-kanban-automations' ],
] );

it( 'maps every read event to a read call that listens for it on a package component', function (): void {
    expect( array_diff( ThrottleKanbanMutations::READ_EVENTS, ThrottleKanbanMutations::READ_CALLS ) )->toBe( [] );

    $listeners = [];

    foreach ( EcommerceKanbanLivewireServiceProvider::LIVEWIRE_COMPONENTS as $class ) {
        foreach ( ( new ReflectionClass( $class ) )->getMethods() as $method ) {
            foreach ( $method->getAttributes( On::class ) as $attribute ) {
                $listeners[ $attribute->getArguments()[0] ][] = $method->getName();
            }
        }
    }

    foreach ( ThrottleKanbanMutations::READ_EVENTS as $event => $method ) {
        expect( $listeners[ $event ] ?? [] )->toContain( $method );
    }
} );

it( 'counts __dispatch calls for unknown events or without an event', function ( array $call ): void {
    $user = makeUser();

    expect( throttle( kanbanUpdate( $user, [ $call ] ) ) )->toBe( 200 )
        ->and( throttle( kanbanUpdate( $user, [ $call ] ) ) )->toBe( 200 )
        ->and( throttle( kanbanUpdate( $user, [ $call ] ) ) )->toBe( 429 );
} )->with( [
    'satellite event' => [ [ 'path' => '', 'method' => '__dispatch', 'params' => [ 'print-provider.reprint', [ 'orderId' => 1 ] ] ] ],
    'missing params'  => [ [ 'path' => '', 'method' => '__dispatch' ] ],
    'empty params'    => [ [ 'path' => '', 'method' => '__dispatch', 'params' => [] ] ],
    'non-string name' => [ [ 'path' => '', 'method' => '__dispatch', 'params' => [ [ 'ec-kb-open-card' ], [] ] ] ],
] );

it( 'counts a listener call batched with a write', function (): void {
    $user  = makeUser();
    $calls = [ listenerCall( 'ec-kb-board-refreshed' ), 'moveCard' ];

    expect( throttle( kanbanUpdate( $user, $calls ) ) )->toBe( 200 )
        ->and( throttle( kanbanUpdate( $user, $calls ) ) )->toBe( 200 )
        ->and( throttle( kanbanUpdate( $user, $calls ) ) )->toBe( 429 );
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
