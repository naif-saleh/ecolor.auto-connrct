<?php

use App\Http\Controllers\AutoDailerByProvider\ADialProviderFeedController;
use App\Http\Controllers\AutoDistributerByUser\ADistAgentFeedController;
use App\Http\Controllers\AutoDistributerByUser\AdistFeedController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ManagerReportController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\Webhook\AdialWebhookController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/**
 * Root Route Handling
 *
 * This route determines the landing page based on the user's authentication
 * status and role. If authenticated, the user is redirected to the appropriate
 * dashboard based on their role. Otherwise, the login page is displayed.
 *
 * Redirect Rules:
 * - SuperUser/Admin → Redirects to /auto-dialer/files.
 * - Manager → Redirects to the manager dashboard.
 * - Regular User → Redirects to the evaluation page.
 * - Unauthenticated → Shows the login page.
 */
Route::get('/', function () {
    if (Auth::check()) {
        $user = Auth::user();
        if ($user->isSuperUser() || $user->isAdmin() || $user->isUser() || $user->isManagerUser()) {
            return redirect()->route('index.page'); // Redirect if Admin/Superuser
        }

        // if ($user->isManagerUser()) {
        //     return redirect()->route('index.page');
        // }

        // if ($user->isUser()) {
        //     return redirect()->route('index.page');
        // }
    }

    return view('auth.login');
});

// Ensure the index route is defined outside the condition

Route::get('/error', function () {
    return view('404');
});

