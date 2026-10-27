<?php

/**
 * Card detail presenter.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceKanbanLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceKanbanLivewire\Support;

use ArtisanPackUI\Ecommerce\Models\KanbanBoard;
use ArtisanPackUI\Ecommerce\Models\KanbanColumn;
use ArtisanPackUI\Ecommerce\Models\Order;
use ArtisanPackUI\Ecommerce\Models\OrderBoardAssignment;
use ArtisanPackUI\Ecommerce\Models\OrderItem;
use ArtisanPackUI\Ecommerce\Models\OrderSubstatus;
use ArtisanPackUI\Ecommerce\Models\OrderTimelineEntry;
use ArtisanPackUI\Ecommerce\Support\LocalizedDate;
use ArtisanPackUI\Ecommerce\Support\MoneyFormatter;
use Illuminate\Support\Str;

/**
 * The card slide-out's view data (spec §7.6): a mini order view built from
 * the engine's order, its lines, its board assignments, and its timeline.
 * Contact details show to anyone with `order.view`, as on the admin's order
 * screen.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceKanbanLivewire
 *
 * @since      1.0.0
 */
final class CardDetailPresenter
{
    /**
     * Timeline entries shown.
     *
     * @since 1.0.0
     *
     * @var int
     */
    public const TIMELINE_ENTRIES = 5;

    /**
     * `$order`'s detail as seen from `$board`.
     *
     * @since 1.0.0
     *
     * @param  Order        $order  The order (with `items` loaded).
     * @param  KanbanBoard  $board  The board the slide-out is on.
     *
     * @return array<string, mixed>
     */
    public static function present( Order $order, KanbanBoard $board ): array
    {
        $currency    = (string) $order->currency;
        $money       = static fn ( mixed $amount ): string => MoneyFormatter::format( (int) $amount, $currency );
        $assignments = OrderBoardAssignment::query()->active()->where( 'order_id', $order->id )->with( [ 'board', 'substatus' ] )->get();
        $columns     = KanbanColumn::query()->whereIn( 'board_id', $assignments->pluck( 'board_id' ) )->get();
        $column      = static fn ( OrderBoardAssignment $card ): string => $columns
            ->first( static fn ( KanbanColumn $column ): bool => (int) $column->board_id === (int) $card->board_id && (int) $column->substatus_id === (int) $card->substatus_id )
            ?->displayLabel() ?? (string) $card->substatus?->label;
        $here        = $assignments->firstWhere( 'board_id', $board->id );

        return [
            'order_id'    => (int) $order->id,
            'number'      => (string) $order->order_number,
            'status'      => Statuses::label( 'system', $order->system_status ),
            'column'      => null === $here ? null : $column( $here ),
            'column_id'   => null === $here ? null : $columns->first( static fn ( KanbanColumn $c ): bool => (int) $c->board_id === (int) $board->id && (int) $c->substatus_id === (int) $here->substatus_id )?->id,
            'customer'    => CardPresenter::customerName( $order ),
            'email'       => (string) $order->email,
            'phone'       => filled( $order->phone ) ? (string) $order->phone : null,
            'placed_at'   => LocalizedDate::format( $order->placed_at ),
            'shipping'    => self::address( (array) ( $order->shipping_address ?? [] ) ),
            'lines'       => $order->items->map( static fn ( OrderItem $item ): array => [
                'name'     => (string) ( $item->product_snapshot['name'] ?? $item->product?->name ?? __( 'Deleted product' ) ),
                'variant'  => is_string( $item->product_snapshot['variant_name'] ?? null ) ? $item->product_snapshot['variant_name'] : null,
                'sku'      => is_string( $item->product_snapshot['sku'] ?? null ) ? $item->product_snapshot['sku'] : null,
                'quantity' => (int) $item->quantity,
                'total'    => $money( $item->total_amount ),
            ] )->all(),
            'totals'      => array_values( array_filter( [
                [ 'label' => __( 'Subtotal' ), 'value' => $money( $order->subtotal_amount ) ],
                (int) $order->discount_amount > 0 ? [ 'label' => __( 'Discount' ), 'value' => '−' . $money( $order->discount_amount ) ] : null,
                [ 'label' => __( 'Shipping' ), 'value' => $money( $order->shipping_amount ) ],
                [ 'label' => __( 'Tax' ), 'value' => $money( $order->tax_amount ) ],
                [ 'label' => __( 'Total' ), 'value' => $money( $order->total_amount ), 'strong' => true ],
                (int) $order->total_refunded_amount > 0 ? [ 'label' => __( 'Refunded' ), 'value' => $money( $order->total_refunded_amount ) ] : null,
            ] ) ),
            'payment'     => Statuses::label( 'payment', $order->payment_status ),
            'fulfillment' => Statuses::label( 'fulfillment', $order->fulfillment_status ),
            'boards'      => $assignments
                ->reject( static fn ( OrderBoardAssignment $card ): bool => (int) $card->board_id === (int) $board->id || null === $card->board )
                ->map( static fn ( OrderBoardAssignment $card ): array => [ 'board' => (string) $card->board->name, 'column' => $column( $card ) ] )
                ->values()
                ->all(),
            'timeline'    => self::timeline( $order ),
            'admin_url'   => AdminLinks::orderUrl( (int) $order->id ),
        ];
    }

