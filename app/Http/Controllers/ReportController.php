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

        $query = AutoDailerReport::query();

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

        $providers = AutoDailerReport::select('provider')->distinct()->pluck('provider');

        $reports = $query->paginate(10);

        $answeredCount = AutoDailerReport::where('state', 'answered')->count();
        $noAnswerCount = AutoDailerReport::where('state', 'no answer')->count();
        $calledCount = AutoDailerReport::where('state', 'called')->count();
        $declinedCount = AutoDailerReport::where('state', 'declined')->count();

        return view('reports.auto_dailer_report', compact(
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




    // Export Auto Dailer AS CSV File...........................................................................................................
    public function exportAutoDailerReport(Request $request)
    {

        $filter = $request->query('filter');
        $query = AutoDailerReport::query();

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

        $reports = $query->paginate(10);

        $answeredCount = AutoDistributerReport::where('state', 'answered')->count();
        $noAnswerCount = AutoDistributerReport::where('state', 'no answer')->count();
        $calledCount = AutoDistributerReport::where('state', 'called')->count();
        $declinedCount = AutoDistributerReport::where('state', 'declined')->count();

        return view('reports.auto_dailer_report', compact(
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
