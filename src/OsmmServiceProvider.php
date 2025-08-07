<?php

namespace CapsuleCmdr\SeatOsmm;

use Illuminate\Support\ServiceProvider;
use Seat\Services\AbstractSeatPlugin;

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
            Route::get('/', [Http\Controllers\HomeOverrideController::class, 'index'])
                ->middleware(['web', 'auth'])
                ->name('home');
        });


        config([
            'osmm.home_elements' => array_merge(config('osmm.home_elements', []), [
                [
                    'order' => 1,
                    'html' => view('seat-osmm::partials.test-widget')->render(),
                ],
            ]),
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
