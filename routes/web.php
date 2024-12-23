<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\AutoDailerController;
use App\Http\Controllers\AutoDirtibuterController;
use App\Http\Controllers\ProviderController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ApiController;
use App\Http\Controllers\ReportController;


Route::get('/', function () {
    return view('welcome');
});

Route::get('/', [HomeController::class, 'index'])->name('home');





// API Endpoints Sanctum Provider...........................................................................................................
// Route::middleware('auth:sanctum')->group(function () {
//     // Settings......................................................................................................

//     // Providers routes..............................................................................................
//     Route::get('api/providers', [ApiController::class, 'getProviders']);
//     Route::get('api/providers/{name}', [ApiController::class, 'getByName']);

//     // AutoDistributer routes.........................................................................................
//     Route::get('api/auto-distributer', [ApiController::class, 'autoDistributet']);
//     Route::put('api/auto-distributer', [ApiController::class, 'autoDistributerUpdateState']);
//     // Users......................................................................................................
//     Route::get('api/users', [ApiController::class, 'getUsers']);

// });

// AutoDailer routes..............................................................................................
// Route::get('api/auto-dailer', [ApiController::class, 'autoDailer']);
// Route::middleware(['api', 'auth:sanctum'])->group(function () {
//     Route::put('/api/auto-dailer-data/{id}/state', [ApiController::class, 'updateState']);
// });

// Route::get('api/auto-dailer/{id}', [ApiController::class, 'autoDailerShowState']);
Route::get('settings/json', [SettingController::class, 'getCfdApi'])->name('settings.getCfdApi')->middleware(['auth']);
// User Management..........................................................................................................................
Route::middleware(['auth', 'admin'])->group(function () {
    Route::get('users', [UserController::class, 'index'])->name('users.index');
    Route::get('users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
    Route::put('users/{user}', [UserController::class, 'update'])->name('users.update');
    Route::delete('users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
    Route::get('users/create', [UserController::class, 'create'])->name('users.create');
    Route::post('users', [UserController::class, 'store'])->name('users.store');

    // Dailer Calling..........................................................................................................................
    Route::get('auto-dailer-call', [ApiController::class, 'autoDailer']);
    Route::get('auto-dailer-call-click', [ApiController::class, 'autoDailerByClick'])->name('auto_dailer.call.click');
    // Distributer Calling.....................................................................................................................
    Route::get('auto-distributer-call', [ApiController::class, 'autoDistributer']);
    Route::get('auto-distributer-call-click', [ApiController::class, 'autoDistributerByClicking'])->name('auto_distributer.call.click');
    // Acitvity Log Report....................................................................................................................
    Route::get('report/user-activity-report', [ReportController::class, 'activityReport'])->name('users.activity.report');
    // Auto Dailer Reports....................................................................................................................
    Route::get('auto-dailer-report', [ReportController::class, 'AutoDailerReports'])->name('auto_dailer.report');
    // Export Auto Dailer...........................................................................................................
    Route::get('auto-dailer-report/export', [ReportController::class, 'exportAutoDailerReport'])->name('auto_dailer.report.export');
    // Auto Distributer Reports....................................................................................................................
    Route::get('auto-distributer-report', [ReportController::class, 'AutoDistributerReports'])->name('auto_distributer.report');
    // Export Auto Distributer...........................................................................................................
    Route::get('auto-distributer-report/export', [ReportController::class, 'exportAutoDistributerReport'])->name('auto_distributer.report.export');
});



// Dashboard...................................................................................................................................
Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware(['auth'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Settings............................................................................................................................
    Route::get('/settings', [SettingController::class, 'showForm'])->name('settings.form');
    Route::post('/settings', [SettingController::class, 'saveSettings'])->name('settings.save');
    // AutoDailers............................................................................................................................
    Route::resource('autodailers', AutoDailerController::class);
    Route::get('/auto_dailer/{id}/download', [AutoDailerController::class, 'download'])->name('auto_dailer.download');
    // AutoDistributers............................................................................................................................
    Route::resource('autodistributers', AutoDirtibuterController::class);
    Route::get('/auto_distributers/{id}/download', [AutoDirtibuterController::class, 'download'])->name('auto_distributers.download');
    // Providers............................................................................................................................
    Route::resource('providers', ProviderController::class);
});

require __DIR__ . '/auth.php';
