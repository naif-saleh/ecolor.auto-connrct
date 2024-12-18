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
    $filter = $request->query('filter');
    $query = AutoDailerReport::query();

    if (in_array($filter, ['answered', 'no answer', 'called'])) {
        $query->where('state', $filter);
    }

    $reports = $query->paginate(1000);

    $answeredCount = AutoDailerReport::where('state', 'answered')->count();
    $noAnswerCount = AutoDailerReport::where('state', 'no answer')->count();
    $calledCount = AutoDailerReport::where('state', 'called')->count(); // Added "called" count

    return view('reports.auto_dailer_report', [
        'reports' => $reports,
        'answeredCount' => $answeredCount,
        'noAnswerCount' => $noAnswerCount,
        'calledCount' => $calledCount, // Pass "called" count to view
        'filter' => $filter,
    ]);
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

         $filter = $request->query('filter');
         $query = AutoDistributerReport::query();

         if ($filter === 'answered' || $filter === 'no answer') {
             $query->where('state', $filter);
         }
         $reports = $query->paginate(1000);

         $answeredCount = AutoDistributerReport::where('state', 'answered')->count();
         $noAnswerCount = AutoDistributerReport::where('state', 'no answer')->count();

         return view('reports.auto_distributer_report', [
             'reports' => $reports,
             'answeredCount' => $answeredCount,
             'noAnswerCount' => $noAnswerCount,
             'filter' => $filter,
         ]);
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
