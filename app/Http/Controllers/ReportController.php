<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\ADialData;
use App\Models\ADialProvider;
use App\Models\ADistData;
use App\Models\AutoDailerReport;
use App\Models\AutoDistributerReport;
use App\Models\Evaluation;
use App\Models\License;
use App\Models\UserActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    /**
     * Active Log Report method
     */
    public function activityReport()
    {
        $logs = ActivityLog::with('user:id,name')
            ->orderBy('operation_time', 'desc')
            ->paginate(100);

        return view('reports.user_activity_report', compact('logs'));
    }

    /**
     * User Activity Report
     */
    public function UserActivityReport()
    {
        $logs = UserActivityLog::with('user:id,name')
            ->orderBy('created_at', 'desc')
            ->paginate(100);

        return view('reports.user_logs', compact('logs'));
    }

    public function AutoDailerReports(Request $request)
    {
        $filter = $request->input('filter', 'today');
        $extensionFrom = $request->input('extension_from');
        $extensionTo = $request->input('extension_to');
        $provider = $request->input('provider');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        $timeFrom = $request->input('time_from');
        $timeTo = $request->input('time_to');

        // Define status mappings
        $answeredStatuses = ['Talking', 'Wexternalline'];
        $noAnswerQueue = ['Rerouting', 'Transferring'];
        $noAnswerStatuses = ['Routing', 'Dialing', 'error', 'Initiating', 'Rerouting', 'Transferring'];

        // Start building the queries
        $query = AutoDailerReport::query();

        // Set a default value for $notCalled to prevent undefined variable error
        $notCalled = 0;

        // Apply provider filter if selected
        if ($provider) {
            $query->where('provider', $provider);
        }

        // Apply date range filters if selected
        if ($dateFrom && $dateTo) {
            $query->whereBetween('created_at', [
                \Carbon\Carbon::parse($dateFrom)->startOfDay(),
                \Carbon\Carbon::parse($dateTo)->endOfDay(),
            ]);
        } elseif ($filter === 'today') {
            // If no date range is provided and filter is 'today', default to today's data
            $query->whereDate('created_at', now()->toDateString());

            $notCalled = ADialData::where('state', 'new')
                ->whereDate('created_at', now()->toDateString())
                ->count();
        } elseif ($filter === 'all') {

            $notCalled = ADialData::where('state', 'new')->count();
        }

        // Apply time range filters if provided
        if ($timeFrom && $timeTo) {
            $query->whereBetween(DB::raw('TIME(created_at)'), [$timeFrom, $timeTo])
                ->count();
        }

        // Apply extension range filters if provided
        if ($extensionFrom) {
            $query->where('extension', '>=', $extensionFrom);
        }
        if ($extensionTo) {
            $query->where('extension', '<=', $extensionTo);
        }

        // Clone query before applying status filters (for statistics)
        $statsQuery = clone $query;

        // Apply status filters based on selection
        if ($filter === 'answered') {
            $query->whereIn('status', $answeredStatuses);
        } elseif ($filter === 'no answer') {
            $query->whereIn('status', $noAnswerStatuses);
        } elseif ($filter === 'no answer queue') {
            $query->whereIn('status', $noAnswerQueue);
        }

        // Get paginated results
        $reports = $query->orderBy('created_at', 'desc')->paginate(50);

        // Calculate statistics
        $totalCount = (clone $statsQuery)->count();
        $answeredCount = (clone $statsQuery)->whereIn('status', $answeredStatuses)->count();
        $noAnswerQueue = (clone $statsQuery)->whereIn('status', $noAnswerQueue)->count();
        $noAnswerCount = (clone $statsQuery)->whereIn('status', $noAnswerStatuses)->count();

        // Get distinct providers for dropdown
        $providers = ADialProvider::select('name', 'extension')
            ->distinct()
            ->orderBy('name', 'asc')
            ->orderBy('extension', 'desc')
            ->get();

        return view('reports.auto_dailer_report', compact(
            'reports',
            'filter',
            'provider',
            'providers',
            'totalCount',
            'answeredCount',
            'noAnswerCount',
            'noAnswerQueue',
            'notCalled',
            'extensionFrom',
            'extensionTo',
            'dateFrom',
            'dateTo',
            'timeFrom',
            'timeTo'
        ));
    }

    /**
     * Export Auto Dailer AS CSV File
     */
    public function exportAutoDailerReport(Request $request)
    {
        $filter = $request->query('filter');
        $extensionFrom = $request->input('extension_from');
        $extensionTo = $request->input('extension_to');
        $provider = $request->input('provider');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        $timeFrom = $request->input('time_from');
        $timeTo = $request->input('time_to');

        // Define status mappings
        $answeredStatuses = ['Talking', 'call'];
        $noAnswerStatuses = ['no answer', 'Routing', 'Dialing', 'error', 'Initiating'];
        $noAnswerQueue = ['Rerouting', 'Transferring'];

        $query = AutoDailerReport::query();

        // Apply filter based on status
        if ($filter === 'answered') {
            $query->whereIn('status', $answeredStatuses);
        } elseif ($filter === 'no answer') {
            $query->whereIn('status', $noAnswerStatuses);
        } elseif ($filter === 'no answer queue') {
            $query->whereIn('status', $noAnswerQueue);
        }

        // Apply date filters
        if ($dateFrom && $dateTo) {
            $carbonFrom = \Carbon\Carbon::parse($dateFrom)->startOfDay();
            $carbonTo = \Carbon\Carbon::parse($dateTo)->endOfDay();
            $query->whereBetween('created_at', [$carbonFrom, $carbonTo]);
        } elseif ($filter === 'today') {
            $today = now()->toDateString();
            $query->whereDate('created_at', $today);
            Log::info('Using today filter for export:', ['today' => $today]);
        }

        // Time filters
        if ($timeFrom && $timeTo) {
            $query->whereBetween(DB::raw('TIME(created_at)'), [$timeFrom, $timeTo]);
        }

        // Extension filters
        if (! empty($extensionFrom)) {
            $query->where('extension', '>=', $extensionFrom);
        }
        if (! empty($extensionTo)) {
            $query->where('extension', '<=', $extensionTo);
        }

        // Provider filter
        if (! empty($provider)) {
            $query->where('provider', $provider);
        }

        // Debug SQL
        Log::info('Autoevaluation Export Query:', [
            'sql' => $query->toSql(),
            'bindings' => $query->getBindings(),
        ]);

        $response = new StreamedResponse(function () use ($query) {
            $handle = fopen('php://output', 'w');

            // Write the CSV header
            fputcsv($handle, ['Mobile', 'Provider', 'Extension', 'State', 'Talking Time', 'Routing Time', 'Time', 'Date']);

            foreach ($query->cursor() as $report) {
                $state = match ($report->status) {
                    'Talking', 'call' => 'Answered',
                    'Routing', 'Dialing', 'no answer', 'error' => 'Unanswered',
                    'Transferring', 'Rerouting' => 'Queue Unanswered',
                    default => $report->status
                };

                fputcsv($handle, [
                    $report->phone_number ?? '-',
                    $report->provider ?? '-',
                    $report->extension ?? '-',
                    $state,
                    $report->duration_time ?? '-',
                    $report->duration_routing ?? '-',
                    optional($report->created_at)?->addHours(3)->format('H:i:s') ?? '-',
                    optional($report->created_at)?->addHours(3)->format('Y-m-d') ?? '-',
                ]);
            }

            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition', 'attachment; filename="auto_dialer_report.csv"');

        return $response;
    }

    /**
     * Dialer Not Called Numbers
     */
    public function dialnotCalledNumbers()
    {
        $notCalled = ADialData::where('state', 'new')
            ->whereDate('created_at', now()->toDateString())
            ->paginate(200);
        $count = ADialData::where('state', 'new')
            ->whereDate('created_at', now()->toDateString())
            ->count();

        return view('reports.Dial_notCalled', compact('notCalled', 'count'));
    }

    /**
     * Dialer Export Not Called Numbers
     */
    public function dialexportTodayNotCalledCSV()
    {
        $notCalledData = ADialData::where('state', 'new')
            ->whereDate('created_at', now()->toDateString())
            ->get();

        // Define CSV headers
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename=today_not_called_numbers.csv',
        ];

        // Generate CSV content
        $callback = function () use ($notCalledData) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['Mobile', 'Status', 'Uploaded At']); // CSV headers

            foreach ($notCalledData as $report) {
                fputcsv($file, [$report->mobile, $report->state, $report->created_at]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function AutoDistributerReports(Request $request)
    {
        $filter = $request->input('filter', 'today'); // Default to 'today' if no filter is provided
        $extensionFrom = $request->input('extension_from');
        $extensionTo = $request->input('extension_to');
        $provider = $request->input('provider');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        $timeFrom = $request->input('time_from');
        $timeTo = $request->input('time_to');

        // Define status mappings
        $answeredStatuses = ['Talking', 'Wexternalline'];
        $noAnswerStatuses = ['Routing', 'Dialing', 'error', 'Rerouting', 'Transferring'];
        $employee_unanswer = ['Initiating'];
        $noAnswerQueue = ['Rerouting', 'Transferring'];
        // Start building the query
        $query = AutoDistributerReport::query();

        // Apply provider filter if selected
        if ($provider) {
            $query->where('provider', $provider);
        }

        // Apply date range filters if selected
        if ($dateFrom && $dateTo) {
            $query->whereBetween('created_at', [
                \Carbon\Carbon::parse($dateFrom)->startOfDay(),
                \Carbon\Carbon::parse($dateTo)->endOfDay(),
            ]);

            $notCalled = ADistData::where('state', 'new')
                ->whereBetween('created_at', [
                    \Carbon\Carbon::parse($dateFrom)->startOfDay(),
                    \Carbon\Carbon::parse($dateTo)->endOfDay(),
                ])
                ->count();
        } elseif ($filter === 'today') {
            // If no date range is provided and filter is 'today', default to today's data
            $query->whereDate('created_at', now()->toDateString());

            $notCalled = ADistData::where('state', 'new')
                ->whereDate('created_at', now()->toDateString())
                ->count();
        }

        // Apply time range filters if provided
        if ($timeFrom && $timeTo) {
            $query->whereBetween(DB::raw('TIME(created_at)'), [$timeFrom, $timeTo]);
        }

        // Apply extension range filters if provided
        if ($extensionFrom) {
            $query->where('extension', '>=', $extensionFrom);
        }
        if ($extensionTo) {
            $query->where('extension', '<=', $extensionTo);
        }

        // Clone query before applying status filters (for statistics)
        $statsQuery = clone $query;

        // Apply status filters based on selection
        if ($filter === 'answered') {
            $query->whereIn('status', $answeredStatuses);
        } elseif ($filter === 'no answer') {
            $query->whereIn('status', $noAnswerStatuses);
        } elseif ($filter === 'emplooyee no answer') {
            $query->whereIn('status', $employee_unanswer);
        } elseif ($filter === 'queue no answer') {
            $query->whereIn('status', $noAnswerQueue);
        }

        // Get paginated results
        $reports = $query->orderBy('created_at', 'desc')->paginate(50);

        // Calculate statistics
        $totalCount = $statsQuery->count();
        $answeredCount = (clone $statsQuery)->whereIn('status', $answeredStatuses)->count();
        $noAnswerCount = (clone $statsQuery)->whereIn('status', $noAnswerStatuses)->count();
        $noAnswerQueueCount = (clone $statsQuery)->whereIn('status', $noAnswerQueue)->count();
        $todayEmployeeUnanswerCount = (clone $statsQuery)->whereIn('status', $employee_unanswer)->count();
        // Get distinct providers for dropdown
        $providers = ADialProvider::select('name', 'extension')
            ->distinct()
            ->orderBy('name', 'asc')
            ->orderBy('extension', 'desc')
            ->get();

        return view('reports.auto_distributer_report', compact(
            'reports',
            'filter',
            'provider',
            'providers',
            'totalCount',
            'answeredCount',
            'noAnswerCount',
            'todayEmployeeUnanswerCount',
            'noAnswerQueueCount',
            'extensionFrom',
            'extensionTo',
            'dateFrom',
            'dateTo',
            'timeFrom',
            'timeTo'
        ));
    }

    /**
     * Export Auto Distributer AS CSV File
     */
    public function exportAutoDistributerReport(Request $request)
    {
        $filter = $request->query('filter');
        $extensionFrom = $request->input('extension_from');
        $extensionTo = $request->input('extension_to');
        $provider = $request->input('provider');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        $timeFrom = $request->input('time_from');
        $timeTo = $request->input('time_to');

        // Define status mappings
        $answeredStatuses = ['Talking', 'Wexternalline'];
        $noAnswerStatuses = ['Routing', 'Dialing', 'error'];
        $noAnswerQueue = ['Rerouting', 'Transferring'];
        $employeeUnanswered = ['Initiating'];

        $query = AutoDistributerReport::query();

        // Apply filter based on status
        if ($filter === 'answered') {
            $query->whereIn('status', $answeredStatuses);
        } elseif ($filter === 'no answer') {
            $query->whereIn('status', $noAnswerStatuses);
        } elseif ($filter === 'emplooyee no answer') {
            $query->whereIn('status', $employeeUnanswered);
        } elseif ($filter === 'queue no answer') {
            $query->whereIn('status', $noAnswerQueue);
        }

        // Date filtering
        if ($dateFrom && $dateTo) {
            $carbonFrom = \Carbon\Carbon::parse($dateFrom)->startOfDay();
            $carbonTo = \Carbon\Carbon::parse($dateTo)->endOfDay();
            $query->whereBetween('created_at', [$carbonFrom, $carbonTo]);
        } elseif ($filter === 'today') {
            $query->whereDate('created_at', now()->toDateString());
        }

        // Time filter
        if ($timeFrom && $timeTo) {
            $query->whereBetween(DB::raw('TIME(created_at)'), [$timeFrom, $timeTo]);
        }

        // Extension range
        if (! empty($extensionFrom)) {
            $query->where('extension', '>=', $extensionFrom);
        }
        if (! empty($extensionTo)) {
            $query->where('extension', '<=', $extensionTo);
        }

        // Provider
        if (! empty($provider)) {
            $query->where('provider', $provider);
        }

        // Debug SQL
        Log::info('Export query:', [
            'sql' => $query->toSql(),
            'bindings' => $query->getBindings(),
        ]);

        $response = new StreamedResponse(function () use ($query) {
            $handle = fopen('php://output', 'w');

            // CSV header
            fputcsv($handle, ['Mobile', 'Provider', 'Extension', 'State', 'Duration', 'Time', 'Date']);

            foreach ($query->cursor() as $report) {
                // Map status to readable label
                $state = match ($report->status) {
                    'Talking', 'Wexternalline' => 'Answered',
                    'Routing', 'Dialing', 'error' => 'Unanswered',
                    'Initiating' => 'Employee Unanswered',
                    'Rerouting', 'Transferring' => 'Queue Unanswered',
                    default => $report->status,
                };

                fputcsv($handle, [
                    $report->phone_number ?? '-',
                    $report->provider ?? '-',
                    $report->extension ?? '-',
                    $state,
                    $report->duration_time ?? '-',
                    optional($report->created_at)?->addHours(3)->format('H:i:s') ?? '-',
                    optional($report->created_at)?->addHours(3)->format('Y-m-d') ?? '-',
                ]);
            }

            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition', 'attachment; filename="auto_distributor_report.csv"');

        return $response;
    }

    /**
     * Distributort Not Called Numbers
     */
    public function distnotCalledNumbers()
    {
        $notCalled = ADistData::where('state', 'new')
            ->whereDate('created_at', now()->toDateString())
            ->paginate(200);
        $count = ADistData::where('state', 'new')
            ->whereDate('created_at', now()->toDateString())
            ->count();

        return view('reports.Dial_notCalled', compact('notCalled', 'count'));
    }

    /**
     * Distributort Export Not Called Numbers
     */
    public function distexportTodayNotCalledCSV()
    {
        $notCalledData = ADistData::where('state', 'new')
            ->whereDate('created_at', now()->toDateString())
            ->get();

        // Define CSV headers
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename=Distributortoday_not_called_numbers.csv',
        ];

        // Generate CSV content
        $callback = function () use ($notCalledData) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['Mobile', 'Status', 'Uploaded At']); // CSV headers

            foreach ($notCalledData as $report) {
                fputcsv($file, [$report->mobile, $report->state, $report->created_at]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Evaluation
     */
    public function Evaluation(Request $request)
    {
        // Retrieve filter parameters from the request
        $filter = $request->get('filter', 'today'); // Default to 'today'
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');

        // Start query
        $query = Evaluation::query();
        $hasDateRange = $dateFrom && $dateTo;

        // Apply 'filter' only if no custom date range is applied
        if (! $hasDateRange) {
            if ($filter === 'satisfied') {
                $query->where('is_satisfied', 'YES');
            } elseif ($filter === 'unsatisfied') {
                $query->where('is_satisfied', 'NO');
            } elseif ($filter === 'today') {
                $query->whereDate('created_at', now()->toDateString());
            }
        }

        // Apply date range filter
        if ($hasDateRange) {
            $query->whereBetween('created_at', [
                \Carbon\Carbon::parse($dateFrom)->startOfDay(),
                \Carbon\Carbon::parse($dateTo)->endOfDay(),
            ]);
        }

        // Clone query BEFORE pagination for accurate stats
        $fullQuery = clone $query;

        // Statistics
        $totalCount = $fullQuery->count();
        $satisfiedCount = (clone $fullQuery)->where('is_satisfied', 'YES')->count();
        $unsatisfiedCount = (clone $fullQuery)->where('is_satisfied', 'NO')->count();

        // Paginate results AFTER getting statistics
        $reports = $query->orderBy('created_at', 'desc')->paginate(50);

        $enabled = false;
        // Check license validity
        $this->checkLicenseAndMakeCall($enabled);

        // Return the view
        return view('evaluation.evaluation', [
            'reports' => $reports,
            'filter' => $filter,
            'totalCount' => $totalCount,
            'satisfiedCount' => $satisfiedCount,
            'unsatisfiedCount' => $unsatisfiedCount,
            'enabled' => $enabled,
        ]);
    }

    /**
     * Check license validity and make call if license is valid
     *
     * @return void
     */
    private function checkLicenseAndMakeCall(&$enabled)
    {
        try {
            // Get license details
            $license_start_date = Carbon::parse(License::get('start_date'));
            $license_end_date = Carbon::parse(License::get('end_date'));
            $license_status = License::get('status');

            // Get dialer module data
            $evaluation_setting = DB::table('licenses')->where('key', 'evaluation_moduales')->first();

            // If evaluation module doesn't exist, don't proceed with call
            if (! $evaluation_setting) {
                Log::info('evaluation: ❌ evaluation module not found. ');

                return;
            }

            // Parse evaluation settings
            $evaluation_data = json_decode($evaluation_setting->value, true);

            // Handle potential JSON decode failures
            if (json_last_error() !== JSON_ERROR_NONE || ! is_array($evaluation_data)) {
                Log::error('evaluation: ❌ evaluation module is disabled, please Upgrade. ');

                return;
            }

            // Check if module is enabled
            $is_enabled = isset($evaluation_data['enabled']) ? (bool) $evaluation_data['enabled'] : false;

            // Check license validity in a sequential manner
            if (! now()->between($license_start_date, $license_end_date)) {
                Log::info('evaluation: ❌ License expired. ');

                return;
            }

            if ($license_status !== 'Active') {
                Log::info('evaluation: ❌ License not activated. ');

                return;
            }

            if (! $is_enabled) {
                Log::info('evaluation: ❌ evaluation module is disabled. ');

                return;
            }

            // If all checks pass, set enabled to true
            $enabled = true;
            
        } catch (\Exception $e) {
            Log::error("evaluation: ❌ License check error: {$e->getMessage()}");
        }
    }

    /**
     * Export Evaluation
     */
    public function exportEvaluation(Request $request)
    {
        // Retrieve filter parameters from the request (if any)
        $filter = $request->query('filter');
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');

        // Query the Evaluation model with filters
        $query = Evaluation::query();

        // Apply 'filter' only if no custom date range is applied
        $hasDateRange = $dateFrom && $dateTo;

        if (! $hasDateRange) {
            if ($filter === 'satisfied') {
                $query->where('is_satisfied', 'YES');
            } elseif ($filter === 'unsatisfied') {
                $query->where('is_satisfied', 'NO');
            } elseif ($filter === 'today') {
                $query->whereDate('created_at', now()->toDateString());
            }
        }

        // Apply date range filter
        if ($hasDateRange) {
            $query->whereBetween('created_at', [
                \Carbon\Carbon::parse($dateFrom)->startOfDay(),
                \Carbon\Carbon::parse($dateTo)->endOfDay(),
            ]);
        }
        // Get the filtered results
        $reports = $query->get();

        // Define the CSV file header
        $headers = ['#', 'Mobile', 'Extension', 'Is Satisfied', 'Called At - Date', 'Called At - Time'];

        // Create the CSV content
        $csvContent = [];

        foreach ($reports as $index => $report) {
            $csvContent[] = [
                $index + 1,
                $report->mobile,
                $report->extension,
                $report->is_satisfied === 'YES' ? 'Satisfied' : 'Unsatisfied',
                $report->created_at->addHours(3)->format('Y-m-d'), // For Date
                $report->created_at->addHours(3)->format('H:i:s'), // For Time
            ];
        }

        $enabled = false;
        // Check license validity
        $this->checkLicenseAndMakeCall($enabled);
        if(!$enabled) {
            return redirect()->back()->with('wrong', 'Evaluation module is disabled. Please Upgrade');
        }

        // Create the CSV response
        $callback = function () use ($csvContent, $headers) {
            $handle = fopen('php://output', 'w');
            // Add the header to the CSV file
            fputcsv($handle, $headers);
            // Add the data rows
            foreach ($csvContent as $row) {
                fputcsv($handle, $row);
            }
            fclose($handle);
        };

        // Return the CSV response with the correct headers
        return response()->stream($callback, 200, [
            'Content-type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename=Evaluation.csv',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
            'Cache-Control' => 'post-check=0, pre-check=0',
            'Pragma' => 'public',
        ]);
    }
}
