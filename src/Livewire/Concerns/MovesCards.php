<?php

/**
 * Card moves.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceKanbanLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceKanbanLivewire\Livewire\Concerns;

use ArtisanPackUI\Ecommerce\Exceptions\EcommerceException;
use ArtisanPackUI\Ecommerce\Kanban\KanbanCardPlacement;
use ArtisanPackUI\Ecommerce\Models\KanbanColumn;
use ArtisanPackUI\Ecommerce\Models\Order;
use ArtisanPackUI\Ecommerce\Models\OrderBoardAssignment;
use ArtisanPackUI\Ecommerce\Services\KanbanBoardService;
use ArtisanPackUI\EcommerceKanbanLivewire\Queries\ColumnCardsQuery;
use ArtisanPackUI\EcommerceKanbanLivewire\Support\CardActions;
use ArtisanPackUI\EcommerceKanbanLivewire\Support\Hooks;
use ArtisanPackUI\EcommerceKanbanLivewire\Support\MoveErrors;
use ArtisanPackUI\EcommerceKanbanLivewire\Support\Statuses;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;

/**
 * Moving cards (spec §7.3, §7.4): a drop between or within columns, and the
 * "Move to…" dialog, both through {@see self::moveCard()}'s engine call so
 * WIP limits, the status roll-up, hooks, automations, and broadcasting all
 * apply.
 *
 * A drop is optimistic: the card stays where it was dropped while the
 * request runs. When it goes through, only its two columns re-render
 * (`ec-kb-columns-rendered`). When the engine refuses,
 * {@see self::moveCard()} toasts why, runs
 * `ap.ecommerceKanbanLivewire.cardMoveFailed`, skips the re-render, and
 * returns {@see self::REJECTED}, and the client reverts that move. A move
 * into an exit column (cancelled, refunded, failed) changes the whole
 * order's status, so it asks first.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceKanbanLivewire
 *
 * @since      1.0.0
 */
trait MovesCards
{
    /**
     * The move went through.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public const MOVED = 'moved';

    /**
     * The move was refused; the client reverts it.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public const REJECTED = 'rejected';

    /**
     * The move waits for confirmation.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public const CONFIRM = 'confirm';

    /**
     * A move into an exit column waiting for confirmation: `order_id`,
     * `column_id`, and the placement as an array.
     *
     * @since 1.0.0
     *
     * @var array{order_id: int, column_id: int, placement: array{mode: string, order_id: int|null}|null}|null
     */
    #[Locked]
    public ?array $pendingMove = null;

    /**
     * Whether the exit-column confirmation is open.
     *
     * @since 1.0.0
     *
     * @var bool
     */
    public bool $confirmingMove = false;

    /**
     * Whether the "Move to…" dialog is open.
     *
     * @since 1.0.0
     *
     * @var bool
     */
    public bool $moveDialog = false;

    /**
     * The order the "Move to…" dialog moves.
     *
     * @since 1.0.0
     *
     * @var int|null
     */
    #[Locked]
    public ?int $moveOrderId = null;

    /**
     * The dialog's chosen column.
     *
     * @since 1.0.0
     *
     * @var mixed
     */
    public mixed $moveColumnId = null;

    /**
     * The dialog's chosen position: `top`, `bottom`, or `after`.
     *
     * @since 1.0.0
     *
     * @var mixed
     */
    public mixed $movePosition = KanbanCardPlacement::TOP;

    /**
     * The card the dialog places the order after, for `after`.
     *
     * @since 1.0.0
     *
     * @var mixed
     */
    public mixed $moveAfterOrderId = null;

    /**
     * Moves `$orderId`'s card into `$toColumnId`, between the cards it was
     * dropped between: just after `$afterOrderId` when given, else just
     * before `$beforeOrderId`, else at the bottom (or in place, for a drop
     * back into the card's own column).
     *
     * @since 1.0.0
     *
     * @param  int       $orderId        The card's order.
     * @param  int       $toColumnId     The column it was dropped in.
     * @param  int|null  $beforeOrderId  The card below the drop.
     * @param  int|null  $afterOrderId   The card above the drop.
     *
     * @return string `moved`, `rejected` (revert the drop), or `confirm`.
     */
    public function moveCard( int $orderId, int $toColumnId, ?int $beforeOrderId = null, ?int $afterOrderId = null ): string
    {
        $placement = match ( true ) {
            null !== $afterOrderId  => KanbanCardPlacement::after( $afterOrderId ),
            null !== $beforeOrderId => KanbanCardPlacement::before( $beforeOrderId ),
            default                 => null,
        };

        return $this->performMove( $orderId, $toColumnId, $placement, partial: true );
    }

