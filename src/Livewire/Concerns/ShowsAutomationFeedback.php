<?php

/**
 * Automation feedback after a move.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceKanbanLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceKanbanLivewire\Livewire\Concerns;

use ArtisanPackUI\Ecommerce\Models\KanbanAutomation;
use ArtisanPackUI\Ecommerce\Models\Order;
use ArtisanPackUI\Ecommerce\Models\OrderBoardAssignment;
use ArtisanPackUI\Ecommerce\Models\OrderTimelineEntry;
use ArtisanPackUI\EcommerceKanbanLivewire\Support\Automations;
use ArtisanPackUI\EcommerceKanbanLivewire\Support\BoardUrls;
use ArtisanPackUI\EcommerceKanbanLivewire\Support\SafeUrl;
use Livewire\Attributes\Renderless;

/**
 * Tells the person who moved a card which automations ran or failed for it
 * (spec §7.10), in one toast per move however many ran.
 *
 * The engine runs automations in the move's request and writes a
 * `kanban.automation_fired` or `kanban.automation_failed` timeline entry
 * for each, so {@see self::automationFeedbackSince()} reads the entries the
 * move wrote. When the page is live over Echo it sets `liveFeedback`, the
 * move skips that read, and the page batches the engine's
 * `automation-fired` / `automation-failed` broadcasts for the cards this
 * browser moved and sends them to {@see self::automationFeedback()}.
 * Either way only the actor sees the toast.
 *
 * A failure toast names each failed trigger and links to the order's
 * slide-out.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceKanbanLivewire
 *
 * @since      1.0.0
 */
trait ShowsAutomationFeedback
{
    /**
     * Whether the page gets automation feedback from broadcasts; set by the
     * page while Echo is connected. It only changes which source this
     * browser's own toasts come from.
     *
     * @since 1.0.0
     *
     * @var bool
     */
    public bool $liveFeedback = false;

    /**
     * Toasts the automations the engine broadcast as fired or failed for a
     * card this browser moved, without rendering the board. Ids of
     * automations on other boards are ignored, as are orders that were
     * never on this board.
     *
     * @since 1.0.0
     *
     * @param  int                $orderId  The moved card's order.
     * @param  array<int, mixed>  $fired    Ids of automations that ran.
     * @param  array<int, mixed>  $failed   Ids of automations that failed.
     *
     * @return void
     */
    #[Renderless]
    public function automationFeedback( int $orderId, array $fired = [], array $failed = [] ): void
    {
        $ids    = static fn ( array $values ): array => array_slice( array_values( array_unique( array_map( 'intval', array_filter( $values, 'is_numeric' ) ) ) ), 0, 50 );
        $fired  = $ids( $fired );
        $failed = $ids( $failed );

        if ( [] === $fired && [] === $failed ) {
            return;
        }

        $order = Order::query()->find( $orderId );

        if ( null === $order || ! OrderBoardAssignment::query()->where( 'board_id', $this->boardId )->where( 'order_id', $orderId )->exists() ) {
            return;
        }

        $triggers = KanbanAutomation::query()
            ->where( 'board_id', $this->boardId )
            ->whereIn( 'id', [ ...$fired, ...$failed ] )
            ->pluck( 'trigger_key', 'id' );

        $keys = static fn ( array $list ): array => array_values( array_filter( array_map( static fn ( int $id ): ?string => isset( $triggers[ $id ] ) ? (string) $triggers[ $id ] : null, $list ) ) );

        $this->toastAutomations( $order, $keys( $fired ), $keys( $failed ) );
    }

    /**
     * Where to start reading timeline entries for a move: the latest entry
     * id now, or null when the page gets feedback from broadcasts.
     *
     * @since 1.0.0
     *
     * @return int|null
     */
    protected function automationFeedbackStart(): ?int
    {
        return $this->liveFeedback ? null : (int) OrderTimelineEntry::query()->max( 'id' );
    }

    /**
     * Toasts the automations a move ran, from the timeline entries written
     * after `$since` for this board.
     *
     * @since 1.0.0
     *
     * @param  Order     $order  The moved card's order.
     * @param  int|null  $since  {@see self::automationFeedbackStart()}.
     *
     * @return void
     */
    protected function automationFeedbackSince( Order $order, ?int $since ): void
    {
        if ( null === $since ) {
            return;
        }

        $fired  = [];
        $failed = [];

        $entries = OrderTimelineEntry::query()
            ->where( 'order_id', $order->id )
            ->where( 'id', '>', $since )
            ->whereIn( 'event_type', [ 'kanban.automation_fired', 'kanban.automation_failed' ] )
            ->orderBy( 'id' )
            ->get( [ 'id', 'event_type', 'payload' ] );

        foreach ( $entries as $entry ) {
            $payload = (array) $entry->payload;

            if ( (int) ( $payload['board_id'] ?? 0 ) !== $this->boardId ) {
                continue;
            }

            $key = (string) ( $payload['trigger_key'] ?? '' );

            if ( 'kanban.automation_failed' === $entry->event_type ) {
                $failed[] = $key;
            } else {
                $fired[] = $key;
            }
        }

        $this->toastAutomations( $order, $fired, $failed );
    }

    /**
     * One toast for a move's automations: an error naming each failed
     * trigger (and how many others ran) with a link to the order, or a
     * success naming those that ran.
     *
     * @since 1.0.0
     *
     * @param  Order               $order   The moved card's order.
     * @param  array<int, string>  $fired   Trigger keys that ran.
     * @param  array<int, string>  $failed  Trigger keys that failed.
     *
     * @return void
     */
    private function toastAutomations( Order $order, array $fired, array $failed ): void
    {
        $number = (string) $order->order_number;
        $labels = static fn ( array $keys ): string => implode( ', ', array_map( static fn ( string $key ): string => Automations::triggerLabel( $key ), $keys ) );

        // The toast renders its title and description as HTML.
        if ( [] !== $failed ) {
            $description = e( __( 'Failed: :triggers.', [ 'triggers' => $labels( $failed ) ] ) );

            if ( [] !== $fired ) {
                $description .= ' ' . e( trans_choice( ':count other automation ran.|:count other automations ran.', count( $fired ), [ 'count' => count( $fired ) ] ) );
            }

            $url = SafeUrl::of( BoardUrls::show( $this->board, [ 'card' => $order->id ] ) );

            if ( null !== $url ) {
                $description .= ' <a class="link" href="' . e( $url ) . '" data-ec-kb-automation-order>' . e( __( 'Open order :number', [ 'number' => $number ] ) ) . '</a>';
            }

            $this->error(
                e( trans_choice( ':count automation failed for order :number|:count automations failed for order :number', count( $failed ), [ 'count' => count( $failed ), 'number' => $number ] ) ),
                $description,
                duration: 10000,
            );

            return;
        }

        if ( [] !== $fired ) {
            $this->success(
                e( trans_choice( ':count automation ran for order :number|:count automations ran for order :number', count( $fired ), [ 'count' => count( $fired ), 'number' => $number ] ) ),
                e( $labels( $fired ) ),
            );
        }
    }
}
