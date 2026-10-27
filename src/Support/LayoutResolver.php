<?php

/**
 * Board layout resolver.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceKanbanLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceKanbanLivewire\Support;

use ArtisanPackUI\Ecommerce\Registries\SatelliteRegistry;
use ArtisanPackUI\EcommerceKanbanLivewire\EcommerceKanbanLivewireServiceProvider;
use Illuminate\Support\Facades\View;
use Throwable;

/**
 * Picks the layout the board pages extend (spec D8).
 *
 * In order: the `artisanpack.ecommerce-kanban-livewire.layout` config,
 * cms-framework's admin layout, the ecommerce admin's layout (when that
 * satellite is installed and active), then the package's standalone layout.
 * Every candidate shares one contract: a `title` section, a `content`
 * section, and `styles` / `scripts` stacks.
 *
 * cms-framework's layout loads none of the host's assets, so under it each
 * page pushes the Vite build, the Livewire assets, and the toasts itself
 * ({@see self::pushesAssets()}). The admin's and the standalone layout load
 * them; a configured layout is expected to as well.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceKanbanLivewire
 *
 * @since      1.0.0
 */
final class LayoutResolver
{
    /**
     * A cms-framework class that is only present when it is installed.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public const CMS_PROBE = 'ArtisanPackUI\\CMSFramework\\Modules\\Admin\\Managers\\AdminMenuManager';

    /**
     * cms-framework's admin layout.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public const CMS_LAYOUT = 'cms::admin.layouts.app';

    /**
     * The ecommerce admin satellite's package name.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public const ADMIN_PACKAGE = 'artisanpack-ui/ecommerce-admin-livewire';

    /**
     * The ecommerce admin's standalone layout.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public const ADMIN_LAYOUT = 'ecommerce-admin::layouts.app';

    /**
     * This package's standalone layout.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public const STANDALONE_LAYOUT = EcommerceKanbanLivewireServiceProvider::VIEW_NAMESPACE . '::layouts.app';

    /**
     * Test override for the cms-framework probe.
     *
     * @since 1.0.0
     *
     * @var bool|null
     */
    private static ?bool $fakeCms = null;

    /**
     * The layout view name the board pages extend.
     *
     * @since 1.0.0
     *
     * @return string
     */
    public static function layout(): string
    {
        $configured = config( 'artisanpack.ecommerce-kanban-livewire.layout' );

        if ( is_string( $configured ) && '' !== trim( $configured ) && View::exists( trim( $configured ) ) ) {
            return trim( $configured );
        }

        if ( self::cmsInstalled() && View::exists( self::CMS_LAYOUT ) ) {
            return self::CMS_LAYOUT;
        }

        if ( self::adminInstalled() && View::exists( self::ADMIN_LAYOUT ) ) {
            return self::ADMIN_LAYOUT;
        }

        return self::STANDALONE_LAYOUT;
    }

    /**
     * Whether a page has to push the host's assets onto the layout's stacks.
     *
     * @since 1.0.0
     *
     * @param  string  $layout  The resolved layout.
     *
     * @return bool
     */
    public static function pushesAssets( string $layout ): bool
    {
        return self::CMS_LAYOUT === $layout;
    }

    /**
     * Whether cms-framework is installed.
     *
     * @since 1.0.0
     *
     * @return bool
     */
    public static function cmsInstalled(): bool
    {
        return self::$fakeCms ?? class_exists( self::CMS_PROBE );
    }

    /**
     * Whether the ecommerce admin satellite is installed and active.
     *
     * @since 1.0.0
     *
     * @return bool
     */
    public static function adminInstalled(): bool
    {
        try {
            $satellites = app( SatelliteRegistry::class );

            return $satellites->has( self::ADMIN_PACKAGE ) && $satellites->isActive( self::ADMIN_PACKAGE );
        } catch ( Throwable ) {
            return false;
        }
    }

    /**
     * Overrides the cms-framework probe in tests; null restores it.
     *
     * @since 1.0.0
     *
     * @param  bool|null  $installed  Whether cms-framework counts as installed.
     *
     * @return void
     */
    public static function fakeCms( ?bool $installed ): void
    {
        self::$fakeCms = $installed;
    }
}
