<?php 
use Illuminate\Support\Facades\Route;

Route::group([
    'middleware' => ['web', 'auth'], // 'locale' is recommended in SeAT , 'locale'
    'prefix' => 'osmm',
    'as' => 'osmm.',
], function () {
    //routes here
    Route::get('/home',[HomeOverrideController::class, 'index'])->name('home.index');
});