<?php

/**
 * Ability checks for the board components.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceKanbanLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceKanbanLivewire\Livewire\Concerns;

use ArtisanPackUI\EcommerceKanbanLivewire\Support\Authorization;

/**
 * Aborts with 403 when the current user lacks an engine ability (spec §6).
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceKanbanLivewire
 *
 * @since      1.0.0
 */
trait AuthorizesKanban
{
    /**
     * Aborts with 403 unless the current user holds `$ability`.
     *
     * @since 1.0.0
     *
     * @param  string  $ability  `{resource}.{action}`.
     * @param  mixed   $subject  The model acted on, if any.
     *
     * @return void
     */
    protected function authorizeKanban( string $ability, mixed $subject = null ): void
    {
        if ( ! Authorization::allows( auth()->user(), $ability, $subject ) ) {
            abort( 403, __( 'You are not allowed to do that.' ) );
        }
    }
}
