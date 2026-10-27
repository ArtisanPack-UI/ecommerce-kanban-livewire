<?php

/**
 * Ecommerce kanban board (Livewire) configuration.
 *
 * Read under the `artisanpack.ecommerce-kanban-livewire` key.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceKanbanLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

return [

    /*
    |--------------------------------------------------------------------------
    | Board routes
    |--------------------------------------------------------------------------
    |
    | The board, board settings, and automation screens. `middleware` wraps
    | every route; add the host's 2FA / `verified` middleware here. Set
    | `routes_enabled` to false to register your own routes and embed the
    | Livewire components.
    |
    */

    'board' => [
        'routes_enabled' => true,
        'route_prefix'   => 'ecommerce-kanban',
        'middleware'     => [ 'web', 'auth' ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Layout
    |--------------------------------------------------------------------------
    |
    | The Blade layout the board pages extend. Leave null to use, in order,
    | cms-framework's admin layout, the ecommerce admin's layout (when that
    | package is installed), or this package's standalone layout. A custom
    | layout must yield a `content` section (and may yield `title`) and load
    | the Livewire assets itself.
    |
    */

    'layout' => null,

    /*
    |--------------------------------------------------------------------------
    | Cards
    |--------------------------------------------------------------------------
    |
    | `per_column` is how many cards a column shows at first and how many more
    | each "Show more" adds.
    |
    */

    'cards' => [
        'per_column' => 25,
    ],

    /*
    |--------------------------------------------------------------------------
    | Real-time updates
    |--------------------------------------------------------------------------
    |
    | When enabled and the host has a broadcaster and Laravel Echo set up,
    | boards listen on the engine's private board channel so card moves made
    | elsewhere appear without a reload. `poll_interval` (seconds, 0 to turn
    | off) is the fallback when broadcasting is unavailable.
    |
    */

    'realtime' => [
        'enabled'       => true,
        'poll_interval' => 30,
    ],
];