    /**
     * The order's latest timeline entries.
     *
     * @since 1.0.0
     *
     * @param  Order  $order  The order.
     *
     * @return array<int, array{id: int, text: string, date: string, date_iso: string|null}>
     */
    public static function timeline( Order $order ): array
    {
        $entries = OrderTimelineEntry::query()->where( 'order_id', $order->id )->latest( 'id' )->limit( self::TIMELINE_ENTRIES )->get();
        $ids     = $entries->map( static fn ( OrderTimelineEntry $entry ): mixed => $entry->payload['to_id'] ?? null )->filter( static fn ( mixed $id ): bool => is_numeric( $id ) )->all();
        $labels  = OrderSubstatus::query()->whereKey( $ids )->pluck( 'label', 'id' );
        $columns = KanbanColumn::query()->whereIn( 'substatus_id', $ids )->with( 'substatus' )->get()
            ->keyBy( static fn ( KanbanColumn $column ): string => $column->board_id . ':' . $column->substatus_id );

        return $entries->map( static function ( OrderTimelineEntry $entry ) use ( $labels, $columns ): array {
            $payload = (array) $entry->payload;
            $to      = $payload['to_id'] ?? null;
            $column  = $columns->get( ( $payload['board_id'] ?? '' ) . ':' . $to );

            return [
                'id'       => (int) $entry->id,
                'text'     => self::describe( (string) $entry->event_type, [ ...$payload, 'to_label' => $column?->displayLabel() ?? $labels[ $to ] ?? null ] ),
                'date'     => LocalizedDate::format( $entry->created_at ),
                'date_iso' => $entry->created_at?->toIso8601String(),
            ];
        } )->all();
    }

    /**
     * An address as display lines.
     *
     * @since 1.0.0
     *
     * @param  array<string, mixed>  $address  Stored address.
     *
     * @return array<int, string>
     */
    public static function address( array $address ): array
    {
        $part  = static fn ( string $key ): string => is_scalar( $address[ $key ] ?? null ) ? trim( (string) $address[ $key ] ) : '';
        $lines = [
            trim( $part( 'first_name' ) . ' ' . $part( 'last_name' ) ),
            $part( 'company' ),
            $part( 'address1' ),
            $part( 'address2' ),
            trim( implode( ' ', array_filter( [ $part( 'city' ) . ( '' !== $part( 'region_code' ) || '' !== $part( 'region' ) ? ',' : '' ), '' !== $part( 'region_code' ) ? $part( 'region_code' ) : $part( 'region' ), $part( 'postal_code' ) ] ) ) ),
            strtoupper( $part( 'country_code' ) ),
        ];

        return array_values( array_filter( $lines, static fn ( string $line ): bool => '' !== $line && ',' !== $line ) );
    }

    /**
     * A timeline entry in words.
     *
     * @since 1.0.0
     *
     * @param  string                $type     Event type.
     * @param  array<string, mixed>  $payload  Event payload.
     *
     * @return string
     */
    public static function describe( string $type, array $payload ): string
    {
        $value = static fn ( string $key ): string => is_scalar( $payload[ $key ] ?? null ) ? (string) $payload[ $key ] : '';

        return match ( $type ) {
            'order.placed'              => __( 'Order placed' ),
            'order.status_changed'      => __( 'Status changed from :from to :to', [ 'from' => Statuses::label( 'system', $value( 'from' ) ), 'to' => Statuses::label( 'system', $value( 'to' ) ) ] ),
            'order.substatus_changed'   => __( 'Moved to :column', [ 'column' => '' !== $value( 'to_label' ) ? $value( 'to_label' ) : __( 'another column' ) ] ),
            'order.cancelled'           => __( 'Order cancelled' ),
            'order.refunded'            => __( 'Order refunded' ),
            'order.edited'              => __( 'Order edited' ),
            'note.added'                => __( 'Note added' ),
            'payment.captured'          => __( 'Payment captured' ),
            'kanban.assignment_added'   => __( 'Added to board :board', [ 'board' => $value( 'board_key' ) ] ),
            'kanban.assignment_removed' => __( 'Removed from board :board', [ 'board' => $value( 'board_key' ) ] ),
            default                     => Str::headline( str_replace( '.', ' ', $type ) ),
        };
    }
}
