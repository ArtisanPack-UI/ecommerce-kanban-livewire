<?php

declare( strict_types=1 );

use Illuminate\Support\Facades\Artisan;

const KANBAN_TRANSLATED_LOCALES = [ 'es', 'fr', 'de', 'pt_BR', 'ja', 'zh_CN', 'it', 'ar' ];

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
 * The number of plural segments a translation of `$key` has in `$locale`.
 *
 * Laravel always picks the first segment for Japanese and Chinese, so those
 * locales use one segment that carries `:count`. Arabic has six forms (zero,
 * one, two, few, many, other), so a `singular|plural` key becomes six
 * segments there.
 */
function pluralSegments( string $locale, string $key ): int
{
    $segments = count( explode( '|', $key ) );

    if ( 1 === $segments ) {
        return 1;
    }

    return match ( $locale ) {
        'ja', 'zh_CN' => 1,
        'ar'          => 6,
        default       => $segments,
    };
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
} )->with( KANBAN_TRANSLATED_LOCALES );

it( 'keeps every placeholder', function ( string $locale ): void {
    foreach ( catalogue( $locale ) as $key => $value ) {
        expect( placeholders( $value ) )->toBe( placeholders( $key ), $locale . ': ' . $key );
    }
} )->with( [ 'en', ...KANBAN_TRANSLATED_LOCALES ] );

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
        expect( count( explode( '|', $value ) ) )->toBe( pluralSegments( $locale, $key ), $locale . ': ' . $key );

        foreach ( [ 0, 1, 5 ] as $count ) {
            $chosen = trans_choice( $key, $count, [ 'count' => $count, 'column' => 'Packing', 'number' => '1042', 'conditions' => 'X', 'board' => 'B' ] );

            expect( $chosen )->not->toContain( '|' )->not->toContain( ':count' );

            // Arabic's zero, one, and two forms may spell the number out.
            if ( str_contains( $key, ':count' ) && ( 'ar' !== $locale || $count > 2 ) ) {
                expect( $chosen )->toContain( (string) $count );
            }
        }

        if ( pluralSegments( $locale, $key ) > 1 || str_contains( $key, ':count' ) ) {
            expect( trans_choice( $key, 1, [ 'count' => 1 ] ) )->not->toBe( trans_choice( $key, 5, [ 'count' => 5 ] ), $locale . ': ' . $key );
        }
    }
} )->with( [ 'en', ...KANBAN_TRANSLATED_LOCALES ] );

it( 'uses all six Arabic plural forms', function (): void {
    app()->setLocale( 'ar' );

    $line   = ':count card|:count cards';
    $chosen = array_map( static fn ( int $count ): string => trans_choice( $line, $count, [ 'count' => $count ] ), [ 0, 1, 2, 3, 11, 100 ] );

    expect( array_unique( array_slice( $chosen, 0, 5 ) ) )->toHaveCount( 5 )
        ->and( $chosen[3] )->toContain( '3' )
        ->and( $chosen[4] )->toContain( '11' )
        ->and( $chosen[5] )->toContain( '100' );
} );

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

it( 'agrees with the engine on every key they share', function ( string $locale ): void {
    // Every package's JSON catalogue loads into one namespace, so a shared key
    // must read the same everywhere or the winner depends on load order. The
    // engine uses "WIP limit" as a lowercase validation attribute, while the
    // board shows it as a field label, so that key is left out.
    $engine = dirname( ( new ReflectionClass( ArtisanPackUI\Ecommerce\Providers\EcommerceServiceProvider::class ) )->getFileName(), 3 ) . '/lang/' . $locale . '.json';
    $theirs = json_decode( (string) file_get_contents( $engine ), true, flags: JSON_THROW_ON_ERROR );

    foreach ( catalogue( $locale ) as $key => $value ) {
        if ( 'WIP limit' !== $key && array_key_exists( $key, $theirs ) ) {
            expect( $value )->toBe( $theirs[ $key ], $locale . ': ' . $key );
        }
    }
} )->with( [ 'es', 'fr', 'de' ] );

/**
 * Keys `__()` / `trans_choice()` can't be found for by a literal scan,
 * because the code builds them at runtime. Add a key here only with a note
 * on where it is built.
 *
 * @var array<int, string>
 */
const KANBAN_DYNAMIC_TRANSLATION_KEYS = [];

/**
 * Every literal key passed to `__()`, `trans_choice()`, or `@lang()` in
 * `src/`, `resources/views`, and `routes/`, skipping docblock lines.
 *
 * @return array<int, string>
 */
function sourceTranslationKeys(): array
{
    $root  = dirname( __DIR__, 2 );
    $files = [];

    foreach ( [ 'src', 'resources/views', 'routes' ] as $directory ) {
        if ( ! is_dir( $root . '/' . $directory ) ) {
            continue;
        }

        foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root . '/' . $directory, FilesystemIterator::SKIP_DOTS ) ) as $file ) {
            if ( $file->isFile() && str_ends_with( $file->getFilename(), '.php' ) ) {
                $files[] = $file->getPathname();
            }
        }
    }

    $keys = [];

    foreach ( $files as $path ) {
        $code = implode( "\n", array_filter(
            explode( "\n", (string) file_get_contents( $path ) ),
            static fn ( string $line ): bool => ! str_starts_with( ltrim( $line ), '*' ) && ! str_starts_with( ltrim( $line ), '/**' ),
        ) );

        preg_match_all( "/(?:\\b__|\\btrans_choice|@lang)\\(\\s*(?:'((?:[^'\\\\]|\\\\.)*)'|\"((?:[^\"\\\\]|\\\\.)*)\")/s", $code, $matches, PREG_SET_ORDER );

        foreach ( $matches as $match ) {
            $keys[] = '' !== ( $match[1] ?? '' ) ? stripcslashes( str_replace( "\\'", "'", $match[1] ) ) : stripcslashes( $match[2] ?? '' );
        }
    }

    return array_values( array_unique( array_filter( $keys, static fn ( string $key ): bool => '' !== $key ) ) );
}

it( 'has every key used in the source in the English catalogue', function (): void {
    expect( array_values( array_diff( sourceTranslationKeys(), array_keys( catalogue( 'en' ) ) ) ) )->toBe( [] );
} );

it( 'has no English key that the source does not use', function (): void {
    $unused = array_diff( array_keys( catalogue( 'en' ) ), sourceTranslationKeys(), KANBAN_DYNAMIC_TRANSLATION_KEYS );

    expect( array_values( $unused ) )->toBe( [] );
} );
