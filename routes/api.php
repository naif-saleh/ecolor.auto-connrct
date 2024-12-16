<?php

use Illuminate\Http\Request;
use App\Http\Controllers\ApiController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FilesController;
use App\Http\Controllers\SettingController;

Route::get('/files', [FilesController::class, 'index']);
Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');


Route::middleware(['auth:sanctum'])->group(function () {
    // Providers..............................................................................................................
    Route::get('providers', [ApiController::class, 'getProviders']);
    // Provider By Name.............................................................................................................
    Route::get('providers/{name}', [ApiController::class, 'getProviderByName']);
    // Settings............................................................................................................
    Route::get('settings', [SettingController::class, 'getCfdApi']);
    // all Auto Dailers.....................................................................................................
    Route::get('auto-dailer ', [ApiController::class, 'autoDailer']);
    // Auto Dailer Update State.............................................................................................
    Route::post('auto-dailer/{id}', [ApiController::class, 'updateState']);
    // all Auto Distributer.....................................................................................................
    Route::get('auto-distributer', [ApiController::class, 'autoDistributer']);
    // all Auto Distributer Update State.....................................................................................................
    Route::post('auto-distributer/{id}', [ApiController::class, 'autoDistributerUpdateState']);
});
