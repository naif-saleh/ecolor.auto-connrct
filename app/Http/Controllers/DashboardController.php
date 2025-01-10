<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\AutoDistributerReport;
use App\Models\AutoDailerReport;
use App\Models\AutoDistributorUploadedData;
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
        // Adjust queries to fetch the correct data from the models
        $autoDistributorCalls = AutoDistributerReport::count();
        $autoDialerCalls = AutoDailerReport::count();
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
        $from_date = $request->input('from_date', now()->startOfMonth()->toDateString());
        $to_date = $request->input('to_date', now()->toDateString());

        if (strpos($from_date, '/') !== false) {
            $from_date = \Carbon\Carbon::createFromFormat('d/m/Y', $from_date)->format('Y-m-d');
        }
        if (strpos($to_date, '/') !== false) {
            $to_date = \Carbon\Carbon::createFromFormat('d/m/Y', $to_date)->format('Y-m-d');
        }

        $extension = $request->input('extension');
        $provider = $request->input('provider');

        $query = AutoDailerReport::select(
            'extension',
            'provider',
            DB::raw('COUNT(DISTINCT phone_number) as unique_phone_numbers'), // Count unique phone numbers
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

        $reportData = $query->groupBy('extension', 'provider')->get();

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
        $from_date = $request->input('from_date', now()->startOfMonth()->toDateString());
        $to_date = $request->input('to_date', now()->toDateString());

        if (strpos($from_date, '/') !== false) {
            $from_date = \Carbon\Carbon::createFromFormat('d/m/Y', $from_date)->format('Y-m-d');
        }
        if (strpos($to_date, '/') !== false) {
            $to_date = \Carbon\Carbon::createFromFormat('d/m/Y', $to_date)->format('Y-m-d');
        }

        $extension = $request->input('extension');
        $provider = $request->input('provider');

        $query = AutoDistributerReport::select(
            'extension',
            'provider',
            DB::raw('COUNT(DISTINCT phone_number) as phone_number_count'), // Count unique phone numbers
            DB::raw('COUNT(*) as total_calls'),
            DB::raw('SUM(CASE WHEN status IN ("Talking", "Wexternalline") THEN 1 ELSE 0 END) as answered'),
            DB::raw('SUM(CASE WHEN status IN ("Wspecialmenu", "no answer", "Dialing", "Routing") THEN 1 ELSE 0 END) as unanswered'),
            DB::raw('SUM(CASE WHEN status IN ("Initiating", "None") THEN 1 ELSE 0 END) as unansweredByEmplooyee'),
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
        $extension = $request->input('extension');

        // Build the query with optional filters
        $query = AutoDistributerReport::select(
            'provider',
            'extension',
            DB::raw('COUNT(*) as total_calls'),
            DB::raw('SUM(CASE WHEN status IN ("Talking", "Wexternalline") THEN 1 ELSE 0 END) as answered'),
            DB::raw('SUM(CASE WHEN status IN ("Initiating", "None") THEN 1 ELSE 0 END) as unasweredEmplooyee'),
            DB::raw('SUM(CASE WHEN status IN ("Wspecialmenu", "no answer", "Dialing", "Routing") THEN 1 ELSE 0 END) as unanswered')
        )
            ->whereBetween('created_at', [
                \Carbon\Carbon::parse($from_date)->startOfDay(),
                \Carbon\Carbon::parse($to_date)->endOfDay()
            ]);



        if ($provider) {
            $query->where('provider', $provider);
        }


        $reportData = $query->groupBy('provider', 'extension')->paginate(100);

        return view('reports.manager.autoDistributorReports.autoDistributorProvider', compact('reportData', 'from_date', 'to_date', 'provider'));
    }





    // Compaign

    public function managerAutoDistributorsReportsCompaign(Request $request)
    {
        $from_date = $request->input('from_date', now()->startOfMonth()->toDateString());
        $to_date = $request->input('to_date', now()->toDateString());

        // Query for the statistics grouped by file_id and include user information
        $query = AutoDistributorUploadedData::select(
            'file_id',
            DB::raw('COUNT(DISTINCT extension) as total_extensions'),
            DB::raw('COUNT(*) as total_calls'),
            DB::raw('COUNT(DISTINCT mobile) as total_numbers'),
            DB::raw('SUM(CASE WHEN state = "Talking" THEN 1 ELSE 0 END) as answered'),
            DB::raw('SUM(CASE WHEN state = "Routing" THEN 1 ELSE 0 END) as unanswered'),
            DB::raw('SUM(CASE WHEN state = "Initiating" THEN 1 ELSE 0 END) as unanswered_employee'),
            DB::raw('SUM(CASE WHEN state = "new" THEN 1 ELSE 0 END) as new_count'),
            DB::raw('SUM(CASE WHEN state IN ("Talking", "Routing") THEN 1 ELSE 0 END) as talking_or_routing_count')
        )
            ->whereBetween('call_date', [
                \Carbon\Carbon::parse($from_date)->startOfDay(),
                \Carbon\Carbon::parse($to_date)->endOfDay()
            ])
            ->groupBy('file_id')
            ->paginate(100);

        // Load the file model for each file
        $query->load('file'); // This loads the file details for each result

        // Loop through each query result and load distinct users for each file
        foreach ($query as $data) {
            // Fetch distinct users related to the current file_id
            $users = AutoDistributorUploadedData::where('file_id', $data->file_id)
                ->distinct()
                ->pluck('user'); // Get all unique users for this file

            // Assign users to the current data record
            $data->users = $users;
        }

        // Logic to set the file status based on the conditions
        foreach ($query as $data) {
            // Check the conditions and assign status accordingly
            if ($data->new_count == $data->total_calls) {
                // All status are "new"
                $data->status = 'Not Started Yet';
            } elseif ($data->talking_or_routing_count > 0 && $data->talking_or_routing_count < $data->new_count) {
                // Some statuses are "Talking" or "Routing"
                $data->status = 'Ringing';
            } elseif ($data->new_count == 0) {
                // No "new" status in the file
                $data->status = 'Called';
            }
        }

        return view('reports.manager.autoDistributorReports.autoDistributorCompaign', compact('query', 'from_date', 'to_date'));
    }
}
