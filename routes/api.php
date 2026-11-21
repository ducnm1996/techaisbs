<?php

use App\Http\Controllers\DeviceApiController;
use Illuminate\Support\Facades\Route;

Route::post('/pair', [DeviceApiController::class, 'pair'])->middleware('throttle:pair');
Route::post('/events', [DeviceApiController::class, 'events'])->middleware(['auth:sanctum', 'device.token', 'throttle:device-events']);
