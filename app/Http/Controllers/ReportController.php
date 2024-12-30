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

    // Map filter values to database values
    $statusMap = [
        'answered' => ['Wextension', 'Wexternalline'],
        'no answer' => ['Wspecialmenu', 'no answer'],
    ];


    $query = AutoDailerReport::query();

    // Apply status filter
    if ($filter && isset($statusMap[$filter])) {
        $query->whereIn('status', $statusMap[$filter]);
    }

    // Apply extension range filters
    if ($extensionFrom) {
        $query->where('provider', '>=', $extensionFrom);
    }

    if ($extensionTo) {
        $query->where('provider', '<=', $extensionTo);
    }

    $reports = $query->paginate(20);

    // Calculate counts using mapped status values
    $answeredCount = AutoDailerReport::whereIn('status', ['Wextension','Wexternalline'])->count();
    $noAnswerCount = AutoDailerReport::whereIn('status', ['Wspecialmenu', 'no answer'])->count();


    return view('reports.auto_dailer_report', compact(
        'reports',
        'filter',
        'extensionFrom',
        'extensionTo',
        'answeredCount',
        'noAnswerCount'
    ));
}





    // Export Auto Dailer AS CSV File...........................................................................................................


public function exportAutoDailerReport(Request $request)
{
    $filter = $request->query('filter');

    // Map filter to corresponding database status values
    $statusMap = [
        'answered' => 'Wextension',
        'no answer' => 'Wspecialmenu',
    ];

    $query = AutoDailerReport::query();

    if ($filter && isset($statusMap[$filter])) {
        $query->where('status', $statusMap[$filter]);
    }

    $reports = $query->get();

    $response = new StreamedResponse(function () use ($reports) {
        $handle = fopen('php://output', 'w');

        // Write the CSV header
        fputcsv($handle, ['Mobile', 'Provider', 'State', 'Called At']);

        // Write each report row
        foreach ($reports as $report) {
            fputcsv($handle, [
                $report->phone_number,
                $report->provider,
                ucfirst(($report->status === 'Wextension') ? 'answered' : 'no answer'),
                $report->created_at,
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

        $query = AutoDistributerReport::query();

        if ($filter) {
            $query->where('state', $filter);
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

        $providers = AutoDistributerReport::select('provider')->distinct()->pluck('provider');

        $reports = $query->paginate(20);

        $answeredCount = AutoDistributerReport::where('state', 'answered')->count();
        $noAnswerCount = AutoDistributerReport::where('state', 'no answer')->count();
        $calledCount = AutoDistributerReport::where('state', 'called')->count();
        $declinedCount = AutoDistributerReport::where('state', 'declined')->count();

        return view('reports.auto_distributer_report', compact(
            'reports',
            'filter',
            'extensionFrom',
            'extensionTo',
            'provider',
            'providers',
            'answeredCount',
            'noAnswerCount',
            'calledCount',
            'declinedCount'
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
