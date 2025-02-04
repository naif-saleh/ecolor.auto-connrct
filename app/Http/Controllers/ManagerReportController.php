<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ADialProvider;
use App\Models\ADistAgent;
use App\Models\ADistFeed;
use App\Models\ADialFeed;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ManagerReportController extends Controller
{




    public function dialerReportsProviders(Request $request)
    {
        $query = ADialProvider::select(
            'a_dial_providers.extension as extension',
            'a_dial_providers.name as provider',
            DB::raw("SUM(CASE WHEN a_dial_data.state = 'Talking' THEN 1 ELSE 0 END) as answered"),
            DB::raw("SUM(CASE WHEN a_dial_data.state = 'Routing' THEN 1 ELSE 0 END) as unanswered"),
            DB::raw("SUM(CASE WHEN a_dial_data.state NOT IN ('Talking', 'Routing') THEN 1 ELSE 0 END) as failed"),
            DB::raw("SUM(CASE WHEN a_dial_data.state != 'new' THEN 1 ELSE 0 END) as total_calls"),
            DB::raw("COUNT(DISTINCT a_dial_data.mobile) as total_numbers"),
            DB::raw("COUNT(DISTINCT a_dial_feeds.id) as uploads_count")
        )
            ->leftJoin('a_dial_feeds', 'a_dial_providers.id', '=', 'a_dial_feeds.provider_id')
            ->leftJoin('a_dial_data', 'a_dial_feeds.id', '=', 'a_dial_data.feed_id')
            ->groupBy('a_dial_providers.extension', 'a_dial_providers.name');

        // Date filter logic
        if ($request->has('date_from') && $request->has('date_to')) {
            $dateFrom = Carbon::parse($request->date_from)->startOfDay(); // Start of the day
            $dateTo = Carbon::parse($request->date_to)->endOfDay(); // End of the day
            $query->whereBetween('a_dial_feeds.date', [$dateFrom, $dateTo]);
        }

        // Default to today if no filter is applied
        if ($request->get('date_from') === null && $request->get('date_to') === null && !$request->has('filter')) {
            $today = Carbon::today(); // Get today's date
            $query->whereDate('a_dial_feeds.date', $today);
        }

        // Filter by provider name if provided
        if ($request->has('provider_name') && $request->provider_name != '') {
            $query->where('a_dial_providers.name', 'LIKE', '%' . $request->provider_name . '%');
        }

        // Execute the query and paginate results
        $report = $query->paginate(20);

        return view('reports.manager.autoDailersReports.autoDailerProviders', compact('report'));
    }


    public function dialerReportsCompaign(Request $request)
    {
        $query = ADialFeed::with(['uploadedData', 'provider'])
            ->where('is_done', 0); // Only active campaigns

        // Apply date filtering if dates are provided
        if ($request->has('date_from') && $request->has('date_to')) {
            $query->whereBetween('created_at', [$request->date_from . ' 00:00:00', $request->date_to . ' 23:59:59']);
        }

        // Paginate results with 10 records per page
        $report = $query->paginate(20);

        // Prepare data for the report
        $campaignReports = $report->map(function ($feed) {
            $totalCalls = $feed->uploadedData->where('state', '!=', 'new')->count();
            $answered = $feed->uploadedData->where('state', 'Talking')->count();
            $unanswered = $feed->uploadedData->where('state', 'Routing')->count();
            $failed = $feed->uploadedData->where('state', 'failed')->count();
            $calling = $feed->uploadedData->whereIn('state', ['Routing', 'Talking'])->count() > 0;
            $status = $calling ? 'Calling' : ($feed->is_done ? 'Completed' : 'Not Started Yet');
            $totalNumbers = $feed->uploadedData->pluck('mobile')->unique()->count();

            return [
                'file_name' => $feed->file_name,
                'from' => $feed->from,
                'to' => $feed->to,
                'status' => $status,
                'number_of_extensions' => $feed->provider->count(),
                'provider' => $feed->provider->pluck('name')->join(', '),
                'answered' => $answered,
                'unanswered' => $unanswered,
                'failed' => $failed,
                'total_calls' => $totalCalls,
                'total_numbers' => $totalNumbers
            ];
        });

        return view('reports.manager.autoDailersReports.autoDailerCompaign', [
            'campaignReports' => $campaignReports,
            'reports' => $report, // Pass paginated results for Laravel pagination links
            'date_from' => $request->date_from,
            'date_to' => $request->date_to
        ]);
    }








    public function distributorReportsProviders(Request $request)
    {
        $query = ADistAgent::select(
            'a_dist_agents.extension as extension',
            'a_dist_agents.displayName as provider',
            DB::raw("SUM(CASE WHEN a_dial_data.state = 'Talking' THEN 1 ELSE 0 END) as answered"),
            DB::raw("SUM(CASE WHEN a_dial_data.state = 'Routing' THEN 1 ELSE 0 END) as unanswered"),
            DB::raw("SUM(CASE WHEN a_dial_data.state = 'Initiating' THEN 1 ELSE 0 END) as failed"),
            DB::raw("SUM(CASE WHEN a_dial_data.state != 'new' THEN 1 ELSE 0 END) as total_calls"),
            DB::raw("COUNT(DISTINCT a_dial_data.mobile) as total_numbers"),
            DB::raw("COUNT(DISTINCT a_dist_feeds.id) as uploads_count")
        )
            ->leftJoin('a_dist_feeds', 'a_dist_agents.id', '=', 'a_dist_feeds.agent_id')
            ->leftJoin('a_dial_data', 'a_dist_feeds.id', '=', 'a_dial_data.feed_id')
            ->groupBy('a_dist_agents.extension', 'a_dist_agents.displayName');

        // Date filter logic
        if ($request->has('date_from') && $request->has('date_to')) {
            $dateFrom = Carbon::parse($request->date_from)->startOfDay(); // Start of the day
            $dateTo = Carbon::parse($request->date_to)->endOfDay(); // End of the day
            $query->whereBetween('a_dist_feeds.date', [$dateFrom, $dateTo]);
        }

        // Default to today if no filter is applied
        if ($request->get('date_from') === null && $request->get('date_to') === null && !$request->has('filter')) {
            $today = Carbon::today(); // Get today's date
            $query->whereDate('a_dist_feeds.date', $today);
        }

        // Filter by provider name if provided
        if ($request->has('provider_name') && $request->provider_name != '') {
            $query->where('a_dial_providers.name', 'LIKE', '%' . $request->provider_name . '%');
        }

        // Execute the query and paginate results
        $report = $query->paginate(20);

        return view('reports.manager.autoDistributorReports.autoDistributorAgents', compact('report'));
    }


    public function distributorReportsCompaign(Request $request)
    {
        $query = ADistFeed::with(['uploadedData', 'agent'])->where('is_done', 0);

        // Apply date filter if provided
        if ($request->has('date_from') && $request->has('date_to')) {
            $query->whereBetween('created_at', [$request->date_from, $request->date_to]);
        }

        $report = $query->paginate(20);

        // Prepare data for the report
        $campaignReports = $report->map(function ($feed) {
            $totalCalls = $feed->uploadedData->where('state', '!=', 'new')->count();
            $answered = $feed->uploadedData->where('state', 'Talking')->count();
            $unanswered = $feed->uploadedData->where('state', 'Routing')->count();
            $failed = $feed->uploadedData->where('state', 'Initiating')->count();

            // Check if any call is still active
            $calling = $feed->uploadedData->whereIn('state', ['Routing', 'Talking', 'Initiating'])->count() > 0;
            $status = $calling ? 'Calling' : ($feed->is_done ? 'Completed' : 'Not Started Yet');

            // Calculate total numbers
            $totalNumbers = $feed->uploadedData->pluck('mobile')->unique()->count();

            return [
                'file_name' => $feed->file_name,
                'from' => $feed->from,
                'to' => $feed->to,
                'status' => $status,
                'number_of_extensions' => $feed->agent->count(),
                'provider' => $feed->agent->pluck('displayName')->join(', '),
                'answered' => $answered,
                'unanswered' => $unanswered,
                'failed' => $failed,
                'total_calls' => $totalCalls,
                'total_numbers' => $totalNumbers
            ];
        });

        return view('reports.manager.autoDistributorReports.autoDistributorCompaign', [
            'campaignReports' => $campaignReports,
            'reports' => $report,
            'noResults' => $campaignReports->isEmpty()
        ]);
    }
}