    /**
     * Makes the move waiting for confirmation.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function confirmMove(): void
    {
        $pending = $this->pendingMove;

        $this->pendingMove    = null;
        $this->confirmingMove = false;

        if ( null !== $pending ) {
            $this->performMove( (int) $pending['order_id'], (int) $pending['column_id'], KanbanCardPlacement::fromArray( $pending['placement'] ), true );
        }
    }

    /**
     * Drops the move waiting for confirmation.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function cancelMove(): void
    {
        $orderId = $this->pendingMove['order_id'] ?? null;

        $this->pendingMove    = null;
        $this->confirmingMove = false;

        if ( null !== $orderId ) {
            $this->dispatch( 'ec-kb-focus-card', orderId: (int) $orderId );
        }
    }

    /**
     * Opens the "Move to…" dialog for a card (the card menu's action).
     *
     * @since 1.0.0
     *
     * @param  int  $orderId  The card's order.
     *
     * @return void
     */
    #[On( CardActions::MOVE_EVENT )]
    public function openMoveDialog( int $orderId ): void
    {
        $this->authorizeKanban( 'kanbanCard.move', $this->board );

        $column = $this->cardColumn( $orderId );

        if ( null === $column ) {
            $this->error( e( MoveErrors::message( 'not-on-board', '' ) ) );

            return;
        }

        $this->resetValidation();

        $this->moveOrderId      = $orderId;
        $this->moveColumnId     = (int) $column->id;
        $this->movePosition     = KanbanCardPlacement::TOP;
        $this->moveAfterOrderId = null;
        $this->moveDialog       = true;
    }

    /**
     * Resets the dialog's "after" card when the column changes.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function updatedMoveColumnId(): void
    {
        $this->moveAfterOrderId = null;
    }

    /**
     * Moves the dialog's card to the chosen column and position.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function submitMove(): void
    {
        $columnIds = $this->columns->pluck( 'id' )->map( static fn ( mixed $id ): int => (int) $id )->all();

        $this->validate( [
            'moveColumnId'     => [ 'required', 'integer', 'in:' . implode( ',', $columnIds ) ],
            'movePosition'     => [ 'required', 'in:top,bottom,after' ],
            'moveAfterOrderId' => [ 'required_if:movePosition,after', 'nullable', 'integer' ],
        ], [], [
            'moveColumnId'     => __( 'column' ),
            'movePosition'     => __( 'position' ),
            'moveAfterOrderId' => __( 'card' ),
        ] );

        if ( null === $this->moveOrderId ) {
            $this->moveDialog = false;

            return;
        }

        $placement = KanbanCardPlacement::AFTER === $this->movePosition
            ? KanbanCardPlacement::after( (int) $this->moveAfterOrderId )
            : KanbanCardPlacement::end( $this->movePosition );

        $result = $this->performMove( $this->moveOrderId, (int) $this->moveColumnId, $placement );

        if ( self::REJECTED !== $result ) {
            $this->moveDialog = false;
        }
    }

    /**
     * The dialog's view data: column options (current column and full
     * columns flagged) and the cards of the chosen column to place after.
     *
     * @since 1.0.0
     *
     * @param  array<int, array{count: int, total: int}>  $counts  Column counts.
     *
     * @return array<string, mixed>
     */
    protected function moveDialogData( array $counts ): array
    {
        if ( ! $this->moveDialog || null === $this->moveOrderId ) {
            return [ 'moveColumns' => [], 'moveCards' => [], 'moveNumber' => null ];
        }

        $current = $this->cardColumn( $this->moveOrderId );
        $counts  = [] === $counts ? ColumnCardsQuery::counts( $this->board, $this->columns ) : $counts;
        $options = [];

        foreach ( $this->columns as $column ) {
            $id     = (int) $column->id;
            $total  = $counts[ $id ]['total'] ?? 0;
            $full   = null !== $column->wip_limit && $column->wip_limit > 0 && $total >= $column->wip_limit;
            $isHere = null !== $current && $id === (int) $current->id;
            $name   = match ( true ) {
                $isHere => __( ':column (current column)', [ 'column' => $column->displayLabel() ] ),
                $full   => __( ':column (at its WIP limit)', [ 'column' => $column->displayLabel() ] ),
                default => $column->displayLabel(),
            };

            $options[] = [ 'id' => $id, 'name' => $name, 'disabled' => $full && ! $isHere ];
        }

        $target = $this->columns->firstWhere( 'id', (int) $this->moveColumnId );
        $cards  = [];

        if ( $target instanceof KanbanColumn ) {
            foreach ( ColumnCardsQuery::cards( $target, 50 ) as $card ) {
                if ( (int) $card->order_id !== $this->moveOrderId ) {
                    $cards[] = [ 'id' => (int) $card->order_id, 'name' => __( 'Order :number', [ 'number' => $card->order->order_number ] ) ];
                }
            }
        }

        return [
            'moveColumns' => $options,
            'moveCards'   => $cards,
            'moveNumber'  => Order::query()->whereKey( $this->moveOrderId )->value( 'order_number' ),
        ];
    }

