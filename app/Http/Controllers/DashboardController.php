<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\AutoDistributerReport;
use App\Models\AutoDailerReport;

class DashboardController extends Controller
{
    public function index()
    {
        // Fetch the total number of AutoDistributorCalls and AutoDialerCalls
        $autoDistributorCalls = AutoDistributerReport::count(); // Adjust query to your needs
        $autoDialerCalls = AutoDailerReport::count(); // Adjust query to your needs
        $total = $autoDialerCalls + $autoDistributorCalls;
        // Pass data to the view
        return view('dashboard', compact('autoDistributorCalls', 'autoDialerCalls', 'total'));
    }

    public function getCallManagerStatisticsAutoDailer()
    {
        $autoDistributorCalls = AutoDistributerReport::count(); // Adjust query to your needs
        $autoDialerCalls = AutoDailerReport::count(); // Adjust query to your needs
        $autoDailerAnswered = AutoDailerReport::where('status', 'Talking')->count();
        $autoDailerUnanswered = AutoDailerReport::where('status', 'Dialing')->count();

        $autoDistributorAnswered = AutoDistributerReport::where('status', 'Talking')->count();
        $autoDistributorUnanswered = AutoDistributerReport::where('status', 'Dialing')->count();

        return view('reports.manager_dashboard', compact(
            'autoDailerAnswered',
            'autoDailerUnanswered',
            'autoDistributorAnswered',
            'autoDistributorUnanswered',
            'autoDistributorCalls',
            'autoDialerCalls'
        ));

         
    }
}
