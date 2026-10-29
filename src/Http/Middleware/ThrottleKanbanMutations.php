<?php

/**
 * Board mutation rate-limit middleware.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceKanbanLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceKanbanLivewire\Http\Middleware;

use ArtisanPackUI\EcommerceKanbanLivewire\EcommerceKanbanLivewireServiceProvider;
use Closure;
use Illuminate\Cache\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Livewire\Mechanisms\HandleRequests\HandleRequests;
use Symfony\Component\HttpFoundation\Response;

/**
 * Runs the board's Livewire update requests through the engine's
 * `ecommerce.admin.mutate` limiter (spec §5.2).
 *
 * Registered on the board routes as `ecommerce-kanban.throttle` and as
 * Livewire persistent middleware, so it re-runs on every update request from
 * a board page. Page loads are not counted, and neither are updates that only
 * read: no property updates beyond {@see self::READ_PROPERTIES} (the filter
 * bar, searches, and dialogs opening and closing), and only calls listed in
 * {@see self::READ_CALLS} (loading the board or a column, collapsing a
 * column, paging, polling, real-time refreshes, automation feedback,
 * searching, and opening or closing a dialog). Those lists only apply to
 * this package's components; any other component's update is a change.
 *
 * Over the limit it answers 429 with `Retry-After` and a JSON body
 * (`message`, `retry_after`); the page turns that into a toast. The response
 * is thrown, because Livewire discards responses that persistent middleware
 * return (other than redirects).
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceKanbanLivewire
 *
 * @since      1.0.0
 */
class ThrottleKanbanMutations
{
    /**
     * The route-middleware alias.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public const ALIAS = 'ecommerce-kanban.throttle';

    /**
     * The engine's named limiter.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public const LIMITER = 'ecommerce.admin.mutate';

    /**
     * Calls that only read, so a request made of them isn't counted.
     *
     * @since 1.0.0
     *
     * @var array<int, string>
     */
    public const READ_CALLS = [
        '$refresh',
        'loadBoard',
        'loadColumns',
        'toggleColumn',
        'showMore',
        'poll',
        'refreshColumns',
        'refreshCards',
        'boardEvent',
        'automationFeedback',
        'searchCustomers',
        'searchProducts',
        'clearFilters',
        'openMoveDialog',
        'closeMoveDialog',
        'cancelMove',
        'openAddOrder',
        'cancelAddOrder',
        'confirmRemoveCard',
        'cancelRemoveCard',
        'editColumn',
        'closeColumnDialog',
        'openAddColumn',
        'cancelAddColumn',
        'confirmDeleteColumn',
        'cancelDeleteColumn',
        'openFor',
        'show',
        'close',
        'cancelDelete',
    ];

    /**
     * Properties whose updates only change what the page shows, so a request
     * whose only updates are these still counts as a read: the board's
     * connection state (`liveFeedback`, sent when it changes), the filter
     * bar, the "Add order" search, and whether a dialog is open. Matched on
     * the first segment of the property path (`extraFilters.foo`).
     *
     * @since 1.0.0
     *
     * @var array<int, string>
     */
    public const READ_PROPERTIES = [
        'liveFeedback',
        'search',
        'customerId',
        'tags',
        'productIds',
        'placedFrom',
        'placedTo',
        'stale',
        'paymentStatus',
        'fulfillmentStatus',
        'extraFilters',
        'addOrderSearch',
        'moveDialog',
        'confirmingMove',
        'addOrderDialog',
        'confirmingRemove',
        'columnDialog',
        'addColumnDialog',
        'confirmingDeleteColumn',
        'open',
    ];

    /**
     * @since 1.0.0
     *
     * @param  RateLimiter  $limiter  The rate limiter.
     */
    public function __construct( private readonly RateLimiter $limiter )
    {
    }

    /**
     * Handles the request.
     *
     * @since 1.0.0
     *
     * @param  Request  $request  The request.
     * @param  Closure  $next     The next handler.
     *
     * @throws HttpResponseException With the 429 response when over the limit.
     *
     * @return Response
     */
    public function handle( Request $request, Closure $next ): Response
    {
        if ( ! self::isLivewireUpdate() ) {
            return $next( $request );
        }

        $update = self::updateRequest( $request );

        if ( self::onlyReads( $update ) ) {
            return $next( $request );
        }

        $definition = $this->limiter->limiter( self::LIMITER );

        if ( null === $definition ) {
            return $next( $request );
        }

        foreach ( self::limits( $definition( $update ) ) as $limit ) {
            $key = self::LIMITER . ':' . $limit->key;

            if ( $this->limiter->tooManyAttempts( $key, $limit->maxAttempts ) ) {
                throw new HttpResponseException( self::tooManyRequests( $this->limiter->availableIn( $key ) ) );
            }

            $this->limiter->hit( $key, $limit->decaySeconds );
        }

        return $next( $request );
    }

