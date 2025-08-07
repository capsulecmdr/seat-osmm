<?php 
use Illuminate\Support\Facades\Route;
use CapsuleCmdr\SeatOsmm\Http\Controllers\HomeOverrideController;

// 🟢 Override the homepage root route explicitly
Route::get('/', [HomeOverrideController::class, 'index'])
    ->middleware(['web', 'auth'])
    ->name('home');

// 🔵 Grouped OSMM routes (for future admin UI, etc.)
Route::group([
    'middleware' => ['web', 'auth'],
    'prefix' => 'osmm',
    'as' => 'osmm.',
], function () {
    // Route::get('/home', [HomeOverrideController::class, 'index'])->name('home.index');
});