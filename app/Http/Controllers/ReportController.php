<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ActivityLog;
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


    // display Auto Dailer Report...........................................................................................................

    public function AutoDailerReports(Request $request)
    {
        $filter = $request->input('filter');
        $extensionFrom = $request->input('extension_from');
        $extensionTo = $request->input('extension_to');
        $provider = $request->input('provider');

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

        $reports = $query->paginate(20);

        // Calculate counts
        $totalCount = AutoDailerReport::count(); // Total calls count
        $answeredCount = AutoDailerReport::whereIn('status', ['Wextension', 'Wexternalline', "Talking"])->count();
        $noAnswerCount = AutoDailerReport::whereIn('status', ['Wspecialmenu','Dialing', 'no answer'])->count();

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





    // Export Auto Dailer AS CSV File...........................................................................................................


    public function exportAutoDailerReport(Request $request)
    {
        $filter = $request->query('filter');
        $extensionFrom = $request->input('extension_from');
        $extensionTo = $request->input('extension_to');
        $provider = $request->input('provider');

        // Map filter to corresponding database status values
        $statusMap = [
            'answered' => ['Wextension', 'Wexternalline', 'Talking'],
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

        // Apply provider filter (ensure provider is not empty or null)
        if (!empty($provider)) {
            $query->where('provider', $provider);
        }

        $reports = $query->get();

        $response = new StreamedResponse(function () use ($reports) {
            $handle = fopen('php://output', 'w');

            // Write the CSV header
            fputcsv($handle, ['Mobile', 'Provider', 'extension', 'State', 'Called At']);

            // Write each report row
            foreach ($reports as $report) {
                fputcsv($handle, [
                    $report->phone_number,
                    $report->provider,
                    $report->extension,
                    ucfirst(($report->status === 'Wextension' || $report->status === 'Wexternalline') ? 'answered' : 'no answer'),
                    \Carbon\Carbon::parse($report->created_at)->addHours(3)->format('Y-m-d H:i:s')
                ]);
            }

            fclose($handle);
        });

        // Set the response headers for CSV download
        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition', 'attachment; filename="auto_dailer_report.csv"');

        return $response;
    }





    // display Auto Distributer Report...........................................................................................................
    public function AutoDistributerReports(Request $request)
    {
        $filter = $request->input('filter');
        $extensionFrom = $request->input('extension_from');
        $extensionTo = $request->input('extension_to');
        $provider = $request->input('provider');

        // Map filter values to database values
        $statusMap = [
            'answered' => ['Wextension', 'Wexternalline'],
            'no answer' => ['Dialing', 'no answer'],
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

        $reports = $query->paginate(20);

        // Calculate counts
        $totalCount = AutoDistributerReport::count(); // Total calls count
        $answeredCount = AutoDistributerReport::whereIn('status', ['Wextension', 'Wexternalline'])->count();
        $noAnswerCount = AutoDistributerReport::whereIn('status', ['Dialing', 'no answer'])->count();

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
            'noAnswerCount'
        ));
    }



    // Export Auto Distributer AS CSV File...........................................................................................................
    public function exportAutoDistributerReport(Request $request)
    {

        $filter = $request->query('filter');
        $query = AutoDistributerReport::query();

        if ($filter === 'answered' || $filter === 'no answer') {
            $query->where('state', $filter);
        }

        $reports = $query->get();

        $response = new StreamedResponse(function () use ($reports) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, ['Mobile', 'Provider', 'Extension', 'State', 'Called At']);

            foreach ($reports as $report) {
                fputcsv($handle, [
                    $report->mobile,
                    $report->provider,
                    $report->extension,
                    $report->state,
                    $report->called_at,
                ]);
            }

            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition', 'attachment; filename="auto_dailer_report.csv"');

        return $response;
    }
}
