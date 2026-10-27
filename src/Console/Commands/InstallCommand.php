<?php

/**
 * Install command.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceKanbanLivewire
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\EcommerceKanbanLivewire\Console\Commands;

use ArtisanPackUI\Ecommerce\Models\KanbanBoard;
use ArtisanPackUI\EcommerceKanbanLivewire\Support\AdminLinks;
use Composer\InstalledVersions;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;
use Throwable;

/**
 * Publishes the board config, prints the front-end lines the host adds, and
 * checks what the board needs (spec §4.3, §5.5, §7.7, D11).
 *
 * The package ships no CSS or JS build and never edits the host's files: the
 * host adds the package views to its Tailwind sources and imports
 * `@artisanpack-ui/livewire-drag-and-drop` itself.
 *
 * @package    ArtisanPack_UI
 * @subpackage EcommerceKanbanLivewire
 *
 * @since      1.0.0
 */
class InstallCommand extends Command
{
    /**
     * The Tailwind `@source` lines a host adds to its main stylesheet.
     *
     * @since 1.0.0
     *
     * @var array<int, string>
     */
    public const TAILWIND_SOURCES = [
        '@source "../../vendor/artisanpack-ui/ecommerce-kanban-livewire/resources/views/**/*.blade.php";',
        '@source "../../vendor/artisanpack-ui/ecommerce-kanban-livewire/src/**/*.php";',
    ];

    /**
     * The drag-and-drop npm package and the lowest version the board supports
     * (cross-container groups, keyboard moves, revert; spec §10).
     *
     * @since 1.0.0
     *
     * @var string
     */
    public const DRAG_AND_DROP_PACKAGE = '@artisanpack-ui/livewire-drag-and-drop@^2.1.0';

    /**
     * The line that loads drag-and-drop, for `resources/js/app.js`.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public const DRAG_AND_DROP_IMPORT = "import '@artisanpack-ui/livewire-drag-and-drop';";

    /**
     * The lowest engine version the board runs on.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public const MIN_ENGINE_VERSION = '1.2.0';

    /**
     * The engine's Composer package.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public const ENGINE_PACKAGE = 'artisanpack-ui/ecommerce';

    /**
     * The engine's REST route for creating a board.
     *
     * @since 1.0.0
     *
     * @var string
     */
    public const REST_BOARDS_ROUTE = 'ecommerce.api.kanban.boards.store';

    /**
     * Broadcasters that don't deliver to browsers.
     *
     * @since 1.0.0
     *
     * @var array<int, string>
     */
    public const INERT_BROADCASTERS = [ 'null', 'log' ];

    /**
     * @var string
     */
    protected $signature = 'ecommerce-kanban:install
        {--force : Overwrite a previously published config file.}';

    /**
     * @var string
     */
    protected $description = 'Publish the kanban board config, print the front-end setup lines, and check the board\'s requirements.';

    /**
     * Runs the command.
     *
     * @since 1.0.0
     *
     * @return int
     */
    public function handle(): int
    {
        $this->components->info( __( 'Installing the kanban board.' ) );

        $this->call( 'vendor:publish', [
            '--tag'   => 'ecommerce-kanban-config',
            '--force' => (bool) $this->option( 'force' ),
        ] );

        $this->printFrontEnd();

        $this->newLine();
        $this->components->info( __( 'Checking the board\'s requirements:' ) );
        $this->checkEngine();
        $this->checkBoards();
        $this->checkRealtime();

        return self::SUCCESS;
    }

    /**
     * Prints the stylesheet and script lines the host adds.
     *
     * @since 1.0.0
     *
     * @return void
     */
    protected function printFrontEnd(): void
    {
        $this->newLine();
        $this->components->info( __( 'Add these lines to your main stylesheet (e.g. resources/css/app.css):' ) );

        foreach ( self::TAILWIND_SOURCES as $source ) {
            $this->line( '    ' . $source );
        }

        $this->newLine();
        $this->components->info( __( 'Install drag-and-drop:' ) );
        $this->line( '    npm install ' . self::DRAG_AND_DROP_PACKAGE );

        $this->newLine();
        $this->components->info( __( 'Add this line to your main script (e.g. resources/js/app.js):' ) );
        $this->line( '    ' . self::DRAG_AND_DROP_IMPORT );
    }

