<?php

declare( strict_types=1 );

use Tests\Browser\Support\Accessibility;
use Tests\Browser\Support\JavaScript;
use Tests\Browser\Support\Locators;

/*
 * axe on every board screen, dialog, and panel (#22, #23), against the
 * seeded demo board. Known component-library failures are listed in
 * Accessibility::LIBRARY_ISSUES and JavaScript::LIBRARY_ERRORS.
 */

it( 'has no accessibility violations on the board picker', function (): void {
    $page = visit( route( 'artisanpack.ecommerce.kanban.boards.index', [ 'pick' => 1 ] ) )->assertPresent( '[data-ec-kb-board]' );

    JavaScript::assertNoErrors( $page );
    Accessibility::assertAccessible( $page );
} );

it( 'has no accessibility violations on the board', function (): void {
    $page = visit( $this->boardUrl() )->assertPresent( '[data-ec-kb-card]' );

    JavaScript::assertNoErrors( $page );
    Accessibility::assertAccessible( $page );
} );

it( 'has no accessibility violations on the board in dark mode', function (): void {
    $page = visit( $this->boardUrl() )->inDarkMode()->assertPresent( '[data-ec-kb-card]' );

    Accessibility::assertAccessible( $page );
} );

it( 'has no accessibility violations with filters that hide cards', function (): void {
    $page = visit( $this->boardUrl( [ 'tags' => [ 'rush' ] ] ) )
        ->assertPresent( '[data-ec-kb-card="' . $this->orders['1001']->id . '"]' )
        ->assertPresent( '[data-ec-kb-matching]' );

    Accessibility::assertAccessible( $page );
} );

it( 'has no accessibility violations in the card slide-out', function (): void {
    $page = visit( $this->boardUrl( [ 'card' => $this->orders['1001']->id ] ) )->assertPresent( '[data-ec-kb-detail]' );

    JavaScript::assertNoErrors( $page );
    Accessibility::assertAccessible( $page );
} );

it( 'has no accessibility violations in the "Move to…" dialog', function (): void {
    $page = visit( $this->boardUrl() )
        ->click( Locators::role( 'Actions for order 1001' ) )
        ->click( '[data-ec-kb-card="' . $this->orders['1001']->id . '"] [data-ec-kb-action="move"]' )
        ->assertVisible( '[data-ec-kb-move-submit]' );

    Accessibility::assertAccessible( $page );
} );

it( 'has no accessibility violations in the "Add order" dialog', function (): void {
    $page = visit( $this->boardUrl() )
        ->click( '[data-ec-kb-add-order]' )
        ->assertVisible( '[data-ec-kb-add-order-submit]' );

    Accessibility::assertAccessible( $page );
} );

it( 'has no accessibility violations in edit mode and its dialogs', function (): void {
    $page = visit( $this->boardUrl() )
        ->click( '[data-ec-kb-edit-board]' )
        ->assertPresent( '[data-ec-kb-column-tools]' );

    Accessibility::assertAccessible( $page );

    $page->click( '[data-ec-kb-column="' . $this->columns['Packing']->id . '"] [data-ec-kb-edit-column]' )
        ->assertVisible( '[data-ec-kb-column-save]' );

    Accessibility::assertAccessible( $page );
} );

it( 'has no accessibility violations in the widget picker', function (): void {
    $page = visit( $this->boardUrl() )
        ->click( '[data-ec-kb-edit-board]' )
        ->click( '[data-ec-kb-board-widgets]' )
        ->assertVisible( '[data-ec-kb-widget-save]' );

    Accessibility::assertAccessible( $page );
} );

it( 'has no accessibility violations in the automations panel', function (): void {
    $page = visit( $this->boardUrl() )
        ->click( '[data-ec-kb-automations]' )
        ->assertVisible( '[data-ec-kb-automation-toggle]' );

    Accessibility::assertAccessible( $page );
} );

it( 'has no accessibility violations on a phone with the filters open', function (): void {
    $page = visit( $this->boardUrl() )->on()->mobile()
        ->assertPresent( '[data-ec-kb-card]' );

    Accessibility::assertAccessible( $page );

    $page->click( '[data-ec-kb-filters-toggle]' )->assertVisible( '[data-ec-kb-filters="ec-kb-filters-drawer"]' );

    Accessibility::assertAccessible( $page );
} );

it( 'has no accessibility violations on a read-only board', function (): void {
    $viewer = Tests\Fixtures\User::query()->create( [ 'name' => 'Val Viewer', 'email' => 'viewer@example.test', 'password' => 'secret' ] );

    grantAbilities( [ 'kanbanBoard.viewAny', 'kanbanBoard.view', 'order.view' ] );

    $this->actingAs( $viewer );

    $page = visit( $this->boardUrl() )->assertPresent( '[data-ec-kb-card]' )->assertNotPresent( '[data-ec-kb-add-order]' );

    JavaScript::assertNoErrors( $page );
    Accessibility::assertAccessible( $page );
} );

it( 'has no accessibility violations with the automation delete confirmation open', function (): void {
    $page = visit( $this->boardUrl() )
        ->click( '[data-ec-kb-automations]' )
        ->click( '[data-ec-kb-automation] [data-ec-kb-delete-automation]' )
        ->assertVisible( '[data-ec-kb-confirm-delete-automation]' );

    Accessibility::assertAccessible( $page );
} );
