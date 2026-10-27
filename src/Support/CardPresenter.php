<?php

/**
 * Card presenter.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceKanbanLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceKanbanLivewire\Support;

use ArtisanPackUI\Ecommerce\Kanban\KanbanCardRenderer;
use ArtisanPackUI\Ecommerce\Kanban\Widgets\CustomerWidget;
use ArtisanPackUI\Ecommerce\Models\KanbanColumn;
use ArtisanPackUI\Ecommerce\Models\Order;
use ArtisanPackUI\Ecommerce\Models\OrderBoardAssignment;
use ArtisanPackUI\Ecommerce\Support\LocalizedDate;
use Throwable;

/**
 * Turns a card (an order's assignment to a board) into what
 * `x-artisanpack-ec-kb-card` renders (spec §7.2).
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceKanbanLivewire
 *
 * @since      1.0.0
 */
final class CardPresenter
{
    /**
     * @since 1.0.0
     *
     * @param  KanbanCardRenderer  $renderer  The engine's widget renderer.
     */
    public function __construct( private readonly KanbanCardRenderer $renderer )
    {
    }

    /**
     * The card's display data.
     *
     * The engine already skips a widget that throws; a renderer failure as a
     * whole is reported and leaves the card without widgets rather than
     * breaking the column.
     *
     * @since 1.0.0
     *
     * @param  OrderBoardAssignment  $card    The card (its `order` loaded).
     * @param  KanbanColumn          $column  The column it is in.
     *
     * @return array{order_id: int, column_id: int, number: string, customer: string, placed_at: string, placed_at_iso: string|null, widgets: array<int, array<string, string|null>>, label: string}
     */
    public function present( OrderBoardAssignment $card, KanbanColumn $column ): array
    {
        $order = $card->order;

        try {
            $widgets = $this->renderer->render( $order, $column );
        } catch ( Throwable $exception ) {
            report( $exception );
            $widgets = [];
        }

        $customer = self::customerName( $order );

        // The card names the customer itself, so the engine's customer
        // widget would only repeat it.
        $widgets = array_values( array_filter(
            $widgets,
            static fn ( array $widget ): bool => ! ( CustomerWidget::KEY === ( $widget['key'] ?? null ) && ( $widget['value'] ?? null ) === $customer ),
        ) );

        $number = (string) $order->order_number;

        return [
            'order_id'      => (int) $order->id,
            'column_id'     => (int) $column->id,
            'number'        => $number,
            'customer'      => $customer,
            'placed_at'     => LocalizedDate::format( $order->placed_at ),
            'placed_at_iso' => $order->placed_at?->toIso8601String(),
            'widgets'       => $widgets,
            'label'         => self::label( $number, $customer, $widgets ),
        ];
    }

    /**
     * The customer's name from the billing (else shipping) address, else
     * the order email.
     *
     * @since 1.0.0
     *
     * @param  Order  $order  The order.
     *
     * @return string
     */
    public static function customerName( Order $order ): string
    {
        $address = (array) ( $order->billing_address ?? [] ) ?: (array) ( $order->shipping_address ?? [] );
        $name    = trim( ( (string) ( $address['first_name'] ?? '' ) ) . ' ' . ( (string) ( $address['last_name'] ?? '' ) ) );

        return '' !== $name ? $name : (string) $order->email;
    }

    /**
     * The card's accessible name: "Order 1042, Jane Doe, 3 items, $124.00".
     *
     * @since 1.0.0
     *
     * @param  string                                    $number    Order number.
     * @param  string                                    $customer  Customer name.
     * @param  array<int, array<string, string|null>>  $widgets   Widget payloads.
     *
     * @return string
     */
    private static function label( string $number, string $customer, array $widgets ): string
    {
        $parts = [ __( 'Order :number', [ 'number' => $number ] ), $customer ];

        foreach ( $widgets as $widget ) {
            $value = trim( (string) ( $widget['value'] ?? '' ) );

            if ( '' !== $value ) {
                $parts[] = $value;
            }
        }

        return implode( ', ', array_filter( $parts, static fn ( string $part ): bool => '' !== $part ) );
    }
}
