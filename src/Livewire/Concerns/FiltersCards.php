<?php

/**
 * Filter bar state.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceKanbanLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceKanbanLivewire\Livewire\Concerns;

use ArtisanPackUI\Ecommerce\Kanban\KanbanCardFilters;
use ArtisanPackUI\Ecommerce\Kanban\KanbanCardTags;
use ArtisanPackUI\Ecommerce\Models\Customer;
use ArtisanPackUI\Ecommerce\Models\Order;
use ArtisanPackUI\Ecommerce\Models\OrderBoardAssignment;
use ArtisanPackUI\Ecommerce\Models\OrderItem;
use ArtisanPackUI\Ecommerce\Models\Product;
use ArtisanPackUI\EcommerceKanbanLivewire\Support\FilterControls;
use ArtisanPackUI\EcommerceKanbanLivewire\Support\Statuses;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;

/**
 * The board's filter bar (spec §7.5): search, customer, tags, products,
 * placed date range, stale only, payment and fulfillment status, and the
 * controls satellites add. Every value lives in the query string, so a
 * filtered board is a shareable link.
 *
 * The properties are untyped because they are filled from the URL, which
 * can hold anything; {@see self::cardFilters()} reads them through
 * {@see KanbanCardFilters::fromArray()}, which drops invalid values.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceKanbanLivewire
 *
 * @since      1.0.0
 */
trait FiltersCards
{
    /**
     * Most options a server-searched filter offers.
     *
     * @since 1.0.0
     *
     * @var int
     */
    private const SEARCH_LIMIT = 20;

    /**
     * Order number, email, or customer name.
     *
     * @since 1.0.0
     *
     * @var mixed
     */
    #[Url( as: 'q', except: '' )]
    public mixed $search = '';

    /**
     * Customer id.
     *
     * @since 1.0.0
     *
     * @var mixed
     */
    #[Url( as: 'customer', except: null )]
    public mixed $customerId = null;

    /**
     * Tags.
     *
     * @since 1.0.0
     *
     * @var mixed
     */
    #[Url( except: [] )]
    public mixed $tags = [];

    /**
     * Product ids.
     *
     * @since 1.0.0
     *
     * @var mixed
     */
    #[Url( as: 'products', except: [] )]
    public mixed $productIds = [];

    /**
     * Placed on or after (`Y-m-d`).
     *
     * @since 1.0.0
     *
     * @var mixed
     */
    #[Url( as: 'from', except: '' )]
    public mixed $placedFrom = '';

    /**
     * Placed on or before (`Y-m-d`).
     *
     * @since 1.0.0
     *
     * @var mixed
     */
    #[Url( as: 'to', except: '' )]
    public mixed $placedTo = '';

    /**
     * Stale cards only.
     *
     * @since 1.0.0
     *
     * @var mixed
     */
    #[Url( except: false )]
    public mixed $stale = false;

    /**
     * Payment statuses.
     *
     * @since 1.0.0
     *
     * @var mixed
     */
    #[Url( as: 'payment', except: [] )]
    public mixed $paymentStatus = [];

    /**
     * Fulfillment statuses.
     *
     * @since 1.0.0
     *
     * @var mixed
     */
    #[Url( as: 'fulfillment', except: [] )]
    public mixed $fulfillmentStatus = [];

    /**
     * Satellite filter values, keyed by control key.
     *
     * @since 1.0.0
     *
     * @var mixed
     */
    #[Url( as: 'f', except: [] )]
    public mixed $extraFilters = [];

    /**
     * The tags on the board's cards, loaded with the cards
     * ({@see self::loadTagOptions()}) rather than on every request.
     *
     * @since 1.0.0
     *
     * @var array<int, array{id: string, name: string}>
     */
    #[Locked]
    public array $tagOptions = [];

    /**
     * Customer choices from the last search (plus the chosen customer).
     *
     * @since 1.0.0
     *
     * @var array<int, array{id: int, name: string}>
     */
    public array $customerOptions = [];

    /**
     * Product choices from the last search (plus the chosen products).
     *
     * @since 1.0.0
     *
     * @var array<int, array{id: int, name: string}>
     */
    public array $productOptions = [];

