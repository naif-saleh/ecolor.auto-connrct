<?php

use Illuminate\Http\Request;
use App\Http\Controllers\ApiController;
use App\Http\Controllers\FilesController;
use Illuminate\Support\Facades\Route;

// Public Routes
// Route::get('/', [FilesController::class, 'index']);

// Authenticated Routes (using Sanctum for authentication)
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();

});

// API Routes (inside the 'api' middleware group)
Route::middleware(['api'])->group(function () {
    Route::post('evaluation/', [ApiController::class, 'Evaluation']);

});
