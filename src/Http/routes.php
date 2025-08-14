<?php

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

use CapsuleCmdr\SeatOsmm\Http\Controllers\HomeOverrideController;
use CapsuleCmdr\SeatOsmm\Http\Controllers\OsmmCalendarController;
use CapsuleCmdr\SeatOsmm\Http\Controllers\TodoController;
use CapsuleCmdr\SeatOsmm\Http\Controllers\Config\BrandingController;

Log::info('[OSMM] routes.php loaded');

// All OSMM routes share /osmm prefix and web+auth middleware
Route::middleware(['web', 'auth'])
    ->prefix('osmm')
    ->as('osmm.')
    ->group(function () {

        // Calendar
        Route::get('/calendar/next', [OsmmCalendarController::class, 'next'])
            ->name('calendar.next');

        // Todos
        Route::get('/todos', [TodoController::class, 'index'])->name('todos.index');
        Route::post('/todos', [TodoController::class, 'store'])->name('todos.store');
        Route::delete('/todos/{id}', [TodoController::class, 'destroy'])->name('todos.destroy');

        // Branding (admin‑gated)
        Route::middleware('can:osmm.admin')->group(function () {
            Route::get('/config/branding', [BrandingController::class, 'index'])
                ->name('config.branding');

            // Use PUT for update so your Blade can call route('osmm.config.branding.update')
            Route::put('/config/branding', [BrandingController::class, 'save'])
                ->name('config.branding.update');
        });

        // (Optional) If you later want a custom home:
        // Route::get('/home', [HomeOverrideController::class, 'index'])->name('home.index');
    });

// Public manifest (no auth; referenced in <head>)
Route::get('/osmm/manifest.json', [BrandingController::class, 'manifest'])
    ->name('osmm.manifest');