    /**
     * Reports the installed engine version.
     *
     * @since 1.0.0
     *
     * @return void
     */
    protected function checkEngine(): void
    {
        $version = self::engineVersion();

        if ( null === $version ) {
            $this->components->warn( __( 'Could not read the installed artisanpack-ui/ecommerce version.' ) );

            return;
        }

        $numeric = ltrim( $version, 'v' );

        // A branch install (`dev-main`) has no number to compare.
        if ( 1 === preg_match( '/^\d/', $numeric ) && version_compare( $numeric, self::MIN_ENGINE_VERSION, '<' ) ) {
            $this->components->warn( __( 'artisanpack-ui/ecommerce :version is installed; the board needs :minimum or later.', [
                'version' => $version,
                'minimum' => self::MIN_ENGINE_VERSION,
            ] ) );

            return;
        }

        $this->components->twoColumnDetail( __( 'Engine version' ), $version );
    }

    /**
     * Reports whether there is an active board to open, and how to create one.
     *
     * @since 1.0.0
     *
     * @return void
     */
    protected function checkBoards(): void
    {
        try {
            $active = KanbanBoard::query()->where( 'is_active', true )->count();
        } catch ( Throwable $exception ) {
            report( $exception );
            $this->components->warn( __( 'Could not count the kanban boards. Run your migrations, then run this command again.' ) );

            return;
        }

        if ( $active > 0 ) {
            $this->components->twoColumnDetail( __( 'Active boards' ), (string) $active );

            return;
        }

        $this->components->warn( __( 'There are no active kanban boards yet.' ) );

        $adminUrl = AdminLinks::createBoardUrl();

        if ( null !== $adminUrl ) {
            $this->line( '    ' . __( 'Create one in the store admin: :url', [ 'url' => $adminUrl ] ) );
        }

        $restUrl = Route::has( self::REST_BOARDS_ROUTE ) ? route( self::REST_BOARDS_ROUTE ) : '/api/ecommerce/v1/kanban/boards';
        $this->line( '    ' . __( 'Or create one through the REST API: POST :url', [ 'url' => $restUrl ] ) );
    }

    /**
     * Reports what real-time sync needs (spec §7.7, D9).
     *
     * @since 1.0.0
     *
     * @return void
     */
    protected function checkRealtime(): void
    {
        $engineBroadcasts = (bool) config( 'artisanpack.ecommerce.kanban.broadcast', false );
        $broadcaster      = (string) config( 'broadcasting.default', 'null' );
        $hasBroadcaster   = '' !== $broadcaster && ! in_array( $broadcaster, self::INERT_BROADCASTERS, true );
        $pollInterval     = (int) config( 'artisanpack.ecommerce-kanban-livewire.realtime.poll_interval', 30 );

        $this->components->twoColumnDetail(
            'artisanpack.ecommerce.kanban.broadcast',
            $engineBroadcasts ? __( 'on' ) : __( 'off' ),
        );
        $this->components->twoColumnDetail( __( 'Broadcaster' ), $broadcaster );

        if ( $engineBroadcasts && $hasBroadcaster ) {
            $this->line( '    ' . __( 'Make sure your main script sets up Laravel Echo (window.Echo); the board subscribes to the engine\'s private board channel.' ) );

            return;
        }

        $this->components->warn( $pollInterval > 0
            ? __( 'Real-time sync is off, so boards refresh every :seconds seconds instead.', [ 'seconds' => $pollInterval ] )
            : __( 'Real-time sync and polling are both off, so boards only change on reload.' ) );

        if ( ! $engineBroadcasts ) {
            $this->line( '    ' . __( 'Turn on broadcasting with ECOMMERCE_KANBAN_BROADCAST=true.' ) );
        }

        if ( ! $hasBroadcaster ) {
            $this->line( '    ' . __( 'Configure a broadcaster (for example run php artisan install:broadcasting for Reverb), then set up Laravel Echo in your main script.' ) );
        }
    }

    /**
     * The installed engine version, if Composer knows it.
     *
     * @since 1.0.0
     *
     * @return string|null
     */
    protected static function engineVersion(): ?string
    {
        try {
            return InstalledVersions::isInstalled( self::ENGINE_PACKAGE )
                ? InstalledVersions::getPrettyVersion( self::ENGINE_PACKAGE )
                : null;
        } catch ( Throwable ) {
            return null;
        }
    }
}