    /**
     * The filters as the engine reads them.
     *
     * @since 1.0.0
     *
     * @return KanbanCardFilters
     */
    public function cardFilters(): KanbanCardFilters
    {
        $controls = array_column( FilterControls::for( $this->board ), null, 'key' );
        $extra    = [];

        foreach ( is_array( $this->extraFilters ) ? $this->extraFilters : [] as $key => $value ) {
            if ( isset( $controls[ $key ] ) && is_scalar( $value ) && '' !== (string) $value ) {
                $extra[ $key ] = (string) $value;
            }
        }

        return KanbanCardFilters::fromArray( [
            'search'             => is_scalar( $this->search ) ? (string) $this->search : null,
            'customer_id'        => $this->customerId,
            'tags'               => $this->tags,
            'product_ids'        => $this->productIds,
            'placed_from'        => self::dateValue( $this->placedFrom ),
            'placed_to'          => self::dateValue( $this->placedTo ),
            'stale'              => filter_var( $this->stale, FILTER_VALIDATE_BOOLEAN ),
            'payment_status'     => $this->paymentStatus,
            'fulfillment_status' => $this->fulfillmentStatus,
        ] + $extra );
    }

    /**
     * How many filters are set.
     *
     * @since 1.0.0
     *
     * @return int
     */
    public function activeFilterCount(): int
    {
        $filters = $this->cardFilters();

        return count( array_filter( [
            null !== $filters->search,
            null !== $filters->customerId,
            [] !== $filters->tags,
            [] !== $filters->productIds,
            null !== $filters->placedFrom || null !== $filters->placedTo,
            null !== $filters->movedBefore,
            [] !== $filters->paymentStatus,
            [] !== $filters->fulfillmentStatus,
        ] ) ) + count( $filters->extra );
    }

    /**
     * Clears every filter.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function clearFilters(): void
    {
        $this->reset( 'search', 'customerId', 'tags', 'productIds', 'placedFrom', 'placedTo', 'stale', 'paymentStatus', 'fulfillmentStatus', 'extraFilters' );
        $this->limits = [];
    }

    /**
     * Searches the customers with orders on this board for the customer
     * filter. Only those: anyone who can view the board already sees their
     * names on the cards, but not the store's other customers.
     *
     * @since 1.0.0
     *
     * @param  mixed  $value  Search text.
     *
     * @return void
     */
    public function searchCustomers( mixed $value = '' ): void
    {
        $like  = self::like( $value );
        $query = $this->boardCustomers()
            ->orderBy( 'last_name' )
            ->orderBy( 'first_name' )
            ->orderBy( 'id' )
            ->limit( self::SEARCH_LIMIT );

        if ( null !== $like ) {
            $query->where( static function ( $match ) use ( $like ): void {
                foreach ( [ 'first_name', 'last_name', 'email' ] as $column ) {
                    $match->orWhereRaw( 'LOWER(' . $column . ") LIKE ? ESCAPE '!'", [ $like ] );
                }
            } );
        }

        $this->customerOptions = $this->withSelected(
            $query->get()->map( static fn ( Customer $customer ): array => self::customerOption( $customer ) )->all(),
            is_numeric( $this->customerId ) ? [ (int) $this->customerId ] : [],
            fn ( array $ids ): array => $this->boardCustomers()->whereKey( $ids )->get()->map( static fn ( Customer $customer ): array => self::customerOption( $customer ) )->all(),
        );
    }

    /**
     * Searches the products in this board's orders for the product filter.
     *
     * @since 1.0.0
     *
     * @param  mixed  $value  Search text.
     *
     * @return void
     */
    public function searchProducts( mixed $value = '' ): void
    {
        $like  = self::like( $value );
        $query = $this->boardProducts()
            ->orderBy( 'name' )
            ->orderBy( 'id' )
            ->limit( self::SEARCH_LIMIT );

        if ( null !== $like ) {
            $query->where( static function ( $match ) use ( $like ): void {
                $match->orWhereRaw( "LOWER(name) LIKE ? ESCAPE '!'", [ $like ] )->orWhereRaw( "LOWER(sku) LIKE ? ESCAPE '!'", [ $like ] );
            } );
        }

        $this->productOptions = $this->withSelected(
            $query->get()->map( static fn ( Product $product ): array => self::productOption( $product ) )->all(),
            $this->cardFilters()->productIds,
            fn ( array $ids ): array => $this->boardProducts()->whereKey( $ids )->get()->map( static fn ( Product $product ): array => self::productOption( $product ) )->all(),
        );
    }

    /**
     * Resets "Show more" paging whenever a filter changes, so each column
     * starts again from its first page of matches.
     *
     * @since 1.0.0
     *
     * @param  string  $property  The updated property.
     *
     * @return void
     */
    public function updatedFiltersCards( string $property ): void
    {
        if ( self::isFilterProperty( $property ) ) {
            $this->limits = [];
        }
    }

    /**
     * Loads the tag filter's options from the board's cards.
     *
     * @since 1.0.0
     *
     * @return void
     */
    protected function loadTagOptions(): void
    {
        $this->tagOptions = array_map( static fn ( string $tag ): array => [ 'id' => $tag, 'name' => $tag ], app( KanbanCardTags::class )->forBoard( $this->board ) );
    }

