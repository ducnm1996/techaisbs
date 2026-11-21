<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\PortalController;
use App\Http\Controllers\SessionController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest:web')->group(function (): void {
    Route::view('/login', 'login')->name('login');
    Route::post('/login', [SessionController::class, 'login'])->middleware('throttle:login');
    Route::view('/password/forgot', 'forgot')->name('password.request');
    Route::post('/password/forgot', [SessionController::class, 'sendLink'])->middleware('throttle:password')->name('password.email');
    Route::view('/password/setup', 'reset')->name('password.reset');
    Route::post('/password/setup', [SessionController::class, 'reset'])->middleware('throttle:password')->name('password.update');
});
Route::middleware(['auth:web', 'portal'])->group(function (): void {
    Route::post('/logout', [SessionController::class, 'logout'])->name('logout');
    Route::get('/2fa', [SessionController::class, 'mfa'])->name('mfa.show');
    Route::post('/2fa', [SessionController::class, 'confirmMfa'])->middleware('throttle:mfa')->name('mfa.confirm');
    Route::get('/', [PortalController::class, 'home'])->name('home');
    Route::get('/history', [PortalController::class, 'history'])->name('history');
    Route::post('/history/search', [PortalController::class, 'search'])->name('history.search');
    Route::get('/history/{event}', [PortalController::class, 'show'])->whereNumber('event')->name('history.show');
    Route::delete('/history/{event}', [PortalController::class, 'delete'])->whereNumber('event')->name('history.delete');
    Route::get('/devices', [PortalController::class, 'devices'])->name('devices');
    Route::post('/devices/pair', [PortalController::class, 'createPair'])->middleware('throttle:pair-create')->name('devices.pair');
    Route::delete('/devices/{device}', [PortalController::class, 'revoke'])->whereNumber('device')->name('devices.revoke');
    Route::prefix('admin')->middleware('admin')->group(function (): void {
        Route::get('/', [AdminController::class, 'index'])->name('admin');
        Route::post('/accounts', [AdminController::class, 'create'])->middleware('throttle:password')->name('admin.create');
        Route::put('/accounts/{user}', [AdminController::class, 'update'])->whereNumber('user')->name('admin.update');
        Route::post('/accounts/{user}/invite', [AdminController::class, 'invite'])->middleware('throttle:password')->whereNumber('user')->name('admin.invite');
    });
});
