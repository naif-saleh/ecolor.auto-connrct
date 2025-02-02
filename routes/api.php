<?php

use Illuminate\Http\Request;
use App\Http\Controllers\ApiController;
use Illuminate\Support\Facades\Route;

/**
 * Authenticated Routes (Protected by Sanctum)
 *
 * This route retrieves the authenticated user's details.
 * It is protected using Laravel Sanctum, ensuring that only
 * authenticated requests with a valid token can access it.
 *
 * Endpoint:
 * - GET /api/user → Returns the authenticated user instance.
 */
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});


/**
 * API Routes within the 'api' middleware group.
 *
 * This route group ensures that all enclosed routes are processed
 * through Laravel's 'api' middleware, which provides features such as
 * authentication, rate limiting, and optimized stateless request handling.
 *
 * Routes:
 * - POST /evaluation → Handles evaluation submissions via ApiController.
 */
Route::middleware(['api'])->group(function () {
    Route::post('evaluation', [ApiController::class, 'evaluation']);
});
