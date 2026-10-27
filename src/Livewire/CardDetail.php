<?php

/**
 * Card detail slide-out.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceKanbanLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceKanbanLivewire\Livewire;

use ArtisanPack\LivewireUiComponents\Traits\Toast;
use ArtisanPackUI\Ecommerce\Models\KanbanBoard;
use ArtisanPackUI\Ecommerce\Models\Order;
use ArtisanPackUI\Ecommerce\Models\OrderBoardAssignment;
use ArtisanPackUI\EcommerceKanbanLivewire\Livewire\Concerns\AuthorizesKanban;
use ArtisanPackUI\EcommerceKanbanLivewire\Support\Authorization;
use ArtisanPackUI\EcommerceKanbanLivewire\Support\CardActions;
use ArtisanPackUI\EcommerceKanbanLivewire\Support\CardDetailPresenter;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * The card slide-out (spec §7.6): a right-hand drawer with a mini order view
 * — status and column, customer and contact, shipping address, lines,
 * totals, payment and fulfillment, the order's other boards, and its latest
 * timeline entries — plus "Move to…" and "Open in admin".
 *
 * The board renders this component once. It loads nothing until a card is
 * opened (the card's "Open" action, or `?card={order}` on the board URL,
 * which keeps an open card linkable). Opening needs `order.view`, and the
 * order must be on the board.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceKanbanLivewire
 *
 * @since      1.0.0
 */
class CardDetail extends Component
{
    use AuthorizesKanban;
    use Toast;

    /**
     * The board's id.
     *
     * @since 1.0.0
     *
     * @var int
     */
    #[Locked]
    public int $boardId;

    /**
     * The open card's order id (`?card=` in the URL).
     *
     * @since 1.0.0
     *
     * @var mixed
     */
    #[Url( as: 'card', except: null )]
    public mixed $orderId = null;

    /**
     * Whether the drawer is open.
     *
     * @since 1.0.0
     *
     * @var bool
     */
    public bool $open = false;

    /**
     * Mounts the slide-out, opening the card named in the URL.
     *
     * @since 1.0.0
     *
     * @param  int  $boardId  The board's id.
     *
     * @return void
     */
    public function mount( int $boardId ): void
    {
        $this->boardId = $boardId;

        $this->authorizeKanban( 'kanbanBoard.view', $this->board() );

        if ( null !== $this->orderId ) {
            $this->show( $this->orderId );
        }
    }

    /**
     * Re-checks access to the board on every update.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function hydrate(): void
    {
        $this->authorizeKanban( 'kanbanBoard.view', $this->board() );
    }

    /**
     * Opens a card (the card menu's "Open" action).
     *
     * @since 1.0.0
     *
     * @param  mixed  $orderId  The card's order (from the event or the URL).
     *
     * @return void
     */
    #[On( CardActions::OPEN_EVENT )]
    public function show( mixed $orderId ): void
    {
        $order = is_numeric( $orderId ) ? $this->loadOrder( (int) $orderId ) : null;

        if ( null === $order ) {
            $this->close();

            return;
        }

        $this->orderId = (int) $order->id;
        $this->open    = true;
    }

    /**
     * Closes the drawer and hands focus back to the card.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function close(): void
    {
        $orderId = $this->orderId;

        $this->orderId = null;
        $this->open    = false;

        if ( is_numeric( $orderId ) ) {
            $this->dispatch( 'ec-kb-focus-card', orderId: (int) $orderId );
        }
    }

    /**
     * Closes the drawer when it is dismissed (overlay click, Escape).
     *
     * @since 1.0.0
     *
     * @param  bool  $value  The new open state.
     *
     * @return void
     */
    public function updatedOpen( bool $value ): void
    {
        if ( ! $value ) {
            $this->close();
        }
    }

    /**
     * Closes the drawer and opens the board's "Move to…" dialog for the card.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function moveTo(): void
    {
        $orderId = $this->orderId;

        $this->orderId = null;
        $this->open    = false;

        if ( is_numeric( $orderId ) ) {
            $this->dispatch( CardActions::MOVE_EVENT, orderId: (int) $orderId );
        }
    }

    /**
     * Renders the drawer.
     *
     * @since 1.0.0
     *
     * @return View
     */
    public function render(): View
    {
        $order  = $this->open && is_numeric( $this->orderId ) ? $this->loadOrder( (int) $this->orderId, false ) : null;
        $board  = $this->board();
        $detail = null === $order ? null : CardDetailPresenter::present( $order, $board );

        return view( 'ecommerce-kanban::livewire.card-detail', [
            'detail'  => $detail,
            'canMove' => null !== $detail && Authorization::allows( auth()->user(), 'kanbanCard.move', $board ),
        ] );
    }

    /**
     * The board.
     *
     * @since 1.0.0
     *
     * @return KanbanBoard
     */
    private function board(): KanbanBoard
    {
        return KanbanBoard::query()->findOrFail( $this->boardId );
    }

    /**
     * The order, when it is on the board and the user may view it; toasts
     * why not otherwise.
     *
     * @since 1.0.0
     *
     * @param  int   $orderId  The order.
     * @param  bool  $notify   Toast when the order can't be shown.
     *
     * @return Order|null
     */
    private function loadOrder( int $orderId, bool $notify = true ): ?Order
    {
        $order   = Order::query()->with( 'items.product' )->find( $orderId );
        $onBoard = null !== $order && OrderBoardAssignment::query()->active()->where( 'board_id', $this->boardId )->where( 'order_id', $order->id )->exists();

        if ( ! $onBoard ) {
            if ( $notify ) {
                $this->error( e( __( 'This order is not on this board.' ) ) );
            }

            return null;
        }

        if ( ! Authorization::allows( auth()->user(), 'order.view', $order ) ) {
            if ( $notify ) {
                $this->error( e( __( 'You are not allowed to view this order.' ) ) );
            }

            return null;
        }

        return $order;
    }
}
