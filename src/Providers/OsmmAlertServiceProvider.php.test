<?php

namespace CapsuleCmdr\SeatOsmm\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class OsmmAlertServiceProvider extends ServiceProvider
{
    protected $listen = [
        \CapsuleCmdr\SeatOsmm\Events\MaintenanceToggled::class => [
            \CapsuleCmdr\SeatOsmm\Listeners\SendMaintenanceToggledAlert::class,
        ],
    ];

    public function boot(): void
    {
        parent::boot();
    }
}
