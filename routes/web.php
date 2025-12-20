<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\ChannelCategoryController;
use App\Http\Controllers\ChannelController;
use App\Http\Controllers\ChannelHistoryController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ListController;
use App\Http\Controllers\UserController;
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
Route::post('/upload-m3u', [ChannelController::class, 'uploadM3U'])->name('upload.m3u');
Route::get('/importar-categorias', [ChannelController::class, 'importCategories'])->name('import.categories');
Route::get('/importar-canales', [ChannelController::class, 'importChannels'])->name('import.channels');


Route::prefix('accounts')->controller(AccountController::class)->group(function() {
    Route::get('/', 'index')->name('accounts.index');
    Route::get('/create', 'create')->name('accounts.create');
    Route::get('/edit/{account}', 'edit')->name('accounts.edit');
    Route::post('/', 'store')->name('accounts.store');
    Route::put('/{account}', 'update')->name('accounts.update');
    Route::post('/generate-token/{account}', 'generateToken')->name('accounts.generate-token');
});

Route::prefix('users')->controller(UserController::class)->group(function() {
    Route::get('/', 'index')->name('users.index');
    Route::get('/create', 'create')->name('users.create');
    Route::get('/edit/{user}', 'edit')->name('users.edit');
    Route::post('/', 'store')->name('users.store');
    Route::put('/{user}', 'update')->name('users.update');
    Route::delete('/{user}', 'destroy')->name('users.destroy');
});

Route::prefix('lists')->controller(ListController::class)->group(function() {
    Route::post('/update', 'update')->name('lists.update');
    Route::get('/tivimate/{folder}', 'downloadTivimate')->name('lists.download.tivimate');
    Route::get('/ott/{folder}', 'downloadOtt')->name('lists.download.ott');
	Route::get('/cine/{folder}', 'downloadCine')->name('lists.download.cine');
    Route::get('/series/{folder}', 'downloadSeries')->name('lists.download.series');
	Route::get('/cineOtt/{folder}', 'downloadCineOtt')->name('lists.download.cineott');
    Route::get('/seriesOtt/{folder}', 'downloadSeriesOtt')->name('lists.download.seriesott');
    Route::get('/kodi/{folder}', 'downloadKodi')->name('lists.download.kodi');
});

Route::prefix('channel-categories')->controller(ChannelCategoryController::class)->group(function() {
    Route::get('/', 'index')->name('channel-categories.index');
    Route::get('/create', 'create')->name('channel-categories.create');
    Route::get('/edit/{category}', 'edit')->name('channel-categories.edit');
    Route::post('/', 'store')->name('channel-categories.store');
    Route::put('/{category}', 'update')->name('channel-categories.update');
    Route::delete('/{category}', 'destroy')->name('channel-categories.destroy');
    Route::post('/reorder', 'reorder')->name('channel-categories.reorder');
});

Route::prefix('channels')->controller(ChannelController::class)->group(function() {
    Route::get('/', 'index')->name('channels.index');
    Route::get('/create', 'create')->name('channels.create');
    Route::get('/edit/{channel}', 'edit')->name('channels.edit');
    Route::post('/', 'store')->name('channels.store');
    Route::put('/{channel}', 'update')->name('channels.update');
    Route::delete('/{channel}', 'destroy')->name('channels.destroy');
    Route::post('/reorder', 'reorder')->name('channels.reorder');
    Route::post('/duplicate/{channel}', 'duplicate')->name('channels.duplicate');
});

Route::get('/logs', [ChannelHistoryController::class, 'index'])->name('logs.index');


