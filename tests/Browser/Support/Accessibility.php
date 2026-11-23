<?php

declare( strict_types=1 );

namespace Tests\Browser\Support;

use PHPUnit\Framework\Assert;

/**
 * axe checks for the browser suite (#22, #23).
 *
 * Runs every axe rule, plus target-size (WCAG 2.2 SC 2.5.8), and fails on
 * any violation except the ones listed in {@see self::LIBRARY_ISSUES}:
 * failures that come from livewire-ui-components markup this package
 * cannot change. Each entry names the axe rule and a CSS selector; a node
 * is ignored only when it (or an ancestor) matches. Remove an entry once
 * the library fixes it, and the suite enforces it here.
 */
final class Accessibility
{
    /**
     * Known library failures: axe rule => selector the node must sit in.
     *
     * @var array<int, array{0: string, 1: string}>
     */
    public const LIBRARY_ISSUES = [
        // ArtisanPack-UI/livewire-ui-components#164: the 3.0 Drawer's panel
        // is an <aside> with role="dialog", a role axe doesn't allow on
        // <aside>.
        [ 'aria-allowed-role', '[data-scope="drawer"][data-part="content"]' ],
        // ArtisanPack-UI/livewire-ui-components#168: once the ColorPicker starts, its
        // label's `for` points at the machine's hidden input, so the visible
        // text field has no label.
        [ 'label', '[data-scope="color-picker"][data-part="control"]' ],
        [ 'label-title-only', '[data-scope="color-picker"][data-part="control"]' ],
    ];

    /**
     * The open dialog or drawer panel: a modal one hides the rest of the
     * page from assistive technology, so only it is checked.
     *
     * @var string
     */
    public const OPEN_OVERLAY = ':is([data-scope="dialog"], [data-scope="drawer"])[data-part="content"][data-state="open"]';

    /**
     * JavaScript: whether every finite CSS animation and transition on the
     * page has finished, so axe sees final colours: a modal is "visible"
     * while its box is still fading in from opacity 0. Endless animations
     * (skeletons, a moving card's pulse) are left out.
     *
     * @var string
     */
    public const ANIMATIONS_SETTLED = <<<'JS'
        document.getAnimations().every( ( animation ) => 'running' !== animation.playState || Infinity === animation.effect?.getTiming().iterations )
        JS;

    /**
     * Asserts the page has no accessibility violations beyond the known
     * library ones.
     *
     * A modal dialog or drawer hides the rest of the page from assistive
     * technology, so pass `$within` (the open panel) to check only that.
     *
     * @param  object       $page    The page (a Pest browser webpage).
     * @param  string|null  $within  A selector to check only that part of the page.
     *
     * @return object
     */
    public static function assertAccessible( object $page, ?string $within = null ): object
    {
        $page->assertScript( 'document.readyState', 'complete' );
        $page->assertScript( self::ANIMATIONS_SETTLED );

        $ignored = json_encode( self::LIBRARY_ISSUES );
        $context = null === $within ? 'document' : 'document.querySelector( ' . json_encode( $within ) . ' )';

        /** @var array<int, array{id: string, impact: string|null, help: string, nodes: array<int, string>}> $violations */
        $violations = (array) $page->script( <<<JS
            async () => {
                const ignored = {$ignored};
                // target-size (WCAG 2.2 SC 2.5.8, 24px) is off by default in axe 4.x.
                const { violations } = await window.axe.run( {$context}, { rules: { 'target-size': { enabled: true } } } );

                return violations
                    .map( ( violation ) => ( {
                        id: violation.id,
                        impact: violation.impact,
                        help: violation.help,
                        nodes: violation.nodes
                            .filter( ( node ) => {
                                const element = document.querySelector( node.target[ 0 ] );

                                return ! ignored.some( ( [ rule, selector ] ) => rule === violation.id && element?.closest( selector ) );
                            } )
                            .map( ( node ) => node.target.join( ' ' ) + ' :: ' + node.html.slice( 0, 200 ) ),
                    } ) )
                    .filter( ( violation ) => violation.nodes.length > 0 );
            }
            JS );

        $report = implode( "\n", array_map(
            static fn ( array $violation ): string => sprintf( "- [%s] %s (%s)\n    %s", $violation['impact'] ?? 'n/a', $violation['help'], $violation['id'], implode( "\n    ", $violation['nodes'] ) ),
            $violations,
        ) );

        Assert::assertSame( [], $violations, "Accessibility violations:\n" . $report );

        return $page;
    }
}
