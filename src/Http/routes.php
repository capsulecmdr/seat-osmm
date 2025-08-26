<?php

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

use CapsuleCmdr\SeatOsmm\Http\Controllers\HomeOverrideController;
use CapsuleCmdr\SeatOsmm\Http\Controllers\OsmmCalendarController;
use CapsuleCmdr\SeatOsmm\Http\Controllers\TodoController;
use CapsuleCmdr\SeatOsmm\Http\Controllers\Config\BrandingController;
use CapsuleCmdr\SeatOsmm\Http\Controllers\OsmmMenuController;

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

    Route::middleware(['web','auth']) // add your own guards/permissions (e.g., can:osmm.admin)
    ->prefix('osmm/menu')
    ->name('osmm.menu.')
    ->group(function () {
        Route::get('/',             [OsmmMenuController::class, 'index'])->name('index');

        // JSON feeds
        Route::get('/native.json',  [OsmmMenuController::class, 'jsonNative'])->name('native');
        Route::get('/merged.json',  [OsmmMenuController::class, 'jsonMerged'])->name('merged');
        Route::get('/overrides.json',[OsmmMenuController::class, 'jsonOverrides'])->name('overrides');

        // CRUD
        Route::post('/parent/upsert', [OsmmMenuController::class, 'upsertParent'])->name('parent.upsert');
        Route::post('/child/upsert',  [OsmmMenuController::class, 'upsertChild'])->name('child.upsert');
        Route::delete('/delete',      [OsmmMenuController::class, 'delete'])->name('delete');
        Route::post('/reset',         [OsmmMenuController::class, 'resetAll'])->name('reset');

        Route::post('/menu-mode', [OsmmMenuController::class, 'saveMenuOverride'])->name('save-mode');
    });