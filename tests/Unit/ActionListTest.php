<?php

declare( strict_types=1 );

use ArtisanPackUI\EcommerceKanbanLivewire\Support\ActionList;

$allowAll = static fn ( string $ability ): bool => true;

it( 'keeps the first action for each key', function () use ( $allowAll ): void {
    $actions = ActionList::normalize( [
        [ 'key' => 'reprint', 'label' => 'Reprint label', 'event' => 'labels.reprint' ],
        [ 'key' => 'invoice', 'label' => 'View invoice', 'url' => 'https://example.test/invoice' ],
        [ 'key' => 'reprint', 'label' => 'Reprint again', 'event' => 'other.reprint' ],
    ], $allowAll );

    expect( array_column( $actions, 'key' ) )->toBe( [ 'reprint', 'invoice' ] )
        ->and( $actions[0]['label'] )->toBe( 'Reprint label' );
} );

it( 'keeps the action with the preferred event for a built-in key, in the first one\'s place', function () use ( $allowAll ): void {
    $actions = ActionList::normalize( [
        [ 'key' => 'remove', 'label' => 'Satellite remove', 'event' => 'satellite.remove' ],
        [ 'key' => 'open', 'label' => 'Open', 'event' => 'ec-kb-open-card' ],
        [ 'key' => 'remove', 'label' => 'Remove from board', 'event' => 'ec-kb-remove-card' ],
        [ 'key' => 'open', 'label' => 'Satellite open', 'event' => 'satellite.open' ],
    ], $allowAll, [ 'open' => 'ec-kb-open-card', 'remove' => 'ec-kb-remove-card' ] );

    expect( array_column( $actions, 'label' ) )->toBe( [ 'Remove from board', 'Open' ] );
} );

it( 'keeps a satellite action under a built-in key when the built-in is gone', function () use ( $allowAll ): void {
    $actions = ActionList::normalize( [
        [ 'key' => 'remove', 'label' => 'Archive', 'event' => 'satellite.archive' ],
    ], $allowAll, [ 'remove' => 'ec-kb-remove-card' ] );

    expect( array_column( $actions, 'label' ) )->toBe( [ 'Archive' ] );
} );

it( 'drops a duplicate the viewer may not take before it can collide', function (): void {
    $actions = ActionList::normalize( [
        [ 'key' => 'refund', 'label' => 'Refund', 'ability' => 'order.refund', 'event' => 'orders.refund' ],
        [ 'key' => 'refund', 'label' => 'Ask for a refund', 'event' => 'orders.ask' ],
    ], static fn ( string $ability ): bool => 'order.refund' !== $ability );

    expect( array_column( $actions, 'label' ) )->toBe( [ 'Ask for a refund' ] );
} );
