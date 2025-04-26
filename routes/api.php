<?php

use Illuminate\Http\Request;
use App\Http\Controllers\ApiController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AutoDistributerByUser\AdistFeedController;
use App\Http\Controllers\Webhook\AdialWebhookController;
use App\Http\Controllers\Webhook\AdistWebhookController;

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


// Route::middleware('api')->group(function () {
//     Route::get('today-feeds', [ADistFeedController::class, 'getTodayFeeds']);
//     Route::post('update-feed-status', [ADistFeedController::class, 'updateFeedStatus']);
//     Route::post('delete-feeds', [ADistFeedController::class, 'deleteFeeds']);
// });

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


Route::post('/licens.ecolor/allow-license-key', [ApiController::class, 'PostLicen'])->name('PostLicen');


// Webhook routes Auto Distributor
Route::prefix('auto-distributor/webhooks')->group(function () {
    Route::post('numbers', [AdistWebhookController::class, 'receive'])->middleware(['throttle:WebhookPostData']);
    Route::get('numbers/status/{batchId}', [AdistWebhookController::class, 'checkStatus']);
});



// Webhook routes Auto Distributor
Route::prefix('auto-dialer/webhooks')->group(function () {
    Route::post('numbers', [AdialWebhookController::class, 'receive'])->middleware(['throttle:WebhookPostData']);
    Route::get('numbers/status/{batchId}', [AdialWebhookController::class, 'checkStatus']);
});
