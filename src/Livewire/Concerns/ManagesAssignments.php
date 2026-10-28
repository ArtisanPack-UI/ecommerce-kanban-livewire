<?php

/**
 * Board assignments.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceKanbanLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceKanbanLivewire\Livewire\Concerns;

use ArtisanPackUI\Ecommerce\Exceptions\EcommerceException;
use ArtisanPackUI\Ecommerce\Models\KanbanColumn;
use ArtisanPackUI\Ecommerce\Models\Order;
use ArtisanPackUI\Ecommerce\Models\OrderBoardAssignment;
use ArtisanPackUI\Ecommerce\Services\KanbanRoutingService;
use ArtisanPackUI\EcommerceKanbanLivewire\Support\Authorization;
use ArtisanPackUI\EcommerceKanbanLivewire\Support\CardActions;
use ArtisanPackUI\EcommerceKanbanLivewire\Support\MoveErrors;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;

/**
 * Putting orders on the board and taking them off (spec §7.6.1): the
 * header's "Add order" dialog searches orders that aren't on the board and
 * assigns one (only orders the user may view), in a chosen column or the
 * engine's entry column; the card
 * menu's (and the slide-out's) "Remove from board" asks first and removes
 * the card once per action token. Both need `kanbanBoard.update` and go
 * through `KanbanRoutingService`, so the board broadcast and hooks fire.
 *
 * Uses {@see WithActionToken}, {@see AuthorizesKanban}, {@see MovesCards}
 * (for the acting user and the board's columns), and the Toast trait.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceKanbanLivewire
 *
 * @since      1.0.0
 */
trait ManagesAssignments
{
    /**
     * Whether the "Add order" dialog is open.
     *
     * @since 1.0.0
     *
     * @var bool
     */
    public bool $addOrderDialog = false;

    /**
     * The dialog's search text.
     *
     * @since 1.0.0
     *
     * @var mixed
     */
    public mixed $addOrderSearch = '';

    /**
     * The order picked in the dialog.
     *
     * @since 1.0.0
     *
     * @var mixed
     */
    public mixed $addOrderId = null;

    /**
     * The column it starts in; empty for the engine's entry column.
     *
     * @since 1.0.0
     *
     * @var mixed
     */
    public mixed $addOrderColumnId = '';

    /**
     * The card waiting for removal confirmation.
     *
     * @since 1.0.0
     *
     * @var int|null
     */
    #[Locked]
    public ?int $removingOrderId = null;

    /**
     * Whether the removal confirmation is open.
     *
     * @since 1.0.0
     *
     * @var bool
     */
    public bool $confirmingRemove = false;

    /**
     * Opens the "Add order" dialog.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function openAddOrder(): void
    {
        $this->authorizeKanban( 'kanbanBoard.update', $this->board );
        $this->resetValidation();

        $this->addOrderSearch   = '';
        $this->addOrderId       = null;
        $this->addOrderColumnId = '';
        $this->addOrderDialog   = true;
    }

    /**
     * Clears the picked order when the search changes.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function updatedAddOrderSearch(): void
    {
        $this->addOrderId = null;
    }

    /**
     * Puts the picked order on the board.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function submitAddOrder(): void
    {
        $this->authorizeKanban( 'kanbanBoard.update', $this->board );

        $columnIds = $this->columns->pluck( 'id' )->map( static fn ( mixed $id ): int => (int) $id )->all();

        $this->validate( [
            'addOrderId'       => [ 'required', 'integer' ],
            'addOrderColumnId' => [ 'nullable', 'integer', 'in:' . implode( ',', $columnIds ) ],
        ], [], [
            'addOrderId'       => __( 'order' ),
            'addOrderColumnId' => __( 'column' ),
        ] );

        $order = Order::query()->find( (int) $this->addOrderId );

        if ( null === $order || ! Authorization::allows( auth()->user(), 'order.view', $order ) ) {
            $this->addError( 'addOrderId', __( 'That order no longer exists.' ) );

            return;
        }

        /** @var KanbanColumn|null $column */
        $column = is_numeric( $this->addOrderColumnId ) ? $this->columns->firstWhere( 'id', (int) $this->addOrderColumnId ) : null;
        $routes = app( KanbanRoutingService::class );
        $label  = $column?->displayLabel() ?? $routes->entryColumn( $this->board, $order )?->displayLabel() ?? '';

        try {
            $routes->assign( $order, $this->board, $column, self::actorId() );
        } catch ( EcommerceException $exception ) {
            $this->error( e( MoveErrors::message( MoveErrors::code( $exception ), $label ) ) );

            return;
        }

