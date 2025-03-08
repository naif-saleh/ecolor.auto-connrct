<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ActivityLog;
use App\Models\ADialProvider;
use App\Models\UserActivityLog;
use App\Models\AutoDailerReport;
use App\Models\AutoDistributerReport;
use App\Models\Evaluation;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Support\Facades\DB;

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

    /**
     * display Auto Dailer Report
     */
    // public function AutoDailerReports(Request $request)
    // {
    //     $filter = $request->input('filter', 'today'); // Default to 'today' if no filter is provided
    //     $extensionFrom = $request->input('extension_from');
    //     $extensionTo = $request->input('extension_to');
    //     $provider = $request->input('provider');
    //     $dateFrom = $request->input('date_from');
    //     $dateTo = $request->input('date_to');

    //     // Define status mappings
    //     $answeredStatuses = ['Talking', 'Wexternalline', 'Transferring'];
    //     $noAnswerStatuses = ['Wspecialmenu', 'no answer', 'Routing', 'Dialing', 'error', 'Initiating'];
    //     //$faildCalls = ['Dialing', 'error'];

    //     // Start building the query
    //     $query = AutoDailerReport::query();

    //     // Apply provider filter if selected
    //     if ($provider) {
    //         $query->where('provider', $provider);
    //     }

    //     // Apply date range filters if selected
    //     if ($dateFrom && $dateTo) {
    //         $query->whereBetween('created_at', [
    //             \Carbon\Carbon::parse($dateFrom)->startOfDay(),
    //             \Carbon\Carbon::parse($dateTo)->endOfDay()
    //         ]);
    //     } elseif ($filter === 'today') {
    //         // If no date range is provided and filter is 'today', default to today's data
    //         $query->whereDate('created_at', now()->toDateString());
    //     }

    //     // Apply extension range filters if provided
    //     if ($extensionFrom) {
    //         $query->where('extension', '>=', $extensionFrom);
    //     }
    //     if ($extensionTo) {
    //         $query->where('extension', '<=', $extensionTo);
    //     }

    //     // Clone query before applying status filters (for statistics)
    //     $statsQuery = clone $query;

    //     // Apply status filters based on selection
    //     if ($filter === 'answered') {
    //         $query->whereIn('status', $answeredStatuses);
    //     } elseif ($filter === 'no answer') {
    //         $query->whereIn('status', $noAnswerStatuses);
    //     }
    //     // elseif ($filter === 'faild') {
    //     //     $query->whereIn('status', $faildCalls);
    //     // }
    //     // If filter is 'all', no additional status or date filter applied

    //     // Get paginated results
    //     $reports = $query->orderBy('created_at', 'desc')->paginate(50);

    //     // Calculate statistics
    //     $totalCount = $statsQuery->count();
    //     $answeredCount = (clone $statsQuery)->whereIn('status', $answeredStatuses)->count();
    //     $noAnswerCount = (clone $statsQuery)->whereIn('status', $noAnswerStatuses)->count();
    //     // $faildCallsCount = (clone $statsQuery)->whereIn('status', $faildCalls)->count();

    //     // Get distinct providers for dropdown
    //     $providers = ADialProvider::select('name', 'extension')
    //         ->distinct()
    //         ->orderBy('name', 'asc')
    //         ->orderBy('extension', 'desc')
    //         ->get();

    //     return view('reports.auto_dailer_report', compact(
    //         'reports',
    //         'filter',
    //         'provider',
    //         'providers',
    //         'totalCount',
    //         'answeredCount',
    //         'noAnswerCount',
    //         'extensionFrom',
    //         'extensionTo',
    //         'dateFrom',
    //         'dateTo'
    //     ));
    // }

    public function AutoDailerReports(Request $request)
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
        $answeredStatuses = ['Talking', 'Wexternalline', 'Transferring'];
        $noAnswerStatuses = ['Wspecialmenu', 'no answer', 'Routing', 'Dialing', 'error', 'Initiating'];

        // Start building the query
        $query = AutoDailerReport::query();

        // Apply provider filter if selected
        if ($provider) {
            $query->where('provider', $provider);
        }

        // Apply date range filters if selected
        if ($dateFrom && $dateTo) {
            $query->whereBetween('created_at', [
                \Carbon\Carbon::parse($dateFrom)->startOfDay(),
                \Carbon\Carbon::parse($dateTo)->endOfDay()
            ]);
        } elseif ($filter === 'today') {
            // If no date range is provided and filter is 'today', default to today's data
            $query->whereDate('created_at', now()->toDateString());
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
        }

        // Get paginated results
        $reports = $query->orderBy('created_at', 'desc')->paginate(50);

        // Calculate statistics
        $totalCount = $statsQuery->count();
        $answeredCount = (clone $statsQuery)->whereIn('status', $answeredStatuses)->count();
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

        $statusMap = [
            'answered' => ['Wexternalline', 'Talking', 'Transferring'],
            'no answer' => ['no answer', 'Routing', 'Dialing', 'error', 'Initiating'],
            //'faild' => ['Dialing', 'error'],
        ];

        $query = AutoDailerReport::query();

        if ($filter === 'today') {
            $query->whereDate('created_at', now()->toDateString());
        } elseif ($filter && isset($statusMap[$filter])) {
            $query->whereIn('status', $statusMap[$filter]);
        }

        if ($extensionFrom) {
            $query->where('extension', '>=', $extensionFrom);
        }

        if ($extensionTo) {
            $query->where('extension', '<=', $extensionTo);
        }

        if ($provider) {
            $query->where('provider', $provider);
        }

        // Apply date range filter
        if ($dateFrom && $dateTo) {
            $query->whereBetween('created_at', [
                \Carbon\Carbon::parse($dateFrom, 'Asia/Riyadh')->startOfDay()->timezone('UTC'),
                \Carbon\Carbon::parse($dateTo, 'Asia/Riyadh')->endOfDay()->timezone('UTC')
            ]);
        }
         // Apply time range filters if provided
         if ($timeFrom && $timeTo) {
            $query->whereBetween(DB::raw('TIME(created_at)'), [$timeFrom, $timeTo]);
        }
        $reports = $query->get();
        // dd('Export Query:', ['query' => $query->toSql(), 'bindings' => $query->getBindings()]);

        $response = new StreamedResponse(function () use ($reports) {
            $handle = fopen('php://output', 'w');

            // Write the CSV header
            fputcsv($handle, ['Mobile', 'Provider', 'Extension', 'State', 'Talking', 'Routing', 'Time', 'Date']);

            // Write each report row
            foreach ($reports as $report) {
                fputcsv($handle, [
                    $report->phone_number,
                    $report->provider,
                    $report->extension,
                    (in_array($report->status, ['Wexternalline', 'Talking']) ? 'Answered' : 'No Answer'),
                    $report->duration_time ? $report->duration_time : '-',
                    $report->duration_routing ? $report->duration_routing : '-',
                    $report->created_at->addHours(3)->format('H:i:s'),
                    $report->created_at->addHours(3)->format('Y-m-d')
                ]);
            }

            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition', 'attachment; filename="Auto_Dailer_Report.csv"');

        return $response;
    }

    /**
     * display Auto Distributer Report
     */
    // public function AutoDistributerReports(Request $request)
    // {
    //     $filter = $request->input('filter', 'today'); // Default to 'today' filter
    //     $extensionFrom = $request->input('extension_from');
    //     $extensionTo = $request->input('extension_to');
    //     $provider = $request->input('provider');
    //     $dateFrom = $request->input('date_from');
    //     $dateTo = $request->input('date_to');

    //     // Map filter values to database values
    //     $statusMap = [
    //         'answered' => ['Talking', 'Wexternalline'],
    //         'no answer' => ['Wspecialmenu', 'no answer', 'Dialing', 'Routing'],
    //         'employee_unanswer' => ['Initiating', 'SomeOtherStatus']
    //     ];

    //     $query = AutoDistributerReport::query();

    //     // Apply status filter (default to today)
    //     if ($filter === 'today' || !$request->has('filter')) {
    //         $query->whereDate('created_at', now()->toDateString());
    //     } elseif ($filter === 'all') {
    //         // No filtering by date or status for 'All'
    //     } elseif (isset($statusMap[$filter])) {
    //         $query->whereIn('status', $statusMap[$filter]);
    //     }

    //     // Apply extension range filters
    //     if ($extensionFrom) {
    //         $query->where('extension', '>=', $extensionFrom);
    //     }
    //     if ($extensionTo) {
    //         $query->where('extension', '<=', $extensionTo);
    //     }

    //     // Apply provider filter
    //     if ($provider) {
    //         $query->where('provider', $provider);
    //     }

    //     // Apply date range filter
    //     if ($dateFrom && $dateTo) {
    //         $query->whereBetween('created_at', [
    //             \Carbon\Carbon::parse($dateFrom)->startOfDay(),
    //             \Carbon\Carbon::parse($dateTo)->endOfDay()
    //         ]);
    //     }

    //     // Apply pagination
    //     $reports = $query->orderBy('created_at', 'desc')->paginate(50);

    //     // Calculate counts for overall report
    //     $totalCount = AutoDistributerReport::count();
    //     $answeredCount = AutoDistributerReport::whereIn('status', ['Wextension', 'Wexternalline', "Talking"])->count();
    //     $noAnswerCount = AutoDistributerReport::whereIn('status', ['Wspecialmenu', 'Dialing', 'no answer', 'Routing'])->count();
    //     $employeeUnanswerCount = AutoDistributerReport::whereIn('status', ['Initiating', 'SomeOtherStatus'])->count();

    //     // Calculate counts for "Today" (default view)
    //     $todayTotalCount = AutoDistributerReport::whereDate('created_at', now()->toDateString())->count();
    //     $todayAnsweredCount = AutoDistributerReport::whereDate('created_at', now()->toDateString())
    //         ->whereIn('status', ['Wextension', 'Wexternalline', "Talking"])->count();
    //     $todayNoAnswerCount = AutoDistributerReport::whereDate('created_at', now()->toDateString())
    //         ->whereIn('status', ['Wspecialmenu', 'Dialing', 'no answer', 'Routing'])->count();
    //     $todayEmployeeUnanswerCount = AutoDistributerReport::whereDate('created_at', now()->toDateString())
    //         ->whereIn('status', ['Initiating', 'SomeOtherStatus'])->count();

    //     // Fetch distinct providers for the filter dropdown
    //     $providers = AutoDistributerReport::select('provider')->distinct()->get();

    //     return view('reports.auto_distributer_report', compact(
    //         'reports',
    //         'filter',
    //         'extensionFrom',
    //         'extensionTo',
    //         'provider',
    //         'providers',
    //         'totalCount',
    //         'answeredCount',
    //         'noAnswerCount',
    //         'employeeUnanswerCount',
    //         'todayTotalCount',
    //         'todayAnsweredCount',
    //         'todayNoAnswerCount',
    //         'todayEmployeeUnanswerCount'
    //     ));
    // }
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
        $answeredStatuses = ['Talking', 'Wexternalline', 'Transferring'];
        $noAnswerStatuses = ['Wspecialmenu', 'no answer', 'Routing', 'Dialing', 'error'];
        $employee_unanswer = ['Initiating', 'SomeOtherStatus'];
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
                \Carbon\Carbon::parse($dateTo)->endOfDay()
            ]);
        } elseif ($filter === 'today') {
            // If no date range is provided and filter is 'today', default to today's data
            $query->whereDate('created_at', now()->toDateString());
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
        }

        // Get paginated results
        $reports = $query->orderBy('created_at', 'desc')->paginate(50);

        // Calculate statistics
        $totalCount = $statsQuery->count();
        $answeredCount = (clone $statsQuery)->whereIn('status', $answeredStatuses)->count();
        $noAnswerCount = (clone $statsQuery)->whereIn('status', $noAnswerStatuses)->count();
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

        $statusMap = [
            'answered' => ['Talking', 'Wexternalline'],
            'no answer' => ['Wspecialmenu', 'no answer', 'Dialing', 'Routing'],
            'employee_unanswer' => ['Initiating', 'SomeOtherStatus']
        ];

        $query = AutoDistributerReport::query();

        if ($filter === 'today') {
            $query->whereDate('created_at', now()->toDateString());
        } elseif ($filter && isset($statusMap[$filter])) {
            $query->whereIn('status', $statusMap[$filter]);
        }

        if ($extensionFrom) {
            $query->where('extension', '>=', $extensionFrom);
        }

        if ($extensionTo) {
            $query->where('extension', '<=', $extensionTo);
        }

        if (!empty($provider)) {
            $query->where('provider', $provider);
        }

        // Apply date range filter
        if ($dateFrom && $dateTo) {
            $query->whereBetween('created_at', [
                \Carbon\Carbon::parse($dateFrom)->startOfDay(),
                \Carbon\Carbon::parse($dateTo)->endOfDay()
            ]);
        }

        $reports = $query->get();

        $response = new StreamedResponse(function () use ($reports) {
            $handle = fopen('php://output', 'w');

            // Write the CSV header
            fputcsv($handle, ['Mobile', 'Provider', 'Extension', 'State', 'duration', 'Time', 'Date']);

            // Write each report row
            foreach ($reports as $report) {
                fputcsv($handle, [
                    $report->phone_number,
                    $report->provider,
                    $report->extension,
                    //logic for status
                    $report->status === 'Talking' ? 'Answered' : ($report->status === 'Routing' ? 'No Answer' : ($report->status === 'Initiating' ? 'Employee No Answer' : 'No Answer')),
                    $report->duration_time ? $report->duration_time : '-',
                    $report->created_at->addHours(3)->format('H:i:s'),
                    $report->created_at->addHours(3)->format('Y-m-d')
                ]);
            }


            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition', 'attachment; filename="auto_distributor_report.csv"');

        return $response;
    }

    /**
     * Evaluation
     */
    public function Evaluation(Request $request)
    {
        // Retrieve filter parameters from the request (default to 'today')
        $filter = $request->get('filter', 'today'); // Default to 'today'
        $dateFrom = $request->get('date_from', null);
        $dateTo = $request->get('date_to', null);

        // Query the Evaluation model with filters
        $query = Evaluation::query();

        // Apply filters for 'is_satisfied' (use filter for 1 or 0)
        if ($filter === 'satisfied') {
            $query->where('is_satisfied', "YES");
        } elseif ($filter === 'unsatisfied') {
            $query->where('is_satisfied', "NO");
        } elseif ($filter === 'today') {
            $query->whereDate('created_at', now()->toDateString()); // Default to today
        }

        // Apply date range filter
        if ($dateFrom && $dateTo) {
            $query->whereBetween('created_at', [
                \Carbon\Carbon::parse($dateFrom)->startOfDay(),
                \Carbon\Carbon::parse($dateTo)->endOfDay()
            ]);
        }

        // Paginate the results
        $reports = $query->orderBy('created_at', 'desc')->paginate(50);

        // Calculate statistics
        $totalCount = (clone $query)->count();
        $satisfiedCount = (clone $query)->where('is_satisfied', "YES")->count();
        $unsatisfiedCount = (clone $query)->where('is_satisfied', "NO")->count();


        // Return the view with data
        return view('evaluation.evaluation', [
            'reports' => $reports,
            'filter' => $filter,
            'totalCount' => $totalCount,
            'satisfiedCount' => $satisfiedCount,
            'unsatisfiedCount' => $unsatisfiedCount,
        ]);
    }


    /**
     * Export Evaluation
     */
    public function exportEvaluation(Request $request)
    {
        // Retrieve filter parameters from the request (if any)
        $filter = $request->get('filter', null);
        // $extensionFrom = $request->get('extension_from', null);
        // $extensionTo = $request->get('extension_to', null);
        // $provider = $request->get('provider', null);
        $dateFrom = $request->get('date_from', null);
        $dateTo = $request->get('date_to', null);

        // Query the Evaluation model with filters
        $query = Evaluation::query();

        // Apply filters for 'is_satisfied' (use filter for 1 or 0)
        if ($filter) {
            if ($filter === 'satisfied') {
                $query->where('is_satisfied', "YES");
            } elseif ($filter === 'unsatisfied') {
                $query->where('is_satisfied', "NO");
            }
        }

        // Apply other filters if provided
        // if ($extensionFrom) {
        //     $query->where('extension', '>=', $extensionFrom);
        // }

        // if ($extensionTo) {
        //     $query->where('extension', '<=', $extensionTo);
        // }

        // if ($provider) {
        //     $query->where('provider', $provider);
        // }

        if ($dateFrom) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        // Get the filtered results
        $reports = $query->get();

        // Define the CSV file header
        $headers = ['#', 'Mobile', 'Is Satisfied', 'Called At - Date', 'Called At - Time'];

        // Create the CSV content
        $csvContent = [];

        foreach ($reports as $index => $report) {
            $csvContent[] = [
                $index + 1,
                $report->mobile,
                $report->is_satisfied === 'YES' ? 'Satisfied' : 'Unsatisfied',
                $report->created_at->addHours(3)->format('Y-m-d'), // For Date
                $report->created_at->addHours(3)->format('H:i:s') // For Time
            ];
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
            "Content-type" => "text/csv",
            "Content-Disposition" => "attachment; filename=Evaluation.csv",
            "Cache-Control" => "no-store, no-cache, must-revalidate",
            "Cache-Control" => "post-check=0, pre-check=0",
            "Pragma" => "public"
        ]);
    }
}
