<?php

\Log::info('[OSMM] routes.php was loaded.');

use Illuminate\Support\Facades\Route;
use CapsuleCmdr\SeatOsmm\Http\Controllers\HomeOverrideController;
use CapsuleCmdr\SeatOsmm\Http\Controllers\OsmmCalendarController;
use CapsuleCmdr\SeatOsmm\Http\Controllers\TodoController;

// 🟢 Override the homepage root route explicitly
// Route::get('/', [HomeOverrideController::class, 'index'])
//     ->middleware(['web', 'auth'])
//     ->name('home');

// 🔵 Grouped OSMM routes (for future admin UI, etc.)
Route::group([
    'middleware' => ['web', 'auth'],
    'prefix' => 'osmm',
    'as' => 'osmm.',
], function () {
    // Route::get('/home', [HomeOverrideController::class, 'index'])->name('home.index');
});

Route::get('/osmm/calendar/next', [OsmmCalendarController::class, 'next'])
  ->name('osmm.calendar.next')->middleware(['web','auth']);


Route::middleware(['web','auth'])->prefix('osmm')->group(function () {
    Route::get('todos',        [TodoController::class, 'index'])->name('osmm.todos.index');
    Route::post('todos',       [TodoController::class, 'store'])->name('osmm.todos.store');
    Route::delete('todos/{id}',[TodoController::class, 'destroy'])->name('osmm.todos.destroy');
});