        $this->addOrderDialog = false;
        $this->success( e( __( 'Order :number added to this board.', [ 'number' => $order->order_number ] ) ) );
        $this->dispatch( 'ec-kb-focus-card', orderId: (int) $order->id );
    }

    /**
     * Asks before taking a card off the board (the card menu's and the
     * slide-out's "Remove from board").
     *
     * @since 1.0.0
     *
     * @param  int  $orderId  The card's order.
     *
     * @return void
     */
    #[On( CardActions::REMOVE_EVENT )]
    public function confirmRemoveCard( int $orderId ): void
    {
        $this->authorizeKanban( 'kanbanBoard.update', $this->board );

        if ( ! $this->onBoard( $orderId ) ) {
            $this->error( e( MoveErrors::message( 'not-on-board', '' ) ) );

            return;
        }

        $this->removingOrderId  = $orderId;
        $this->confirmingRemove = true;
    }

    /**
     * Drops the pending removal.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function cancelRemoveCard(): void
    {
        $orderId = $this->removingOrderId;

        $this->removingOrderId  = null;
        $this->confirmingRemove = false;

        if ( null !== $orderId ) {
            $this->dispatch( 'ec-kb-focus-card', orderId: $orderId );
        }
    }

    /**
     * Takes the pending card off the board, once per token.
     *
     * @since 1.0.0
     *
     * @param  string  $token  The token minted with the confirmation.
     *
     * @return void
     */
    public function removeCard( string $token ): void
    {
        $this->authorizeKanban( 'kanbanBoard.update', $this->board );

        $orderId = $this->removingOrderId;
        $order   = null === $orderId || ! $this->confirmingRemove ? null : Order::query()->find( $orderId );

        $this->removingOrderId  = null;
        $this->confirmingRemove = false;

        if ( null === $order ) {
            return;
        }

        try {
            $removed = $this->withActionToken( $token, 'remove-card', $this->boardId . ':' . $order->id, fn (): bool => (bool) app( KanbanRoutingService::class )->remove( $order, $this->board, self::actorId() ) );
        } catch ( EcommerceException $exception ) {
            $this->error( e( MoveErrors::message( MoveErrors::code( $exception ), '' ) ) );

            return;
        }

        if ( true === $removed ) {
            $this->success( e( __( 'Order :number removed from this board.', [ 'number' => $order->order_number ] ) ) );
        }
    }

    /**
     * The dialogs' view data.
     *
     * @since 1.0.0
     *
     * @return array<string, mixed>
     */
    protected function assignmentData(): array
    {
        $removing = null === $this->removingOrderId ? null : Order::query()->whereKey( $this->removingOrderId )->value( 'order_number' );

        return [
            'addOrderResults' => $this->addOrderDialog ? $this->addOrderResults() : [],
            'addOrderColumns' => $this->addOrderDialog ? [
                [ 'id' => '', 'name' => __( 'Entry column (automatic)' ) ],
                ...$this->columns->map( static fn ( KanbanColumn $column ): array => [ 'id' => (int) $column->id, 'name' => $column->displayLabel() ] )->all(),
            ] : [],
            'removingNumber'  => null === $removing ? null : (string) $removing,
            'removeToken'     => null === $this->removingOrderId ? null : $this->actionToken( 'remove-card', $this->boardId . ':' . $this->removingOrderId ),
        ];
    }

    /**
     * Orders that aren't on this board matching the dialog's search, newest
     * first, limited to orders the user may view (`order.view`).
     *
     * @since 1.0.0
     *
     * @return array<int, array{id: int, name: string}>
     */
    private function addOrderResults(): array
    {
        $search = is_scalar( $this->addOrderSearch ) ? mb_substr( trim( (string) $this->addOrderSearch ), 0, 100 ) : '';

        if ( '' === $search ) {
            return [];
        }

        $like = '%' . str_replace( [ '!', '%', '_' ], [ '!!', '!%', '!_' ], mb_strtolower( $search ) ) . '%';

        $user = auth()->user();

        return Order::query()
            ->whereNotIn( 'id', OrderBoardAssignment::query()->active()->where( 'board_id', $this->boardId )->select( 'order_id' ) )
            ->where( static function ( $match ) use ( $like ): void {
                $match->orWhereRaw( "LOWER(order_number) LIKE ? ESCAPE '!'", [ $like ] )
                    ->orWhereRaw( "LOWER(email) LIKE ? ESCAPE '!'", [ $like ] );
            } )
            ->orderByDesc( 'id' )
            ->limit( 50 )
            ->get()
            ->filter( static fn ( Order $order ): bool => Authorization::allows( $user, 'order.view', $order ) )
            ->take( 10 )
            ->map( static fn ( Order $order ): array => [
                'id'   => (int) $order->id,
                'name' => __( 'Order :number — :email', [ 'number' => $order->order_number, 'email' => $order->email ] ),
            ] )
            ->values()
            ->all();
    }

    /**
     * Whether `$orderId` has a card on this board.
     *
     * @since 1.0.0
     *
     * @param  int  $orderId  The order.
     *
     * @return bool
     */
    private function onBoard( int $orderId ): bool
    {
        return OrderBoardAssignment::query()->active()->where( 'board_id', $this->boardId )->where( 'order_id', $orderId )->exists();
    }
}
