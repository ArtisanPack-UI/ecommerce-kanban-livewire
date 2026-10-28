<?php

declare( strict_types=1 );

use Illuminate\Support\Facades\Artisan;

/**
 * A catalogue from `lang/`.
 *
 * @return array<string, string>
 */
function catalogue( string $locale ): array
{
    return json_decode( (string) file_get_contents( __DIR__ . '/../../lang/' . $locale . '.json' ), true, flags: JSON_THROW_ON_ERROR );
}

/**
 * The `:placeholder` and `{placeholder}` tokens in a string, sorted.
 *
 * @return array<int, string>
 */
function placeholders( string $text ): array
{
    preg_match_all( '/:[a-z_]+|\{[a-z]+\}/i', $text, $matches );

    $tokens = array_values( array_unique( $matches[0] ) );
    sort( $tokens );

    return $tokens;
}

it( 'translates every English key', function ( string $locale ): void {
    $english = catalogue( 'en' );
    $other   = catalogue( $locale );

    expect( array_keys( $other ) )->toBe( array_keys( $english ) );

    foreach ( $other as $key => $value ) {
        expect( $value )->toBeString()->not->toBe( '', $key );
    }
} )->with( [ 'es', 'fr', 'de' ] );

it( 'keeps every placeholder', function ( string $locale ): void {
    foreach ( catalogue( $locale ) as $key => $value ) {
        expect( placeholders( $value ) )->toBe( placeholders( $key ), $locale . ': ' . $key );
    }
} )->with( [ 'en', 'es', 'fr', 'de' ] );

it( 'keeps the English catalogue as its own keys', function (): void {
    foreach ( catalogue( 'en' ) as $key => $value ) {
        expect( $value )->toBe( $key );
    }
} );

it( 'gives plural strings every form and chooses one for 0, 1, and many', function ( string $locale ): void {
    app()->setLocale( $locale );

    $plurals = array_filter( catalogue( $locale ), static fn ( string $value, string $key ): bool => str_contains( $key, '|' ), ARRAY_FILTER_USE_BOTH );

    expect( $plurals )->not->toBeEmpty();

    foreach ( $plurals as $key => $value ) {
        expect( count( explode( '|', $value ) ) )->toBe( count( explode( '|', $key ) ), $locale . ': ' . $key );

        foreach ( [ 0, 1, 5 ] as $count ) {
            $chosen = trans_choice( $key, $count, [ 'count' => $count, 'column' => 'Packing', 'number' => '1042', 'conditions' => 'X', 'board' => 'B' ] );

            expect( $chosen )->not->toContain( '|' )->not->toContain( ':count' );

            if ( str_contains( $key, ':count' ) ) {
                expect( $chosen )->toContain( (string) $count );
            }
        }

        expect( trans_choice( $key, 1, [ 'count' => 1 ] ) )->not->toBe( trans_choice( $key, 5, [ 'count' => 5 ] ), $locale . ': ' . $key );
    }
} )->with( [ 'en', 'es', 'fr', 'de' ] );

it( 'passes the engine translation lint', function (): void {
    $root = realpath( __DIR__ . '/../..' );

    $code = Artisan::call( 'ecommerce:lint:translations', [
        '--no-engine' => true,
        '--path'      => [ $root . '/src', $root . '/resources' ],
        '--lang'      => $root . '/lang',
    ] );

    $output = Artisan::output();

    expect( $code )->toBe( 0, $output )
        ->and( $output )->toContain( 'Translation lint passed' );
} );
