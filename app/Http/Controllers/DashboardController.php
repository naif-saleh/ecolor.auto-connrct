<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\AutoDistributerReport;
use App\Models\AutoDailerReport;
use Illuminate\Support\Facades\DB;

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


    // Auto Dailers.........................................................................................

    // Extensions
    public function managerAutoDailersReports(Request $request)
    {
        // Parse input dates to ensure they are in the correct format
        $from_date = $request->input('from_date', now()->startOfMonth()->toDateString());
        $to_date = $request->input('to_date', now()->toDateString());

        // Convert date format from dd/mm/yyyy to yyyy-mm-dd if needed
        if (strpos($from_date, '/') !== false) {
            $from_date = \Carbon\Carbon::createFromFormat('d/m/Y', $from_date)->format('Y-m-d');
        }
        if (strpos($to_date, '/') !== false) {
            $to_date = \Carbon\Carbon::createFromFormat('d/m/Y', $to_date)->format('Y-m-d');
        }

        $extension = $request->input('extension');
        $provider = $request->input('provider');

        // Build the query with optional filters
        $query = AutoDailerReport::select(
            'extension',
            'provider',
            DB::raw('COUNT(*) as total_calls'),
            DB::raw('SUM(CASE WHEN status IN ("Talking", "Wexternalline") THEN 1 ELSE 0 END) as answered'),
            DB::raw('SUM(CASE WHEN status IN ("Wspecialmenu", "no answer", "Dialing", "Routing") THEN 1 ELSE 0 END) as unanswered')
        )
            ->whereBetween('created_at', [
                \Carbon\Carbon::parse($from_date)->startOfDay(),
                \Carbon\Carbon::parse($to_date)->endOfDay()
            ]);

        if ($extension) {
            $query->where('extension', $extension);
        }

        if ($provider) {
            $query->where('provider', $provider);
        }

        $reportData = $query->groupBy('extension', 'provider')->paginate(100);

        return view('reports.manager.autoDailersReports.autoDailerExtension', compact('reportData', 'from_date', 'to_date', 'extension', 'provider'));
    }



    // Providers
    public function managerAutoDailersReportsProvider(Request $request)
    {
        // Parse input dates to ensure they are in the correct format
        $from_date = $request->input('from_date', now()->startOfMonth()->toDateString());
        $to_date = $request->input('to_date', now()->toDateString());

        // Convert date format from dd/mm/yyyy to yyyy-mm-dd if needed
        if (strpos($from_date, '/') !== false) {
            $from_date = \Carbon\Carbon::createFromFormat('d/m/Y', $from_date)->format('Y-m-d');
        }
        if (strpos($to_date, '/') !== false) {
            $to_date = \Carbon\Carbon::createFromFormat('d/m/Y', $to_date)->format('Y-m-d');
        }

        $provider = $request->input('provider');

        // Build the query with optional filters
        $query = AutoDailerReport::select(
            'provider',
            DB::raw('COUNT(*) as total_calls'),
            DB::raw('SUM(CASE WHEN status IN ("Talking", "Wexternalline") THEN 1 ELSE 0 END) as answered'),
            DB::raw('SUM(CASE WHEN status IN ("Wspecialmenu", "no answer", "Dialing", "Routing") THEN 1 ELSE 0 END) as unanswered')
        )
            ->whereBetween('created_at', [
                \Carbon\Carbon::parse($from_date)->startOfDay(),
                \Carbon\Carbon::parse($to_date)->endOfDay()
            ]);



        if ($provider) {
            $query->where('provider', $provider);
        }

        $reportData = $query->groupBy('provider')->paginate(100);

        return view('reports.manager.autoDailersReports.autoDailerProviders', compact('reportData', 'from_date', 'to_date', 'provider'));
    }












    // Auto Distributor.........................................................................................

    // Extensions
    public function managerAutoDistributorsReports(Request $request)
    {
        // Parse input dates to ensure they are in the correct format
        $from_date = $request->input('from_date', now()->startOfMonth()->toDateString());
        $to_date = $request->input('to_date', now()->toDateString());

        // Convert date format from dd/mm/yyyy to yyyy-mm-dd if needed
        if (strpos($from_date, '/') !== false) {
            $from_date = \Carbon\Carbon::createFromFormat('d/m/Y', $from_date)->format('Y-m-d');
        }
        if (strpos($to_date, '/') !== false) {
            $to_date = \Carbon\Carbon::createFromFormat('d/m/Y', $to_date)->format('Y-m-d');
        }

        $extension = $request->input('extension');
        $provider = $request->input('provider');

        // Build the query with optional filters
        $query = AutoDistributerReport::select(
            'extension',
            'provider',
            DB::raw('COUNT(*) as total_calls'),
            DB::raw('SUM(CASE WHEN status IN ("Talking", "Wexternalline") THEN 1 ELSE 0 END) as answered'),
            DB::raw('SUM(CASE WHEN status IN ("Wspecialmenu", "no answer", "Dialing", "Routing") THEN 1 ELSE 0 END) as unanswered')
        )
            ->whereBetween('created_at', [
                \Carbon\Carbon::parse($from_date)->startOfDay(),
                \Carbon\Carbon::parse($to_date)->endOfDay()
            ]);

        if ($extension) {
            $query->where('extension', $extension);
        }

        if ($provider) {
            $query->where('provider', $provider);
        }

        $reportData = $query->groupBy('extension', 'provider')->paginate(100);

        return view('reports.manager.autoDistributorReports.autoDistributorExtension', compact('reportData', 'from_date', 'to_date', 'extension', 'provider'));
    }


    // Providers
    public function managerAutoDistributorsReportsProvider(Request $request)
    {
        // Parse input dates to ensure they are in the correct format
        $from_date = $request->input('from_date', now()->startOfMonth()->toDateString());
        $to_date = $request->input('to_date', now()->toDateString());

        // Convert date format from dd/mm/yyyy to yyyy-mm-dd if needed
        if (strpos($from_date, '/') !== false) {
            $from_date = \Carbon\Carbon::createFromFormat('d/m/Y', $from_date)->format('Y-m-d');
        }
        if (strpos($to_date, '/') !== false) {
            $to_date = \Carbon\Carbon::createFromFormat('d/m/Y', $to_date)->format('Y-m-d');
        }

        $provider = $request->input('provider');

        // Build the query with optional filters
        $query = AutoDistributerReport::select(
            'provider',
            DB::raw('COUNT(*) as total_calls'),
            DB::raw('SUM(CASE WHEN status IN ("Talking", "Wexternalline") THEN 1 ELSE 0 END) as answered'),
            DB::raw('SUM(CASE WHEN status IN ("Wspecialmenu", "no answer", "Dialing", "Routing") THEN 1 ELSE 0 END) as unanswered')
        )
            ->whereBetween('created_at', [
                \Carbon\Carbon::parse($from_date)->startOfDay(),
                \Carbon\Carbon::parse($to_date)->endOfDay()
            ]);



        if ($provider) {
            $query->where('provider', $provider);
        }

        $reportData = $query->groupBy('provider')->paginate(100);

        return view('reports.manager.autoDistributorReports.autoDistributorProvider', compact('reportData', 'from_date', 'to_date', 'provider'));
    }
}
