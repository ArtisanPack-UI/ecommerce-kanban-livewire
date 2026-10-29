<?php

declare( strict_types=1 );

use ArtisanPackUI\Ecommerce\Models\KanbanBoard;
use ArtisanPackUI\EcommerceKanbanLivewire\Console\Commands\InstallCommand;
use Illuminate\Support\Facades\File;

afterEach( function (): void {
    File::delete( config_path( 'artisanpack/ecommerce-kanban-livewire.php' ) );
} );

it( 'publishes the config', function (): void {
    $this->artisan( 'ecommerce-kanban:install' )->assertSuccessful();

    expect( File::exists( config_path( 'artisanpack/ecommerce-kanban-livewire.php' ) ) )->toBeTrue();
} );

it( 'is idempotent and keeps an edited config unless forced', function (): void {
    $path = config_path( 'artisanpack/ecommerce-kanban-livewire.php' );

    $this->artisan( 'ecommerce-kanban:install' )->assertSuccessful();
    File::put( $path, '<?php return [ "edited" => true ];' );

    $this->artisan( 'ecommerce-kanban:install' )->assertSuccessful();
    expect( File::get( $path ) )->toContain( 'edited' );

    $this->artisan( 'ecommerce-kanban:install', [ '--force' => true ] )->assertSuccessful();
    expect( File::get( $path ) )->not->toContain( 'edited' )->toContain( 'route_prefix' );
} );

it( 'prints the Tailwind sources and the drag-and-drop install and import with the minimum version', function (): void {
    $command = $this->artisan( 'ecommerce-kanban:install' );

    foreach ( InstallCommand::TAILWIND_SOURCES as $source ) {
        $command->expectsOutputToContain( $source );
    }

    $command->expectsOutputToContain( 'npm install @artisanpack-ui/livewire-drag-and-drop@^2.1.0' )
        ->expectsOutputToContain( "import '@artisanpack-ui/livewire-drag-and-drop';" )
        ->assertSuccessful();
} );

it( 'never edits the host stylesheet or script', function (): void {
    File::ensureDirectoryExists( resource_path( 'css' ) );
    File::ensureDirectoryExists( resource_path( 'js' ) );
    File::put( resource_path( 'css/kanban-install-test.css' ), '@import "tailwindcss";' );
    File::put( resource_path( 'js/kanban-install-test.js' ), "import './bootstrap';" );

    $this->artisan( 'ecommerce-kanban:install' )->assertSuccessful();

    expect( File::get( resource_path( 'css/kanban-install-test.css' ) ) )->toBe( '@import "tailwindcss";' )
        ->and( File::get( resource_path( 'js/kanban-install-test.js' ) ) )->toBe( "import './bootstrap';" );

    File::delete( [ resource_path( 'css/kanban-install-test.css' ), resource_path( 'js/kanban-install-test.js' ) ] );
} );

it( 'reports the engine version', function (): void {
    $this->artisan( 'ecommerce-kanban:install' )
        ->expectsOutputToContain( 'Engine version' )
        ->doesntExpectOutputToContain( 'Could not read the installed artisanpack-ui/ecommerce version' )
        ->assertSuccessful();
} );

it( 'warns when there is no active board and says how to create one', function (): void {
    KanbanBoard::factory()->create( [ 'is_active' => false ] );

    $this->artisan( 'ecommerce-kanban:install' )
        ->expectsOutputToContain( 'There are no active kanban boards yet.' )
        ->expectsOutputToContain( 'Or create one through the REST API: POST' )
        ->assertSuccessful();
} );

it( 'counts the active boards', function (): void {
    KanbanBoard::factory()->count( 2 )->create();

    $this->artisan( 'ecommerce-kanban:install' )
        ->expectsOutputToContain( 'Active boards' )
        ->doesntExpectOutputToContain( 'There are no active kanban boards yet.' )
        ->assertSuccessful();
} );

it( 'explains the polling fallback when broadcasting is off', function (): void {
    config()->set( 'artisanpack.ecommerce.kanban.broadcast', false );
    config()->set( 'broadcasting.default', 'null' );

    $this->artisan( 'ecommerce-kanban:install' )
        ->expectsOutputToContain( 'artisanpack.ecommerce.kanban.broadcast' )
        ->expectsOutputToContain( 'boards refresh every 30 seconds instead' )
        ->expectsOutputToContain( 'ECOMMERCE_KANBAN_BROADCAST=true' )
        ->expectsOutputToContain( 'php artisan install:broadcasting' )
        ->assertSuccessful();
} );

it( 'gives the Echo hint when broadcasting is set up', function (): void {
    config()->set( 'artisanpack.ecommerce.kanban.broadcast', true );
    config()->set( 'broadcasting.default', 'reverb' );

    $this->artisan( 'ecommerce-kanban:install' )
        ->expectsOutputToContain( 'window.Echo' )
        ->doesntExpectOutputToContain( 'refresh every' )
        ->assertSuccessful();
} );