// User Management..........................................................................................................................
Route::middleware(['auth', 'admin'])->group(function () {
    Route::get('/home', [HomeController::class, 'index'])->name('index.page');
    /**
     * User Management Routes
     *
     * These routes handle various operations related to user management,
     * including listing, creating, updating, editing, and deleting users.
     * Additionally, it includes functionality for resetting user passwords.
     *
     * Routes:
     * - GET /users → List all users.
     * - GET /users/{user}/edit → Show edit form for a specific user.
     * - PUT /users/{user} → Update a specific user.
     * - DELETE /users/{user} → Delete a specific user.
     * - POST /users/reset-password → Reset the password for a user.
     * - GET /users/create → Show user creation form.
     * - POST /users → Store a new user.
     */
    Route::get('users', [UserController::class, 'index'])->name('users.system.index');
    Route::get('users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
    Route::put('users/{user}', [UserController::class, 'update'])->name('users.update');
    Route::delete('users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
    Route::post('users/reset-password', [UserController::class, 'resetPassword'])->name('users.reset.password');
    Route::get('users/create', [UserController::class, 'create'])->name('users.create');
    Route::post('users', [UserController::class, 'store'])->name('users.store');

    // Settings....
    Route::get('/settings/update-time-calls', [SettingsController::class, 'index'])->name('settings.index');
    Route::post('/settings', [SettingsController::class, 'updateBlockTime'])->name('settings.update');

    Route::get('/settings/update-count-calls', [SettingsController::class, 'indexCountCall'])->name('settings.indexCountNumbers');

    // License
    Route::get('license/manage', [SettingsController::class, 'licenseForm'])->name('licen.index');

    /**
     * Auto Dialer Provider Routese
     *
     * These routes manage auto dialer providers and their related feeds/files.
     * Functionality includes listing, creating, storing, updating, and deleting
     * provider records, as well as managing associated feeds.
     *
     * Routes:
     * - GET /providers → List all providers.
     * - POST /provider/store → Store a new provider.
     * - GET /provider/create → Show provider creation form.
     * - GET /providers/{provider}/feed/create → Show feed creation form for a provider.
     * - POST /providers/{provider}/feed/store → Store a new feed for a provider.
     * - GET /providers/{provider}/feeds → List feeds for a specific provider.
     * - DELETE /autodailer/{slug} → Delete a provider.
     * - PUT /auto-dailer/{slug} → Update a provider.
     * - GET /providers/feeds/{slug} → Show file content of a feed.
     * - POST /providers/feeds/{slug}/allow → Update allow status for a feed.
     */
    Route::get('/providers', [ADialProviderFeedController::class, 'index'])->name('providers.index');
    Route::post('/provider/store', [ADialProviderFeedController::class, 'store'])->name('providers.store');
    Route::get('/provider/create', [ADialProviderFeedController::class, 'create'])->name('providers.create');
    Route::put('/providers/{id}/update', [ADialProviderFeedController::class, 'updateProvider'])->name('providers.update');
    Route::delete('/providers/{provider}', [ADialProviderFeedController::class, 'destroyProvider'])->name('providers.delete');
    Route::get('/providers/{provider}/feed/create', [ADialProviderFeedController::class, 'createFile'])->name('provider.files.create');
    Route::post('/providers/{provider}/feed/store', [ADialProviderFeedController::class, 'storeFile'])->name('provider.files.store');
    Route::get('/providers/{provider}/feeds', [ADialProviderFeedController::class, 'files'])->name('provider.files.index');
    Route::delete('/auto-dailer/{slug}', [ADialProviderFeedController::class, 'destroy'])->name('autodailer.delete');
    Route::put('/auto-dailer/{slug}', [ADialProviderFeedController::class, 'update'])->name('autoDailer.update');
    Route::get('/providers/feeds/{slug}', [ADialProviderFeedController::class, 'showFileContent'])->name('provider.files.show');
    Route::post('/providers/feeds/{slug}/allow', [ADialProviderFeedController::class, 'updateAllowStatus'])->name('autodailers.files.allow');
    // Import CSV File Using Drop Zone
    Route::post('/providers/feeds/upload-file', [ADialProviderFeedController::class, 'importCsvData'])->name('autodailers.file.csv.dropzone.upload');
    // Download File Numbers
    Route::get('provider/{provider}/files/{file}/download', [ADialProviderFeedController::class, 'downloadFileData'])->name('provider.files.download');

    /**
     * Auto Distributor User Routes
     *
     * These routes handle agent-related actions within the auto distributor system.
     * Functionality includes listing agents, managing their feeds/files, and
     * updating file statuses.
     *
     * Routes:
     * - GET /agents → List all agents.
     * - GET /agents/{agent}/feeds → List feeds for a specific agent.
     * - GET /agents/feeds/{slug} → Show file content of a feed.
     * - GET /agents/{agent}/feed/create → Show feed creation form for an agent.
     * - POST /agents/{agent}/feed/store → Store a new feed for an agent.
     * - POST /agents/feeds/{slug}/allow → Update allow status for an agent's feed.
     */


    Route::prefix('auto-distributor')->name('auto-distributor.')->group(function () {
        Route::get('/', [ADistAgentFeedController::class, 'index'])->name('index');
        Route::post('/toggle-agent', [ADistAgentFeedController::class, 'toggleAgentStatus'])->name('toggle-agent');
        // Other existing routes...
    });
    Route::get('/agents', [ADistAgentFeedController::class, 'index'])->name('users.index');
    Route::get('/agents/{agent}/feeds', [ADistAgentFeedController::class, 'files'])->name('users.files.index');
    Route::get('/agents/feeds/{slug}', [ADistAgentFeedController::class, 'showFileContent'])->name('users.files.show');
    Route::get('/agents/{agent}/feed/create', [ADistAgentFeedController::class, 'createFile'])->name('users.files.create');
    Route::post('/agents/{agent}/feed/store', [ADistAgentFeedController::class, 'storeFile'])->name('users.files.store');
    Route::post('/agents/feeds/{slug}/allow', [ADistAgentFeedController::class, 'updateAllowStatus'])->name('users.files.allow');
    Route::put('/agents/feed/{slug}', [ADistAgentFeedController::class, 'update'])->name('users.feed.update');
    Route::delete('/agent/feed/{slug}', [ADistAgentFeedController::class, 'destroy'])->name('users.feed.delete');
    // Import CSV File Using Drop Zone
    Route::post('/agents/feeds/upload-file', [ADistAgentFeedController::class, 'importCsvData'])->name('users.file.csv.dropzone.upload');
    Route::get('agent/files/{slug}/download-skipped-numbers', [ADistAgentFeedController::class, 'downloadSkippedNumbers'])->name('users.files.downloadSkippedNumbers');
    // Download File Numbers
    Route::get('agent/{agent}/files/{file}/download', [ADistAgentFeedController::class, 'downloadFileData'])->name('agent.files.download');

    Route::get('/today-feeds', [ADistFeedController::class, 'getTodayFeeds']);
    Route::post('/update-feed-status', [ADistFeedController::class, 'updateFeedStatus']);
    Route::post('/delete-feeds', [ADistFeedController::class, 'deleteFeeds']);

    // Manager Reports...................................................................................................................

    // Auto Dailer Extensios...............
    Route::get('manager/auto-dailer/report-compaign', [ManagerReportController::class, 'dialerReportsCompaign'])->name('manager.dailer.report.compaign');
    // Auto Dailer Providers...............
    Route::get('manager/auto-dailer/report-providers', [ManagerReportController::class, 'dialerReportsProviders'])->name('manager.dailer.report.providers');
    // Auto Distributer Providers...............
    Route::get('manager/auto-distributor/report-providers', [ManagerReportController::class, 'distributorReportsProviders'])->name('manager.autodistributor.report.providers');
    // Auto Distributer Compagin...............
    Route::get('manager/auto-distributor/report-compagin', [ManagerReportController::class, 'distributorReportsCompaign'])->name('manager.autodistributor.report.compaign');

    // // Dashboard Statistics....................................................................................................................
    Route::get('/dashboard-calls', [DashboardController::class, 'index'])->name('calls.dashboard');
    // // Acitvity System Log Report....................................................................................................................
    Route::get('report/system-log-report', [ReportController::class, 'activityReport'])->name('system.activity.report');
    // // Acitvity User Log Report....................................................................................................................
    Route::get('report/user-log-report', [ReportController::class, 'UserActivityReport'])->name('users.activity.report');
    // // Auto Dailer Reports....................................................................................................................
    Route::get('auto-dailer-report', [ReportController::class, 'AutoDailerReports'])->name('auto_dailer.report');
    // // Export Auto Dailer...........................................................................................................
    Route::get('auto-dailer-report/export', [ReportController::class, 'exportAutoDailerReport'])->name('auto_dailer.report.export');
    // // Not Called Number Auto Dailer...........................................................................................................
    Route::get('auto-dailer-report/not-called-numbers', [ReportController::class, 'dialnotCalledNumbers'])->name('auto_dailer.report.notCalled');
    // // Export Not Called Number Auto Dailer...........................................................................................................
    Route::get('dial/not-called/export-today-csv', [ReportController::class, 'dialexportTodayNotCalledCSV'])
        ->name('auto_dailer.report.notCalled.exportTodayCSV');

    // // Auto Distributer Reports....................................................................................................................
    Route::get('auto-distributer-report', [ReportController::class, 'AutoDistributerReports'])->name('auto_distributer.report');
    // // Export Auto Distributer...........................................................................................................
    Route::get('auto-distributer-report/export', [ReportController::class, 'exportAutoDistributerReport'])->name('auto_distributer.report.export');
    // // Not Called Number Auto Distributer...........................................................................................................
    Route::get('auto-distributer-report/not-called-numbers', [ReportController::class, 'distNotCalledNumbers'])->name('auto_distributer.report.notCalled');
    // // Export Not Called Number Auto Distributer...........................................................................................................
    Route::get('dist/not-called/export-today-csv', [ReportController::class, 'distexportTodayNotCalledCSV'])
        ->name('auto_distributer.report.notCalled.exportTodayCSV');

    //    Manager Dashboard........................................................................................................
    Route::get('manager/dashboard', [DashboardController::class, 'getCallManagerStatisticsAutoDailer'])->name('manager.dashboard');

    // Evaluation..............................................................................................................
    // Auto Dailer
    Route::get('reports/evaluation', [ReportController::class, 'Evaluation'])->name('evaluation');
    Route::get('reports/evaluation/export', [ReportController::class, 'exportEvaluation'])->name('evaluation.export');
});

// Dashboard...................................................................................................................................
// Route::get('/dashboard', function () {
//     return view('welcome');
// })->middleware(['auth', 'verified'])->name('index.page');

Route::middleware(['auth'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
