<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ActivityLog;
use App\Models\UserActivityLog;
use App\Models\AutoDailerReport;
use App\Models\AutoDistributerReport;
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

        return view('reports', compact('logs'));
    }


    // display Auto Dailer Report...........................................................................................................

    public function AutoDailerReports(Request $request)
    {
        $filter = $request->input('filter');
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

        // Apply status filter
        if ($filter === 'today') {
            $query->whereDate('created_at', now()->toDateString());
        } elseif ($filter && isset($statusMap[$filter])) {
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

        $reports = $query->paginate(20);

        // Calculate counts
        $totalCount = AutoDailerReport::count(); // Total calls count
        $answeredCount = AutoDailerReport::whereIn('status', ['Wextension', 'Wexternalline', "Talking"])->count();
        $noAnswerCount = AutoDailerReport::whereIn('status', ['Wspecialmenu', 'Dialing', 'no answer'])->count();

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
            'noAnswerCount'
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
                    $report->created_at->addHours(3)->format('H:i:s')
                ]);
            }

            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition', 'attachment; filename="auto_distributor_report.csv"');

        return $response;
    }




    // display Auto Distributer Report...........................................................................................................
    public function AutoDistributerReports(Request $request)
    {
        $filter = $request->input('filter');
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

        $query = AutoDistributerReport::query();

        // Apply status filter
        if ($filter === 'today') {
            $query->whereDate('created_at', now()->toDateString());
        } elseif ($filter && isset($statusMap[$filter])) {
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

        $reports = $query->paginate(20);

        // Calculate counts
        $totalCount = AutoDistributerReport::count(); // Total calls count
        $answeredCount = AutoDistributerReport::whereIn('status', ['Wextension', 'Wexternalline', "Talking"])->count();
        $noAnswerCount = AutoDistributerReport::whereIn('status', ['Wspecialmenu', 'Dialing', 'no answer'])->count();

        // Fetch distinct providers for the filter dropdown
        $providers = AutoDistributerReport::select('provider')->distinct()->get();

        return view('reports.auto_dailer_report', compact(
            'reports',
            'filter',
            'extensionFrom',
            'extensionTo',
            'provider',
            'providers',
            'totalCount',
            'answeredCount',
            'noAnswerCount'
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
            'answered' => ['Wexternalline', 'Talking'],
            'no answer' => ['no answer', 'Dialing'],
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
                    in_array($report->status, ['Wexternalline', 'Talking']) ? 'Answered' : 'No Answer',
                    $report->created_at->addHours(3)->format('H:i:s'),
                    $report->created_at->addHours(3)->format('H:i:s')
                ]);
            }

            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition', 'attachment; filename="auto_distributor_report.csv"');

        return $response;
    }
}