    /**
     * Whether the request being served is Livewire's update route.
     *
     * Livewire runs persistent middleware on a copy of the update request that
     * carries the page's path and method, so the request itself looks like a
     * page load; the route being served tells them apart.
     *
     * @since 1.0.0
     *
     * @return bool
     */
    public static function isLivewireUpdate(): bool
    {
        return app( HandleRequests::class )->isLivewireRoute();
    }

    /**
     * The request that carries the Livewire update's payload.
     *
     * As persistent middleware this runs on a GET copy of the update request
     * that Livewire makes with the page's path, and that copy's input holds
     * none of the update's JSON body. The update request itself is still
     * the application's current request, so its payload is read from there.
     *
     * @since 1.0.0
     *
     * @param  Request  $request  The request the middleware was given.
     *
     * @return Request
     */
    public static function updateRequest( Request $request ): Request
    {
        if ( is_array( $request->input( 'components' ) ) ) {
            return $request;
        }

        $current = app( 'request' );

        return $current instanceof Request && is_array( $current->input( 'components' ) ) ? $current : $request;
    }

    /**
     * Whether every component in the update only reads: it is one of this
     * package's components, it sends no property updates beyond
     * {@see self::READ_PROPERTIES}, and every call it makes is one of
     * {@see self::READ_CALLS}. A commit with no calls at all is a read too:
     * it is how Livewire 3 sends `wire:poll` and `$refresh`.
     *
     * @since 1.0.0
     *
     * @param  Request  $request  The Livewire update request.
     *
     * @return bool
     */
    public static function onlyReads( Request $request ): bool
    {
        $components = $request->input( 'components' );

        if ( ! is_array( $components ) || [] === $components ) {
            return false;
        }

        foreach ( $components as $component ) {
            if ( ! is_array( $component ) || ! self::isPackageComponent( $component ) ) {
                return false;
            }

            $properties = array_map( static fn ( mixed $path ): string => explode( '.', (string) $path, 2 )[0], array_keys( (array) ( $component['updates'] ?? [] ) ) );

            if ( [] !== array_diff( $properties, self::READ_PROPERTIES ) ) {
                return false;
            }

            foreach ( (array) ( $component['calls'] ?? [] ) as $call ) {
                if ( ! is_array( $call ) || ! in_array( $call['method'] ?? null, self::READ_CALLS, true ) ) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * The 429 response.
     *
     * @since 1.0.0
     *
     * @param  int  $retryAfter  Seconds until the next request is allowed.
     *
     * @return JsonResponse
     */
    public static function tooManyRequests( int $retryAfter ): JsonResponse
    {
        $retryAfter = max( 1, $retryAfter );

        return new JsonResponse(
            [
                'message'     => trans_choice(
                    'Too many changes in a short time. Try again in :count second.|Too many changes in a short time. Try again in :count seconds.',
                    $retryAfter,
                    [ 'count' => $retryAfter ],
                ),
                'retry_after' => $retryAfter,
            ],
            429,
            [ 'Retry-After' => (string) $retryAfter ],
        );
    }

    /**
     * Whether a component in a Livewire update is one of this package's, by
     * the name in its snapshot.
     *
     * @since 1.0.0
     *
     * @param  array<string, mixed>  $component  The component's part of the update.
     *
     * @return bool
     */
    private static function isPackageComponent( array $component ): bool
    {
        $snapshot = $component['snapshot'] ?? null;
        $snapshot = is_string( $snapshot ) ? json_decode( $snapshot, true ) : $snapshot;
        $name     = is_array( $snapshot ) ? ( $snapshot['memo']['name'] ?? null ) : null;

        return is_string( $name ) && array_key_exists( $name, EcommerceKanbanLivewireServiceProvider::LIVEWIRE_COMPONENTS );
    }

    /**
     * Normalizes a limiter result to a list of limits.
     *
     * @since 1.0.0
     *
     * @param  mixed  $result  What the limiter callback returned.
     *
     * @return array<int, Limit>
     */
    private static function limits( mixed $result ): array
    {
        $limits = $result instanceof Limit ? [ $result ] : (array) $result;

        return array_values( array_filter( $limits, static fn ( mixed $limit ): bool => $limit instanceof Limit ) );
    }
}
