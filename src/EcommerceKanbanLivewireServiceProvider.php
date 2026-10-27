<?php

/**
 * Ecommerce kanban board (Livewire) service provider.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceKanbanLivewire
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceKanbanLivewire;

use ArtisanPackUI\Ecommerce\Registries\AdminMenuRegistry;
use ArtisanPackUI\Ecommerce\Registries\SatelliteRegistry;
use ArtisanPackUI\EcommerceKanbanLivewire\Console\Commands\InstallCommand;
use ArtisanPackUI\EcommerceKanbanLivewire\Http\Middleware\ThrottleKanbanMutations;
use ArtisanPackUI\EcommerceKanbanLivewire\Livewire\Board;
use ArtisanPackUI\EcommerceKanbanLivewire\Livewire\BoardPicker;
use ArtisanPackUI\EcommerceKanbanLivewire\Livewire\CardDetail;
use ArtisanPackUI\EcommerceKanbanLivewire\Support\BoardUrls;
use ArtisanPackUI\EcommerceKanbanLivewire\Support\LayoutResolver;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

/**
 * Bootstraps the Livewire kanban board satellite.
 *
 * Registers the package with the engine's `SatelliteRegistry` first. When the
 * satellite has been uninstalled (`register()` returns false) nothing else is
 * wired: no views, routes, components, navigation, broadcasting listeners,
 * or hooks.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceKanbanLivewire
 *
 * @since      1.0.0
 */
class EcommerceKanbanLivewireServiceProvider extends ServiceProvider
{
    /**
     * The package version reported to the engine's `SatelliteRegistry`.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public const VERSION = '1.0.0';

    /**
     * The Composer package name.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public const PACKAGE_NAME = 'artisanpack-ui/ecommerce-kanban-livewire';

    /**
     * The view and translation namespace.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public const VIEW_NAMESPACE = 'ecommerce-kanban';

    /**
     * The Livewire components, keyed by name.
     *
     * @since 1.0.0
     *
     * @var array<string, class-string>
     */
    public const LIVEWIRE_COMPONENTS = [
        'artisanpack-ecommerce-kanban-board'        => Board::class,
        'artisanpack-ecommerce-kanban-board-picker' => BoardPicker::class,
        'artisanpack-ecommerce-kanban-card-detail'  => CardDetail::class,
    ];

    /**
     * The anonymous `<x-artisanpack-ec-kb-…>` Blade components, keyed by
     * name, with their views.
     *
     * @since 1.0.0
     *
     * @var array<string, string>
     */
    public const BLADE_COMPONENTS = [
        'card'   => self::VIEW_NAMESPACE . '::components.card',
        'column' => self::VIEW_NAMESPACE . '::components.column',
        'empty'  => self::VIEW_NAMESPACE . '::components.empty',
        'widget' => self::VIEW_NAMESPACE . '::components.widget',
        'wip'    => self::VIEW_NAMESPACE . '::components.wip',
    ];

    /**
     * The admin menu entry key.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public const MENU_KEY = 'kanban';

    /**
     * Registers the package configuration.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/artisanpack/ecommerce-kanban-livewire.php',
            'artisanpack.ecommerce-kanban-livewire',
        );
    }

    /**
     * Registers the satellite and, when it is active, wires the board.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function boot(): void
    {
        if ( ! $this->registerSatellite() ) {
            return;
        }

        $this->registerPublishing();
        $this->registerViews();
        $this->registerTranslations();
        $this->registerCommands();
        $this->registerBladeComponents();
        $this->registerLayoutResolver();
        $this->registerLivewireComponents();
        $this->registerRoutes();
        $this->registerNavigation();
    }

    /**
     * The descriptor this package registers with the engine (spec §14).
     *
     * The board stores its boards, columns, automations, and assignments in
     * the engine's kanban tables, so it owns no tables, columns, migrations,
     * meta namespaces, or product types, and ships no uninstaller.
     *
     * @since 1.0.0
     *
     * @return array<string, mixed>
     */
    public static function satelliteDescriptor(): array
    {
        return [
            'package_name'    => self::PACKAGE_NAME,
            'version'         => self::VERSION,
            'label'           => __( 'Kanban board (Livewire)' ),
            'migration_paths' => [],
            'config_keys'     => [ 'artisanpack.ecommerce-kanban-livewire' ],
            'meta_namespaces' => [],
            'tables'          => [],
            'columns'         => [],
            'product_types'   => [],
        ];
    }

