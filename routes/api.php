<?php

use App\Http\Controllers\Api\DeviceApiController;
use App\Http\Controllers\Api\DeviceEventController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

/*
 | API perangkat terminal RFID (Tauri edge client), versi v1.
 |
 | - POST /pair      : tukar kode pairing (satu kali pakai) → token.
 |                     Tanpa Bearer token; throttle ketat.
 | - Endpoint lain   : wajib Bearer token terminal + throttle longgar.
 */
Route::prefix('v1/device')->group(function () {
    Route::post('/pair', [DeviceApiController::class, 'pair'])->middleware('throttle:10,1');

    Route::middleware(['terminal', 'throttle:240,1'])->group(function () {
        Route::get('/me', [DeviceEventController::class, 'me']);
        Route::get('/config', [DeviceApiController::class, 'config']);
        Route::post('/heartbeat', [DeviceApiController::class, 'heartbeat']);
        Route::post('/events', [DeviceEventController::class, 'store']);
    });
});
