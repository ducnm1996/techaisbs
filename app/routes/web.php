<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DiaryController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::view('/login', 'auth', ['register' => false])->name('login');
    Route::view('/register', 'auth', ['register' => true])->name('register');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:6,1');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:4,1');
});
Route::middleware('auth')->group(function () {
    Route::get('/', [DiaryController::class, 'dashboard'])->name('dashboard');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::view('/profile', 'profile')->name('profile');
    Route::put('/profile', [DiaryController::class, 'profile']);
    Route::put('/baby-growth', [DiaryController::class, 'growth'])->name('baby.growth');
    Route::get('/attachments/{id}', [DiaryController::class, 'attachment'])->whereNumber('id')->name('attachment');
    Route::get('/diary-images/{id}/{photo}', [DiaryController::class, 'photo'])
        ->whereNumber('id')
        ->where('photo', '[a-zA-Z0-9]{40}\.(jpg|jpeg|png|webp)')
        ->name('journal.photo');
    Route::prefix('/diary/{kind}')->where(['kind' => 'meals|checkups'])->group(function () {
        Route::get('/', [DiaryController::class, 'index'])->name('entries.index');
        Route::get('/create', [DiaryController::class, 'form'])->name('entries.create');
        Route::post('/', [DiaryController::class, 'save'])->name('entries.store');
        Route::get('/{id}/edit', [DiaryController::class, 'form'])->whereNumber('id')->name('entries.edit');
        Route::put('/{id}', [DiaryController::class, 'save'])->whereNumber('id')->name('entries.update');
        Route::delete('/{id}', [DiaryController::class, 'destroy'])->whereNumber('id')->name('entries.destroy');
    });
});