    /**
     * The confirmation's view data.
     *
     * @since 1.0.0
     *
     * @return array<string, mixed>
     */
    protected function confirmMoveData(): array
    {
        if ( null === $this->pendingMove ) {
            return [ 'pending' => null ];
        }

        $column = $this->columns->firstWhere( 'id', (int) $this->pendingMove['column_id'] );

        return [
            'pending' => [
                'number' => (string) Order::query()->whereKey( $this->pendingMove['order_id'] )->value( 'order_number' ),
                'column' => $column?->displayLabel() ?? '',
                'status' => Statuses::label( 'system', $column?->substatus?->system_status ),
            ],
        ];
    }

    /**
     * Runs a move through the engine.
     *
     * @since 1.0.0
     *
     * @param  int                       $orderId     The card's order.
     * @param  int                       $toColumnId  Target column.
     * @param  KanbanCardPlacement|null  $placement   Where it lands.
     * @param  bool                      $confirmed   Whether an exit-column move was confirmed.
     * @param  bool                      $partial     Re-render only the two columns (a drop) rather than the board.
     *
     * @return string
     */
    private function performMove( int $orderId, int $toColumnId, ?KanbanCardPlacement $placement, bool $confirmed = false, bool $partial = false ): string
    {
        $this->authorizeKanban( 'kanbanCard.move', $this->board );

        /** @var KanbanColumn|null $column */
        $column = $this->columns->firstWhere( 'id', $toColumnId );
        $order  = Order::query()->find( $orderId );

        if ( null === $column ) {
            return $this->refuse( 'column-not-on-board', null, $order );
        }

        if ( null === $order ) {
            return $this->refuse( 'not-on-board', $column );
        }

        $current = $this->cardColumn( $orderId );

        if ( ! $confirmed && in_array( $column->substatus?->system_status, Statuses::EXIT, true ) && (int) $current?->id !== (int) $column->id ) {
            $this->pendingMove    = [ 'order_id' => $orderId, 'column_id' => (int) $column->id, 'placement' => $placement?->toArray() ];
            $this->confirmingMove = true;

            return self::CONFIRM;
        }

        $since = $this->automationFeedbackStart();

        try {
            app( KanbanBoardService::class )->move( $order, $column, self::actorId(), null, $placement );
        } catch ( EcommerceException $exception ) {
            return $this->refuse( MoveErrors::code( $exception ), $column, $order );
        }

        $this->automationFeedbackSince( $order, $since );

        if ( $partial ) {
            $this->renderMovedColumns( array_values( array_unique( array_filter( [ (int) $current?->id, (int) $column->id ] ) ) ) );
        }

        $this->dispatch( 'ec-kb-card-moved', orderId: $orderId );
        $this->dispatch( 'ec-kb-focus-card', orderId: $orderId );

        return self::MOVED;
    }

    /**
     * Re-renders just the columns a drop changed: their HTML goes to the
     * page in `ec-kb-columns-rendered` and the board render is skipped. In
     * "Edit board" mode the board renders as usual.
     *
     * @since 1.0.0
     *
     * @param  array<int, int>  $columnIds  The source and target columns.
     *
     * @return void
     */
    private function renderMovedColumns( array $columnIds ): void
    {
        $result = $this->columnsHtml( $columnIds );

        if ( $result['refresh'] ) {
            return;
        }

        $this->skipRender();
        $this->dispatch( 'ec-kb-columns-rendered', columns: $result['columns'] );
    }

    /**
     * Toasts why a move was refused and leaves the board as the client has
     * it, so the client can revert just that move.
     *
     * @since 1.0.0
     *
     * @param  string             $code    Refusal code.
     * @param  KanbanColumn|null  $column  Target column, when it is on this board.
     * @param  Order|null         $order   The order, when it exists.
     *
     * @return string
     */
    private function refuse( string $code, ?KanbanColumn $column, ?Order $order = null ): string
    {
        doAction( Hooks::CARD_MOVE_FAILED, $code, $order, $column, $this->board, auth()->user() );

        // The toast renders its title as HTML; column labels are admin-entered.
        $this->error( e( MoveErrors::message( $code, $column?->displayLabel() ?? '' ) ) );
        $this->skipRender();

        return self::REJECTED;
    }

    /**
     * The column `$orderId`'s card is in on this board, or null when the
     * order isn't on it.
     *
     * @since 1.0.0
     *
     * @param  int  $orderId  Order.
     *
     * @return KanbanColumn|null
     */
    private function cardColumn( int $orderId ): ?KanbanColumn
    {
        $substatusId = OrderBoardAssignment::query()
            ->active()
            ->where( 'board_id', $this->boardId )
            ->where( 'order_id', $orderId )
            ->value( 'substatus_id' );

        return null === $substatusId ? null : $this->columns->firstWhere( 'substatus_id', (int) $substatusId );
    }

    /**
     * The acting user's id.
     *
     * @since 1.0.0
     *
     * @return int|null
     */
    private static function actorId(): ?int
    {
        $id = auth()->id();

        return is_numeric( $id ) ? (int) $id : null;
    }
}
