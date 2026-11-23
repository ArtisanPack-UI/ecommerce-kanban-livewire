<?php

declare( strict_types=1 );

use ArtisanPackUI\EcommerceKanbanLivewire\Support\ControlOptions;

it( 'maps id, name and hint to the design system\'s value, label and description', function (): void {
    expect( ControlOptions::from( [
        [ 'id' => 7, 'name' => 'Packing', 'hint' => 'Second column', 'disabled' => true ],
        [ 'value' => 'top', 'label' => 'Top', 'name' => 'ignored' ],
        [ 'id' => null, 'name' => 'Any' ],
        'plain',
    ] ) )->toBe( [
        [ 'disabled' => true, 'value' => 7, 'label' => 'Packing', 'description' => 'Second column' ],
        [ 'value' => 'top', 'label' => 'Top' ],
        [ 'value' => '', 'label' => 'Any' ],
        'plain',
    ] );
} );

it( 'maps Select groups recursively and accepts null', function (): void {
    expect( ControlOptions::from( [ [ 'label' => 'Processing', 'options' => [ [ 'id' => 1, 'name' => 'Packing' ] ] ] ] ) )
        ->toBe( [ [ 'label' => 'Processing', 'options' => [ [ 'value' => 1, 'label' => 'Packing' ] ] ] ] )
        ->and( ControlOptions::from( null ) )->toBe( [] );
} );
