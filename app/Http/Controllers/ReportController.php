<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ActivityLog;

class ReportController extends Controller
{
    // Active Log Report method...............................
    public function activityReport()
{
    $logs = ActivityLog::with('user:id,name')
                ->orderBy('operation_time', 'desc')
                ->paginate(1);

    return view('reports.user_activity_report', compact('logs'));
}

}
