<?php

namespace CapsuleCmdr\SeatOsmm;

use Illuminate\Support\ServiceProvider;
use Seat\Services\AbstractSeatPlugin;
use Illuminate\Support\Facades\Route;
use CapsuleCmdr\SeatOsmm\Http\Controllers\HomeOverrideController;
use CapsuleCmdr\SeatOsmm\Support\Esi\EsiTokenStorage;
use CapsuleCmdr\SeatOsmm\Support\Esi\SeatRelationTokenStorage;
use Illuminate\Support\Facades\Gate;
use CapsuleCmdr\SeatOsmm\Http\Middleware\OsmmMaintenanceMiddleware;
use Illuminate\Routing\Router;
use Illuminate\Contracts\Http\Kernel;

class OsmmServiceProvider extends AbstractSeatPlugin
{

    protected $listen = [
        \CapsuleCmdr\SeatOsmm\Events\MaintenanceToggled::class => [
            \CapsuleCmdr\SeatOsmm\Listeners\SendMaintenanceToggledAlert::class,
        ],
    ];

    public function boot(): void
    {
        $this->add_routes();
        $this->add_views();
        
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


        #overrides
        $this->app['view']->prependNamespace('web', __DIR__ . '/resources/views/web');
        $this->app['view']->addNamespace('eveseat_web', base_path('vendor/eveseat/web/src/resources/views'));

        Gate::define('osmm.admin', function ($user) {
            // SeAT will map this via its permission system if you also expose it in config.
            return $user->has('osmm.admin');
        });

        Gate::define('osmm.admin', function ($user) {
            // 1. Direct user permission
            if ($user->hasPermission('osmm.admin')) {
                return true;
            }

            // 2. Squad-based permission
            return $user->squads()
                ->whereHas('permissions', fn($q) => $q->where('name', 'osmm.admin'))
                ->exists();
        });

        Gate::define('osmm.maint_bypass', function ($user) {
            if (method_exists($user, 'hasPermission') && $user->hasPermission('osmm.maint_bypass')) {
                return true;
            }
            return $user->squads()
                ->whereHas('permissions', fn($q) => $q->where('name', 'osmm.maint_bypass'))
                ->exists();
        });

        Gate::define('osmm.maint_manage', function ($user) {
            if (method_exists($user, 'hasPermission') && $user->hasPermission('osmm.maint_manage')) {
                return true;
            }
            return $user->squads()
                ->whereHas('permissions', fn($q) => $q->where('name', 'osmm.maint_manage'))
                ->exists();
        });

        // Push as GLOBAL middleware so we know it runs
        // $kernel = $this->app->make(Kernel::class);
        // $kernel->pushMiddleware(OsmmMaintenanceMiddleware::class);

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
        $this->publishes([
            __DIR__.'/resources/assets' => public_path('vendor/capsulecmdr/seat-osmm'),
        ], ['public', 'osmm-assets', 'seat']);
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
        $this->loadTranslationsFrom(__DIR__.'/resources/lang', 'osmm');
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

        //merge notification alerts
        $this->mergeConfigFrom(__DIR__ . '/config/notifications.alerts.php','notifications.alerts');

        $this->add_translations();

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