    /**
     * Makes the configured `board.middleware` persistent: class names as
     * given, aliases resolved to their classes. Middleware groups (`web`)
     * are left out; Livewire's update route has its own.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function registerPersistentMiddleware(): void
    {
        if ( ! class_exists( Livewire::class ) ) {
            return;
        }

        $middleware = self::hostPersistentMiddleware();

        if ( [] !== $middleware ) {
            Livewire::addPersistentMiddleware( $middleware );
        }
    }

    /**
     * The middleware classes behind `board.middleware`, without groups.
     *
     * @since 1.0.0
     *
     * @return array<int, class-string>
     */
    public static function hostPersistentMiddleware(): array
    {
        $router  = app( 'router' );
        $aliases = $router->getMiddleware();
        $groups  = $router->getMiddlewareGroups();
        $classes = [];

        foreach ( (array) config( 'artisanpack.ecommerce-kanban-livewire.board.middleware', [] ) as $entry ) {
            if ( ! is_string( $entry ) || '' === $entry ) {
                continue;
            }

            $name = explode( ':', $entry, 2 )[0];

            if ( isset( $groups[ $name ] ) ) {
                continue;
            }

            $class = $aliases[ $name ] ?? $name;

            if ( is_string( $class ) && class_exists( $class ) ) {
                $classes[] = $class;
            }
        }

        return array_values( array_unique( $classes ) );
    }

    /**
     * Registers the package with the engine's `SatelliteRegistry`.
     *
     * @since 1.0.0
     *
     * @return bool True when the satellite is active.
     */
    protected function registerSatellite(): bool
    {
        return $this->app->make( SatelliteRegistry::class )->register( self::satelliteDescriptor() );
    }

    /**
     * Registers the config, views, and translations publish tags.
     *
     * @since 1.0.0
     *
     * @return void
     */
    protected function registerPublishing(): void
    {
        $this->publishes(
            [
                __DIR__ . '/../config/artisanpack/ecommerce-kanban-livewire.php' => config_path( 'artisanpack/ecommerce-kanban-livewire.php' ),
            ],
            'ecommerce-kanban-config',
        );

        $this->publishes(
            [
                __DIR__ . '/../resources/views' => resource_path( 'views/vendor/' . self::VIEW_NAMESPACE ),
            ],
            'ecommerce-kanban-views',
        );

        $this->publishes(
            [
                __DIR__ . '/../lang' => $this->app->langPath( 'vendor/' . self::VIEW_NAMESPACE ),
            ],
            'ecommerce-kanban-lang',
        );
    }

    /**
     * Loads the package views under the `ecommerce-kanban` namespace.
     *
     * @since 1.0.0
     *
     * @return void
     */
    protected function registerViews(): void
    {
        $this->loadViewsFrom( __DIR__ . '/../resources/views', self::VIEW_NAMESPACE );
    }

    /**
     * Loads the JSON translation catalogues.
     *
     * Published catalogues in `lang/vendor/ecommerce-kanban` are loaded too so a
     * host can override individual strings.
     *
     * @since 1.0.0
     *
     * @return void
     */
    protected function registerTranslations(): void
    {
        $this->loadJsonTranslationsFrom( __DIR__ . '/../lang' );
        $this->loadJsonTranslationsFrom( $this->app->langPath( 'vendor/' . self::VIEW_NAMESPACE ) );
    }

