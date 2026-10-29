<?php

declare( strict_types=1 );

use ArtisanPackUI\EcommerceKanbanLivewire\Support\SafeUrl;

it( 'keeps http(s), mailto, and relative URLs', function ( string $url ): void {
    expect( SafeUrl::of( $url ) )->toBe( $url );
} )->with( [
    'https://example.test/track',
    'HTTP://example.test',
    'mailto:jane@example.test',
    '/admin/orders/1',
    '?card=1',
    '#card-1',
    '//cdn.example.test/x',
] );

it( 'drops script and data URLs, however they are disguised', function ( mixed $url ): void {
    expect( SafeUrl::of( $url ) )->toBeNull();
} )->with( [
    'javascript:alert(1)',
    'JavaScript:alert(1)',
    " java\tscript:alert(1)",
    "java\nscript:alert(1)",
    'data:text/html;base64,PHNjcmlwdD4=',
    'vbscript:msgbox(1)',
    '',
    '   ',
    null,
    42,
] );
