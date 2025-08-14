<?php

namespace CapsuleCmdr\SeatOsmm;

use Illuminate\Support\ServiceProvider;
use Seat\Services\AbstractSeatPlugin;
use Illuminate\Support\Facades\Route;
use CapsuleCmdr\SeatOsmm\Http\Controllers\HomeOverrideController;
use CapsuleCmdr\SeatOsmm\Support\Esi\EsiTokenStorage;
use CapsuleCmdr\SeatOsmm\Support\Esi\SeatRelationTokenStorage;
use Illuminate\Support\Facades\Gate;
use Seat\Services\Menu\Facade\Menu;

class OsmmServiceProvider extends AbstractSeatPlugin
{

    public function boot(): void
    {
        $this->add_routes();
        $this->add_views();
        $this->add_translations();
        
        $this->addPublications();
        
        $this->addMigrations();

        // ⏱ Delay route override until all other providers are booted
        app()->booted(function () {
            Route::get('/', [HomeOverrideController::class, 'index'])
                ->middleware(['web', 'auth'])
                ->name('seatcore::home');

            Route::get('/home', [HomeOverrideController::class, 'index'])
                ->middleware(['web', 'auth'])
                ->name('osmm.home'); // optional
        });

        $widgets = [];

        // Helper to push widgets into config array
        $addWidgets = function ($row, $count) use (&$widgets) {
            for ($i = 1; $i <= $count; $i++) {
                $widgets[] = [
                    'row'   => $row,
                    'order' => $i,
                    'html'  => view('seat-osmm::partials.test-widget', [
                        'title' => "Test Widget R{$row}-#{$i}",
                        'text'  => "This is test widget #{$i} in row {$row}.",
                    ])->render(),
                ];
            }
        };

        // Row 1 - 8 widgets
        $addWidgets(1, 8);

        // Row 2 - 5 widgets
        $addWidgets(2, 5);

        // Row 3 - 4 widgets
        $addWidgets(3, 4);

        // Row 4 - 3 widgets
        $addWidgets(4, 3);

        config([
            'osmm.home_elements' => $widgets
        ]);


        #overrides
        $this->app['view']->prependNamespace('web', __DIR__ . '/../../resources/views/web');
        $this->app['view']->addNamespace('eveseat_web', base_path('vendor/eveseat/web/src/resources/views'));

        Gate::define('osmm.admin', function ($user) {
            // SeAT will map this via its permission system if you also expose it in config.
            return $user->has('osmm.admin');
        });

        Menu::add('OSMM Config', [
            'icon'       => 'fa fa-cogs',
            'route'      => 'osmm.config.branding',
            'permission' => 'osmm.admin',
            'parent'     => 'tools',   // places it under "SeAT Tools"
            'order'      => 999,       // near the bottom of Tools
        ]);
    }
    
    private function addPublications(): void
    {
        // $this->publishes([
        //     __DIR__.'/Config/fitting.exportlinks.php' => config_path('fitting.exportlinks.php'),
        // ],
        //     ['config', 'seat'],
        // );

        // $this->publishes([
        //     __DIR__.'/resources/assets/css' => public_path('web/css'),
        //     __DIR__.'/resources/assets/js' => public_path('web/js'),
        // ]);
    }

    private function add_routes(): void
    {
        if (! $this->app->routesAreCached()) {
            include __DIR__.'/Http/routes.php';
        }
    }

    private function add_commands(): void
    {
        // $this->commands([
        //     UpgradeFits::class,
        // ]);
    }

    private function add_translations(): void
    {
        // $this->loadTranslationsFrom(__DIR__.'/resources/lang', 'osmm');
    }

    private function add_views(): void
    {
        $this->loadViewsFrom(__DIR__.'/resources/views', 'seat-osmm');
    }

    /**
     * Register bindings and configuration.
     */
    public function register(): void
    {
        //merge config
        $this->mergeConfigFrom(__DIR__.'/config/config.php','osmm');

        //merge sidebar
        $this->mergeConfigFrom(__DIR__.'/config/sidebar.php','package.sidebar');

        //register permissions
        $this->registerPermissions(__DIR__ . '/config/Permissions/permissions.php','osmm');

        $this->app->bind(EsiTokenStorage::class, fn () => new SeatRelationTokenStorage());
    }

    private function addMigrations()
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations/');
    }



    /**
     * Required metadata for SeAT Plugin Loader.
     */
    public function getName(): string
    {
        return 'SeAT-OSMM';
    }

    public function getPackagistVendorName(): string
    {
        return 'capsulecmdr';
    }

    public function getPackagistPackageName(): string
    {
        return 'seat-osmm';
    }

    public function getPackageRepositoryUrl(): string
    {
        return 'https://github.com/capsulecmdr/seat-osmm';
    }
}
