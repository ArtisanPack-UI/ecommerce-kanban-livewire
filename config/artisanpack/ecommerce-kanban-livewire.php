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