    /**
     * Registers the Artisan commands.
     *
     * @since 1.0.0
     *
     * @return void
     */
    protected function registerCommands(): void
    {
        if ( ! $this->app->runningInConsole() ) {
            return;
        }

        $this->commands( [
            InstallCommand::class,
        ] );
    }

    /**
     * Registers the `<x-artisanpack-ec-kb-…>` Blade components.
     *
     * @since 1.0.0
     *
     * @return void
     */
    protected function registerBladeComponents(): void
    {
        foreach ( self::BLADE_COMPONENTS as $name => $view ) {
            Blade::component( $view, 'artisanpack-ec-kb-' . $name );
        }
    }

    /**
     * Shares the layout every page view extends (spec D8).
     *
     * Pages extend `$ecommerceKanbanLayout`; `$ecommerceKanbanPushesAssets`
     * tells the page to push the host's assets onto a layout that ships none.
     *
     * @since 1.0.0
     *
     * @return void
     */
    protected function registerLayoutResolver(): void
    {
        View::composer( self::VIEW_NAMESPACE . '::pages.*', static function ( ViewContract $view ): void {
            $layout = LayoutResolver::layout();

            $view->with( [
                'ecommerceKanbanLayout'       => $layout,
                'ecommerceKanbanPushesAssets' => LayoutResolver::pushesAssets( $layout ),
            ] );
        } );
    }

    /**
     * Registers the Livewire components and the persistent middleware.
     *
     * The route middleware only guards the initial page load. Registering the
     * `ecommerce.admin.mutate` limiter as persistent makes Livewire re-run it
     * on every update request from a board page. So do the host's own
     * `board.middleware` entries (2FA, `verified`, ...), once every alias is
     * known.
     *
     * @since 1.0.0
     *
     * @return void
     */
    protected function registerLivewireComponents(): void
    {
        if ( ! class_exists( Livewire::class ) ) {
            return;
        }

        foreach ( self::LIVEWIRE_COMPONENTS as $name => $class ) {
            Livewire::component( $name, $class );
        }

        Livewire::addPersistentMiddleware( [ ThrottleKanbanMutations::class ] );

        $this->app->booted( fn () => $this->registerPersistentMiddleware() );
    }

    /**
     * Registers the board routes and the throttle middleware alias.
     *
     * Skipped when the routes are cached (the cache already holds them) or
     * when `board.routes_enabled` is false.
     *
     * @since 1.0.0
     *
     * @return void
     */
    protected function registerRoutes(): void
    {
        Route::aliasMiddleware( ThrottleKanbanMutations::ALIAS, ThrottleKanbanMutations::class );

        if ( $this->app->routesAreCached() || ! self::routesEnabled() ) {
            return;
        }

        $this->loadRoutesFrom( __DIR__ . '/../routes/web.php' );
    }

    /**
     * Adds "Kanban" to the engine's admin menu (spec §5.4), which the admin
     * and cms-framework menus read. Left out when the routes are off, since
     * the entry would have nowhere to go.
     *
     * @since 1.0.0
     *
     * @return void
     */
    protected function registerNavigation(): void
    {
        if ( ! self::routesEnabled() || ! $this->app->bound( AdminMenuRegistry::class ) ) {
            return;
        }

        $menu = $this->app->make( AdminMenuRegistry::class );

        if ( $menu->has( self::MENU_KEY ) ) {
            return;
        }

        $menu->register( self::MENU_KEY, [
            'label'      => static fn (): string => __( 'Kanban' ),
            'icon'       => 'o-view-columns',
            'route'      => BoardUrls::INDEX_ROUTE,
            'section'    => 'orders',
            'position'   => 15,
            'permission' => 'kanbanBoard.viewAny',
            'satellite'  => self::PACKAGE_NAME,
        ] );
    }

    /**
     * Whether the package registers its board routes.
     *
     * @since 1.0.0
     *
     * @return bool
     */
    protected static function routesEnabled(): bool
    {
        return (bool) config( 'artisanpack.ecommerce-kanban-livewire.board.routes_enabled', true );
    }
}
