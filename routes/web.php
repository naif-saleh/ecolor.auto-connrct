<?php

use Illuminate\Support\Facades\Auth;
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
use App\Http\Controllers\AutoDailerByProvider\ProviderForAutoDailerController;
use App\Http\Controllers\AutoDailerByProvider\ProviderFeedController;
use App\Http\Controllers\AutoDistributerByUser\UserForAutoDistributer;
use App\Http\Controllers\AutoDistributerByUser\UserFeedController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\AutoDailerFileController;
use App\Http\Controllers\AutoDistributorFileController;

Route::get('/', function () {
    if (Auth::check()) {
        $user = Auth::user();


        if ($user->isSuperUser() || $user->isAdmin()) {
            return redirect('/auto-dailer/files');
        }

        if ($user->isManagerUser()) {
            return redirect()->route('manager.dashboard');
        }
    }

    return view('auth.login');
});


// Route::get('/', [ProviderForAutoDailerController::class, 'index'])->name('autoDailerByProvider.index');


// // Show feeds for a provider
// Route::get('providers/{id}/feeds', [ProviderFeedController::class, 'show'])->name('feeds.show');

// // Show individual feed details
// Route::get('feeds/{id}', [ProviderFeedController::class, 'showFeed'])->name('feeds.showFeed');

// // Store new feed
// Route::post('providers/{id}/feeds', [ProviderFeedController::class, 'storeFeed'])->name('feeds.store');



// // AutoDistributer By User..........................................................................................................

// // User..........................................................................................................................
// Route::get('/auto-distributers-users', [UserForAutoDistributer::class, 'index'])->name('autoDistributers.index');
// Route::get('/auto-distributers-users/create', [UserForAutoDistributer::class, 'create'])->name('autoDistributers.create');
// Route::post('/auto-distributers-users', [UserForAutoDistributer::class, 'store'])->name('autoDistributers.store');
// Route::get('/auto-distributers-users/{id}', [UserForAutoDistributer::class, 'show'])->name('autoDistributers.show');
// Route::get('/auto-distributers-users/{id}/edit', [UserForAutoDistributer::class, 'edit'])->name('autoDistributers.edit');
// Route::put('/auto-distributers-users/{id}', [UserForAutoDistributer::class, 'update'])->name('autoDistributers.update');
// Route::delete('/auto-distributers-users/{id}', [UserForAutoDistributer::class, 'destroy'])->name('autoDistributers.destroy');
// // User................................................................................................................................
// // User Feed....................................................................................................................
// Route::get('auto-distributers/{id}/createFeed', [UserFeedController::class, 'createFeed'])->name('autoDistributers.createFeed');
// Route::post('auto-distributers/{id}/storeFeed', [UserFeedController::class, 'storeFeed'])->name('autoDistributers.storeFeed');
// Route::get('auto-distributers-feed/{id}', [UserFeedController::class, 'show'])->name('autoDistributersUser.show');
// // Route::get('autoDialercall', [ProviderForAutoDailerController::class, 'autoDailer'])->name('call');
// // User Feed....................................................................................................................





