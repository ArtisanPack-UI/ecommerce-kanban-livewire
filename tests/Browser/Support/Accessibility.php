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
        // ArtisanPack-UI/livewire-ui-components#117: the search input of
        // `x-artisanpack-choices` / `x-artisanpack-choices-offline` has no
        // label (the field label is a <legend> it isn't linked to).
        [ 'label', '[x-ref="searchInput"]' ],
        // ArtisanPack-UI/livewire-ui-components#117: `x-artisanpack-select`
        // labels with a <legend>, so the control itself has no accessible
        // name.
        [ 'select-name', 'fieldset' ],
        // ArtisanPack-UI/livewire-ui-components#117: the native colour input
        // of `x-artisanpack-colorpicker` has no label.
        [ 'label', 'input[type="color"]' ],
        // ArtisanPack-UI/livewire-ui-components#117: the drawer's
        // `drawer-toggle` checkbox is unlabelled.
        [ 'label', '.drawer > input.drawer-toggle' ],
        // ArtisanPack-UI/livewire-ui-components#117: `x-artisanpack-dropdown`
        // menus put a <div> between the <ul> and its <li>s, and give items
        // role="menuitem" without a role="menu" parent.
        [ 'list', '.menu' ],
        [ 'listitem', '.menu' ],
        [ 'aria-required-children', '.menu' ],
        [ 'aria-required-parent', '.menu' ],
        // ArtisanPack-UI/livewire-ui-components#135: the modal's and drawer's
        // icon-only close buttons have no accessible name. (The board's own
        // buttons never use `btn-circle`.)
        [ 'button-name', '.btn-circle' ],
    ];

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
     * JavaScript: whether every open drawer has finished sliding and fading
     * in, so axe sees its final colours.
     *
     * @var string
     */
    public const DRAWERS_SETTLED = <<<'JS'
        Array.from( document.querySelectorAll( '.drawer-toggle:checked ~ .drawer-side' ) ).every( ( side ) => getComputedStyle( side ).opacity === '1' && Array.from( side.children ).every( ( panel ) => [ 'none', '0%', '0px' ].includes( getComputedStyle( panel ).translate ) ) )
        JS;

    /**
     * Asserts the page has no accessibility violations beyond the known
     * library ones.
     *
     * @param  object  $page  The page (a Pest browser webpage).
     *
     * @return object
     */
    public static function assertAccessible( object $page ): object
    {
        $page->assertScript( 'document.readyState', 'complete' );
        $page->assertScript( self::DRAWERS_SETTLED );
        $page->assertScript( self::ANIMATIONS_SETTLED );

        $ignored = json_encode( self::LIBRARY_ISSUES );

        /** @var array<int, array{id: string, impact: string|null, help: string, nodes: array<int, string>}> $violations */
        $violations = (array) $page->script( <<<JS
            async () => {
                const ignored = {$ignored};
                // target-size (WCAG 2.2 SC 2.5.8, 24px) is off by default in axe 4.x.
                const { violations } = await window.axe.run( document, { rules: { 'target-size': { enabled: true } } } );

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
