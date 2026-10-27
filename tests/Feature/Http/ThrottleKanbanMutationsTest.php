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
 */
function kanbanUpdate( $user, array $calls, array $updates = [] ): Request
{
    $request = Request::create( '/livewire/update', 'POST', [
        'components' => [ [
            'snapshot' => '{}',
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
        ->and( throttle( kanbanUpdate( $user, [], [ 'search' => 'x' ] ) ) )->toBe( 200 )
        ->and( throttle( kanbanUpdate( $user, [ 'moveCard' ] ) ) )->toBe( 429 );
} );

it( 'does not count reads', function (): void {
    $user = makeUser();

    foreach ( range( 1, 5 ) as $attempt ) {
        expect( throttle( kanbanUpdate( $user, [ 'loadBoard', 'toggleColumn', 'showMore', '$refresh' ] ) ) )->toBe( 200 );
    }
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
