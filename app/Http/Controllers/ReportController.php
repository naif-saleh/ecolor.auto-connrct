<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ActivityLog;
use App\Models\UserActivityLog;
use App\Models\AutoDailerReport;
use App\Models\AutoDistributerReport;
use App\Models\Evaluation;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    // Active Log Report method...............................
    public function activityReport()
    {
        $logs = ActivityLog::with('user:id,name')
            ->orderBy('operation_time', 'desc')
            ->paginate(100);

        return view('reports.user_activity_report', compact('logs'));
    }

    public function UserActivityReport()
    {
        $logs = UserActivityLog::with('user:id,name')
            ->orderBy('created_at', 'desc')
            ->paginate(100);

        return view('reports.user_logs', compact('logs'));
    }


    // display Auto Dailer Report...........................................................................................................

    public function AutoDailerReports(Request $request)
    {
        $filter = $request->input('filter', 'today'); // Default to "today"
        $extensionFrom = $request->input('extension_from');
        $extensionTo = $request->input('extension_to');
        $provider = $request->input('provider');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        // Map filter values to database values
        $statusMap = [
            'answered' => ['Talking', 'Wexternalline'],
            'no answer' => ['Wspecialmenu', 'no answer', 'Dialing'],
        ];

        $query = AutoDailerReport::query();

        // Apply status filter (default to today)
        if ($filter === 'today') {
            // Filter by today's date
            $query->whereDate('created_at', now()->toDateString());
        } elseif ($filter === 'all') {
        } elseif (isset($statusMap[$filter])) {
            $query->whereIn('status', $statusMap[$filter]);
        }

        // Apply extension range filters
        if ($extensionFrom) {
            $query->where('extension', '>=', $extensionFrom);
        }

        if ($extensionTo) {
            $query->where('extension', '<=', $extensionTo);
        }

        // Apply provider filter
        if ($provider) {
            $query->where('provider', $provider);
        }

        // Apply date range filter
        if ($dateFrom && $dateTo) {
            $query->whereBetween('created_at', [
                \Carbon\Carbon::parse($dateFrom)->startOfDay(),
                \Carbon\Carbon::parse($dateTo)->endOfDay()
            ]);
        }

        // Apply pagination after filters
        $reports = $query->paginate(20);

        // Calculate counts for overall report
        $totalCount = AutoDailerReport::count();
        $answeredCount = AutoDailerReport::whereIn('status', ['Wextension', 'Wexternalline', "Talking"])->count();
        $noAnswerCount = AutoDailerReport::whereIn('status', ['Wspecialmenu', 'Dialing', 'no answer'])->count();

        // Calculate counts for "Today"
        $todayTotalCount = AutoDailerReport::whereDate('created_at', now()->toDateString())->count();
        $todayAnsweredCount = AutoDailerReport::whereDate('created_at', now()->toDateString())
            ->whereIn('status', ['Wextension', 'Wexternalline', "Talking"])
            ->count();
        $todayNoAnswerCount = AutoDailerReport::whereDate('created_at', now()->toDateString())
            ->whereIn('status', ['Wspecialmenu', 'Dialing', 'no answer'])
            ->count();

        // Fetch distinct providers for the filter dropdown
        $providers = AutoDailerReport::select('provider')->distinct()->get();

        return view('reports.auto_dailer_report', compact(
            'reports',
            'filter',
            'extensionFrom',
            'extensionTo',
            'provider',
            'providers',
            'totalCount',
            'answeredCount',
            'noAnswerCount',
            'todayTotalCount',
            'todayAnsweredCount',
            'todayNoAnswerCount'
        ));
    }







    // Export Auto Distributer AS CSV File...........................................................................................................
    public function exportAutoDailerReport(Request $request)
    {
        $filter = $request->query('filter');
        $extensionFrom = $request->input('extension_from');
        $extensionTo = $request->input('extension_to');
        $provider = $request->input('provider');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        $statusMap = [
            'answered' => ['Wexternalline', 'Talking'],
            'no answer' => ['no answer', 'Dialing'],
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
            fputcsv($handle, ['Mobile', 'Provider', 'Extension', 'State', 'Time', 'Date']);

            // Write each report row
            foreach ($reports as $report) {
                fputcsv($handle, [
                    $report->phone_number,
                    $report->provider,
                    $report->extension,
                    in_array($report->status, ['Wexternalline', 'Talking']) ? 'Answered' : 'No Answer',
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





    // display Auto Distributer Report...........................................................................................................
    public function AutoDistributerReports(Request $request)
    {
        $filter = $request->input('filter', 'today'); // Default to 'today' filter
        $extensionFrom = $request->input('extension_from');
        $extensionTo = $request->input('extension_to');
        $provider = $request->input('provider');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        // Map filter values to database values
        $statusMap = [
            'answered' => ['Talking', 'Wexternalline'],
            'no answer' => ['Wspecialmenu', 'no answer', 'Dialing', 'Routing'],
            'employee_unanswer' => ['Initiating', 'SomeOtherStatus']
        ];

        $query = AutoDistributerReport::query();

        // Apply status filter (default to today)
        if ($filter === 'today' || !$request->has('filter')) {
            $query->whereDate('created_at', now()->toDateString());
        } elseif ($filter === 'all') {
            // No filtering by date or status for 'All'
        } elseif (isset($statusMap[$filter])) {
            $query->whereIn('status', $statusMap[$filter]);
        }

        // Apply extension range filters
        if ($extensionFrom) {
            $query->where('extension', '>=', $extensionFrom);
        }
        if ($extensionTo) {
            $query->where('extension', '<=', $extensionTo);
        }

        // Apply provider filter
        if ($provider) {
            $query->where('provider', $provider);
        }

        // Apply date range filter
        if ($dateFrom && $dateTo) {
            $query->whereBetween('created_at', [
                \Carbon\Carbon::parse($dateFrom)->startOfDay(),
                \Carbon\Carbon::parse($dateTo)->endOfDay()
            ]);
        }

        // Apply pagination
        $reports = $query->paginate(50);

        // Calculate counts for overall report
        $totalCount = AutoDistributerReport::count();
        $answeredCount = AutoDistributerReport::whereIn('status', ['Wextension', 'Wexternalline', "Talking"])->count();
        $noAnswerCount = AutoDistributerReport::whereIn('status', ['Wspecialmenu', 'Dialing', 'no answer', 'Routing'])->count();
        $employeeUnanswerCount = AutoDistributerReport::whereIn('status', ['Initiating', 'SomeOtherStatus'])->count();

        // Calculate counts for "Today" (default view)
        $todayTotalCount = AutoDistributerReport::whereDate('created_at', now()->toDateString())->count();
        $todayAnsweredCount = AutoDistributerReport::whereDate('created_at', now()->toDateString())
            ->whereIn('status', ['Wextension', 'Wexternalline', "Talking"])->count();
        $todayNoAnswerCount = AutoDistributerReport::whereDate('created_at', now()->toDateString())
            ->whereIn('status', ['Wspecialmenu', 'Dialing', 'no answer', 'Routing'])->count();
        $todayEmployeeUnanswerCount = AutoDistributerReport::whereDate('created_at', now()->toDateString())
            ->whereIn('status', ['Initiating', 'SomeOtherStatus'])->count();

        // Fetch distinct providers for the filter dropdown
        $providers = AutoDistributerReport::select('provider')->distinct()->get();

        return view('reports.auto_distributer_report', compact(
            'reports',
            'filter',
            'extensionFrom',
            'extensionTo',
            'provider',
            'providers',
            'totalCount',
            'answeredCount',
            'noAnswerCount',
            'employeeUnanswerCount',
            'todayTotalCount',
            'todayAnsweredCount',
            'todayNoAnswerCount',
            'todayEmployeeUnanswerCount'
        ));
    }









    // Export Auto Distributer AS CSV File...........................................................................................................
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
            fputcsv($handle, ['Mobile', 'Provider', 'Extension', 'State', 'Time', 'Date']);

            // Write each report row
            foreach ($reports as $report) {
                fputcsv($handle, [
                    $report->phone_number,
                    $report->provider,
                    $report->extension,
                    //logic for status
                    $report->status === 'Talking' ? 'Answered' : ($report->status === 'Routing' ? 'No Answer' : ($report->status === 'Initiating' ? 'Employee No Answer' : 'No Answer')),
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


    // Evaluation.................................................................................................................

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

        if ($dateFrom) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        // Paginate the results
        $reports = $query->paginate(100);

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
        $headers = ['#', 'Mobile',  'Is Satisfied', 'Called At - Date', 'Called At - Time'];

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