    /**
     * The filter bar's view data.
     *
     * @since 1.0.0
     *
     * @return array<string, mixed>
     */
    protected function filterBarData(): array
    {
        if ( [] === $this->customerOptions && is_numeric( $this->customerId ) ) {
            $this->searchCustomers();
        }

        if ( [] === $this->productOptions && [] !== $this->cardFilters()->productIds ) {
            $this->searchProducts();
        }

        return [
            'activeFilters'      => $this->activeFilterCount(),
            'paymentOptions'     => Statuses::options( 'payment' ),
            'fulfillmentOptions' => Statuses::options( 'fulfillment' ),
            'extraControls'      => FilterControls::for( $this->board ),
        ];
    }

    /**
     * Whether `$property` is one of the filter bar's values.
     *
     * @since 1.0.0
     *
     * @param  string  $property  Property name (may be a nested path).
     *
     * @return bool
     */
    protected static function isFilterProperty( string $property ): bool
    {
        return in_array( explode( '.', $property )[0], [ 'search', 'customerId', 'tags', 'productIds', 'placedFrom', 'placedTo', 'stale', 'paymentStatus', 'fulfillmentStatus', 'extraFilters' ], true );
    }

    /**
     * Customers with an order on this board: the only ones the customer
     * filter offers, including for an id typed into the URL.
     *
     * @since 1.0.0
     *
     * @return Builder<Customer>
     */
    private function boardCustomers(): Builder
    {
        return Customer::query()->whereIn( 'id', $this->boardOrders()->whereNotNull( 'customer_id' )->select( 'customer_id' ) );
    }

    /**
     * Products in this board's orders: the only ones the product filter
     * offers, including for ids typed into the URL.
     *
     * @since 1.0.0
     *
     * @return Builder<Product>
     */
    private function boardProducts(): Builder
    {
        return Product::query()->whereIn( 'id', OrderItem::query()->whereIn( 'order_id', $this->boardOrders()->select( 'id' ) )->whereNotNull( 'product_id' )->select( 'product_id' ) );
    }

    /**
     * Orders with an active card on this board.
     *
     * @since 1.0.0
     *
     * @return Builder<Order>
     */
    private function boardOrders(): Builder
    {
        return Order::query()->whereIn( 'id', OrderBoardAssignment::query()->active()->where( 'board_id', $this->boardId )->select( 'order_id' ) );
    }

    /**
     * Prepends the chosen records missing from `$options`.
     *
     * @since 1.0.0
     *
     * @param  array<int, array{id: int, name: string}>  $options   Search results.
     * @param  array<int, int>                           $selected  Chosen ids.
     * @param  callable                                  $load      Loads options for ids.
     *
     * @return array<int, array{id: int, name: string}>
     */
    private function withSelected( array $options, array $selected, callable $load ): array
    {
        $missing = array_values( array_diff( $selected, array_column( $options, 'id' ) ) );

        return [] === $missing ? $options : [ ...$load( $missing ), ...$options ];
    }

    /**
     * A LIKE pattern for `$value`, or null for an empty search.
     *
     * @since 1.0.0
     *
     * @param  mixed  $value  Search text.
     *
     * @return string|null
     */
    private static function like( mixed $value ): ?string
    {
        $value = is_scalar( $value ) ? mb_substr( trim( (string) $value ), 0, 100 ) : '';

        return '' === $value ? null : '%' . str_replace( [ '!', '%', '_' ], [ '!!', '!%', '!_' ], mb_strtolower( $value ) ) . '%';
    }

    /**
     * A `Y-m-d` date, or null.
     *
     * @since 1.0.0
     *
     * @param  mixed  $value  Raw value.
     *
     * @return string|null
     */
    private static function dateValue( mixed $value ): ?string
    {
        return is_string( $value ) && 1 === preg_match( '/^\d{4}-\d{2}-\d{2}$/D', $value ) ? $value : null;
    }

    /**
     * @since 1.0.0
     *
     * @param  Customer  $customer  Customer.
     *
     * @return array{id: int, name: string}
     */
    private static function customerOption( Customer $customer ): array
    {
        $name = trim( (string) $customer->first_name . ' ' . (string) $customer->last_name );

        return [ 'id' => (int) $customer->id, 'name' => '' === $name ? (string) $customer->email : $name . ' (' . $customer->email . ')' ];
    }

    /**
     * @since 1.0.0
     *
     * @param  Product  $product  Product.
     *
     * @return array{id: int, name: string}
     */
    private static function productOption( Product $product ): array
    {
        $sku = trim( (string) $product->sku );

        return [ 'id' => (int) $product->id, 'name' => '' === $sku ? (string) $product->name : $product->name . ' (' . $sku . ')' ];
    }
}