// Route::get('api/auto-dailer/{id}', [ApiController::class, 'autoDailerShowState']);
// Route::get('settings/json', [SettingController::class, 'getCfdApi'])->name('settings.getCfdApi')->middleware(['auth']);
// User Management..........................................................................................................................
Route::middleware(['auth', 'admin'])->group(function () {
    Route::get('users', [UserController::class, 'index'])->name('users.index');
    Route::get('users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
    Route::put('users/{user}', [UserController::class, 'update'])->name('users.update');
    Route::delete('users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
    Route::post('users/reset-password', [UserController::class, 'resetPassword'])->name('users.reset.password');
    Route::get('users/create', [UserController::class, 'create'])->name('users.create');
    Route::post('users', [UserController::class, 'store'])->name('users.store');





    // Manager Reports...................................................................................................................

    // Auto Dailer Extensios...............
    Route::get('manager/auto-dailer/report-extensions', [DashboardController::class, 'managerAutoDailersReports'])->name('manager.auotdailer.report.extension');
    // Auto Dailer Providers...............
    Route::get('manager/auto-dailer/report-providers', [DashboardController::class, 'managerAutoDailersReportsProvider'])->name('manager.auotdailer.report.providers');


    // Auto Distributer Extensions...........
    Route::get('manager/auto-distributor/report-extensions', [DashboardController::class, 'managerAutoDistributorsReports'])->name('manager.autodistributor.report.extension');
    // Auto Distributer Providers...............
    Route::get('manager/auto-distributor/report-providers', [DashboardController::class, 'managerAutoDistributorsReportsProvider'])->name('manager.autodistributor.report.providers');
    // Auto Distributer Compagin...............
    Route::get('manager/auto-distributor/report-compagin', [DashboardController::class, 'managerAutoDistributorsReportsCompaign'])->name('manager.autodistributor.report.compaign');

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
    // // Auto Distributer Reports....................................................................................................................
    Route::get('auto-distributer-report', [ReportController::class, 'AutoDistributerReports'])->name('auto_distributer.report');
    // // Export Auto Distributer...........................................................................................................
    Route::get('auto-distributer-report/export', [ReportController::class, 'exportAutoDistributerReport'])->name('auto_distributer.report.export');


    // AutoDailer..........................................................................................................................
    Route::get('auto-dailer/files', [AutoDailerFileController::class, 'index'])->name('autodailers.files.index');
    Route::get('auto-dailer/files/{slug}', [AutoDailerFileController::class, 'show'])->name('autodailers.files.show');
    Route::post('auto-dailer/upload-csv', [AutoDailerFileController::class, 'uploadCsv'])->name('autodailers.upload.csv');
    Route::delete('/autodailer-file/{id}', [AutoDailerFileController::class, 'deleteFile'])->name('autodailer.delete');
    // download File....................................................................................................................
    Route::get('auto-dailer/download-processed-file/{fileId}', [AutoDailerFileController::class, 'downloadUploadedFile'])->name('autodailers.download.processed.files');
    // Update value if file done or not.................................................................................................
    Route::post('autodailers/files/{slug}/allow', [AutoDailerFileController::class, 'updateAllowStatus'])->name('autodailers.files.allow');
    // Download Example csv.............................................................................................................
    Route::get('/download-example-csv', [AutoDailerFileController::class, 'downloadExampleCsv'])->name('download.example.csv');
    //    Manager Dashboard........................................................................................................
    Route::get('manager/dashboard', [DashboardController::class, 'getCallManagerStatisticsAutoDailer'])->name('manager.dashboard');



    // AutoDistributor..........................................................................................................................
    Route::get('auto-distributor/files', [AutoDistributorFileController::class, 'index'])->name('distributor.files.index');
    Route::get('auto-distributor/files/{slug}', [AutoDistributorFileController::class, 'show'])->name('distributor.files.show');
    Route::post('auto-distributor/upload-csv', [AutoDistributorFileController::class, 'uploadCsv'])->name('distributor.upload.csv');
    Route::delete('auto-distributor-file/{id}', [AutoDistributorFileController::class, 'deleteFile'])->name('distributor.delete');
    Route::get('/download-processed-file/{fileId}', [AutoDistributorFileController::class, 'downloadUploadedFile'])->name('distributor.download.processed.file');
    // Update value if file done or not.................................................................................................
    Route::post('auto-distributor/files/{slug}/allow', [AutoDistributorFileController::class, 'updateAllowStatus'])->name('distributor.files.allow');
    // Download Example csv.............................................................................................................
    Route::get('/download-example-csv', [AutoDistributorFileController::class, 'downloadExampleCsv'])->name('download.example.csv');
    // Import All Users...................................................................................................................
    Route::get('auto-distributor/import-users', [AutoDistributorFileController::class, 'importAllUsers'])->name('distributor.import.users');



    // Evaluation..............................................................................................................
    // Auto Dailer
    Route::get('auto-dailer/evaluation', [ReportController::class, 'autoDailerEvaluation'])->name('autoDailer.evaluation');
    Route::get('auto-dailer/evaluation/export', [ReportController::class, 'exportAutoDailerEvaluation'])->name('autoDailer.evaluation.export');

    // Auto Distributor
    Route::get('auto-distributor/evaluation', [ReportController::class, 'AutoDistributorEvaluation'])->name('autoDistributor.evaluation');
    Route::get('auto-distributor/evaluation/export', [ReportController::class, 'exportAutoDistributorEvaluation'])->name('autoDistributor.evaluation.export');
    // //    Manager Dashboard........................................................................................................
    // Route::get('manager/dashboard', [DashboardController::class, 'getCallManagerStatisticsAutoDailer'])->name('manager.dashboard');







    // AutoDistributer..............................................................................................................
    // Route::resource('auto_distributerer_extensions', UserForAutoDistributer::class);
    // Route::get('auto-distributer-extensions/import', [UserForAutoDistributer::class, 'import'])->name('auto_distributerer_extensions.import');
    // Route::delete('auto-distributer-extensions/delete-all', [UserForAutoDistributer::class, 'destroyAllUsers'])->name('auto_distributerer_extensions.deleteAll');


    // Route::get('auto-distributer-extensions/{id}/show', [UserFeedController::class, 'show'])->name('auto_distributerer_extensions.show');
    // Route::post('auto-distributer-extensions/{id}/store', [UserFeedController::class, 'store'])->name('auto_distributerer_extensions.storeFeed');
    // Route::get('auto-distributer-extensions/{id}/createFeed', [UserFeedController::class, 'createFeed'])->name('auto_distributerer_extensions.createFeed');
    // Route::get('auto-distributer-extensions/{extensionId}/feed/{feedFileId}/view-data', [UserFeedController::class, 'viewFeedData'])->name('auto_distributer_extensions.viewFeedData');

    // // AutoDailer By Provider..........................................................................................................

    // // Provider..........................................................................................................................
    // Route::get('/auto-dialer-providers', [ProviderForAutoDailerController::class, 'index'])->name('autoDialerProviders.index');
    // Route::get('/auto-dialer-providers/create', [ProviderForAutoDailerController::class, 'create'])->name('autoDialerProviders.create');
    // Route::post('/auto-dialer-providers', [ProviderForAutoDailerController::class, 'store'])->name('autoDialerProviders.store');
    // Route::get('/auto-dialer-providers/{id}', [ProviderForAutoDailerController::class, 'show'])->name('autoDialerProvider.show');
    // Route::get('/auto-dialer-providers/{id}/edit', [ProviderForAutoDailerController::class, 'edit'])->name('autoDialerProviders.edit');
    // Route::put('/auto-dialer-providers/{id}', [ProviderForAutoDailerController::class, 'update'])->name('autoDialerProviders.update');
    // Route::delete('/auto-dialer-providers/{id}', [ProviderForAutoDailerController::class, 'destroy'])->name('autoDialerProviders.destroy');
    // // Provider................................................................................................................................

    // // Provider Feed....................................................................................................................
    // Route::get('autoDialerProviders/{id}/createFeed', [ProviderFeedController::class, 'createFeed'])->name('autoDialerProviders.createFeed');
    // Route::post('autoDialerProviders/{id}/storeFeed', [ProviderFeedController::class, 'storeFeed'])->name('autoDialerProviders.storeFeed');
    // Route::get('autoDialerProviders/{id}', [ProviderFeedController::class, 'show'])->name('autoDialerProviders.show');
    // // Route::get('autoDialercall', [ProviderForAutoDailerController::class, 'autoDailer'])->name('call');
    // Provider Feed....................................................................................................................



    // // Dailer Calling..........................................................................................................................
    // Route::get('auto-dailer-call', [ApiController::class, 'autoDailer'])->name('autoDailer');
    // Route::get('auto-dailer-call-click', [ApiController::class, 'autoDailerByClick'])->name('auto_dailer.call.click');
    // // Distributer Calling.....................................................................................................................
    // Route::get('auto-distributer-call', [ApiController::class, 'autoDistributer']);
    // Route::get('auto-distributer-call-click', [ApiController::class, 'autoDistributerByClicking'])->name('auto_distributer.call.click');

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
    // Route::get('/settings', [SettingController::class, 'showForm'])->name('settings.form');
    // Route::post('/settings', [SettingController::class, 'saveSettings'])->name('settings.save');
    // // AutoDailers............................................................................................................................
    // Route::resource('autodailers', AutoDailerController::class);
    // Route::delete('/auto-dailers/delete-all', [AutoDailerController::class, 'deleteAllFiles'])->name('auto-dailers.deleteAll');
    // Route::get('/auto_dailer/{id}/download', [AutoDailerController::class, 'download'])->name('auto_dailer.download');
    // // AutoDistributers............................................................................................................................
    // Route::resource('autodistributers', AutoDirtibuterController::class);
    // Route::delete('/auto-distributers/delete-all', [AutoDirtibuterController::class, 'deleteAllFiles'])->name('auto-distributers.deleteAll');
    // Route::get('/auto_distributers/{id}/download', [AutoDirtibuterController::class, 'download'])->name('auto_distributers.download');
    // // Providers............................................................................................................................
    // Route::resource('providers', ProviderController::class);
});

require __DIR__ . '/auth.php';
