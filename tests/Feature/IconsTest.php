<?php

declare( strict_types=1 );

use ArtisanPack\LivewireUiComponents\Support\IconRegistry;
use ArtisanPackUI\EcommerceKanbanLivewire\Support\Icons;

it( 'keeps names from the component library\'s icon registry', function ( string $name ): void {
    expect( IconRegistry::has( $name ) )->toBeTrue()
        ->and( Icons::name( $name ) )->toBe( $name );
} )->with( [ 'settings', 'external-link', 'layout-grid' ] );

it( 'prefixes a Heroicon name that isn\'t in the registry', function (): void {
    expect( IconRegistry::has( 'check-circle' ) )->toBeFalse()
        ->and( Icons::name( 'check-circle' ) )->toBe( 'o-check-circle' )
        ->and( Icons::name( 's-check-circle' ) )->toBe( 's-check-circle' );
} );

it( 'drops an unknown or empty name', function ( ?string $name ): void {
    expect( Icons::name( $name ) )->toBeNull();
} )->with( [ 'unknown' => 'no-such-icon-anywhere', 'empty' => '', 'null' => null ] );

it( 'keeps an icon registered at runtime', function (): void {
    IconRegistry::register( [ 'ec-kb-test-glyph' => '<path d="M4 4h16v16H4z" />' ] );

    try {
        expect( Icons::name( 'ec-kb-test-glyph' ) )->toBe( 'ec-kb-test-glyph' );
    } finally {
        IconRegistry::reset();
    }
} );
