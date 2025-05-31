<?php

use App\Http\Controllers\HomeController;
use App\Http\Controllers\ListController;
use App\Http\Controllers\TokenController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Auth::routes(['register' => false, 'reset' => false]);

Route::get('/', [HomeController::class, 'index'])->name('home');

Route::prefix('token')->controller(TokenController::class)->group(function() {
    Route::get('/edit', 'edit')->name('tokens.edit');
    Route::post('/', 'store')->name('tokens.store');
});

Route::prefix('lists')->controller(ListController::class)->group(function() {
    Route::get('/edit', 'edit')->name('lists.edit');
    Route::post('/update', 'update')->name('lists.update');
    Route::get('/tivimate', 'downloadTivimate')->name('lists.download.tivimate');
    Route::get('/ott', 'downloadOtt')->name('lists.download.ott');
});
