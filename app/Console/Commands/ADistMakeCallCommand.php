<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\ADistAgent;
use App\Models\ADistFeed;
use App\Models\ADistData;
use App\Models\AutoDistributerReport;
use App\Models\General_Setting;
use App\Services\ThreeCxService;
use Illuminate\Support\Facades\Cache;

class ADistMakeCallCommand extends Command
{
    protected $signature = 'app:ADist-make-call-command';
    protected $description = 'Initiate auto-distributor calls';
    protected $threeCxService;

    public function __construct(ThreeCxService $threeCxService)
    {
        parent::__construct();
        $this->threeCxService = $threeCxService;
    }

    public function handle()
    {
        Log::info('ADistMakeCallCommand executed at ' . Carbon::now());
        $timezone = config('app.timezone');
        Log::info("Using timezone: {$timezone}");

        // Count agents and log
        $agents = ADistAgent::whereHas('files', function ($query) {
            $query->whereDate('date', today())->where('allow', true);
        })->get();

        Log::info("Found " . $agents->count() . " agents with files scheduled for today");

        foreach ($agents as $agent) {
            try {
                Log::info("Processing agent {$agent->id} with extension {$agent->extension}");

                // ✅ Check if agent is available for a call
                if ($this->threeCxService->isAgentInCall($agent)) {
                    Log::info("⚠️ Agent {$agent->id} ({$agent->extension}) is currently in a call.");
                    continue; // Skip the entire agent if they are in a call
                }

                if ($agent->status !== "Available") {
                    Log::info("⏳ Agent {$agent->id} ({$agent->extension}) is not available - Status: {$agent->status}");
                    continue; // Skip the entire agent if they are not available
                }

                // ✅ Lock agent to avoid multiple processes
                $lockKey = "agent_call_lock_{$agent->id}";
                if (!Cache::add($lockKey, true, now()->addSeconds(10))) {
                    Log::info("🚫 Agent {$agent->id} is locked by another process");
                    continue; // Skip agent if they are locked
                }

                try {
                    // ✅ Fetch Feeds
                    $feeds = ADistFeed::where('agent_id', $agent->id)
                        ->where('allow', true)
                        ->where('is_done', false)
                        ->whereDate('date', today())
                        ->get();

                    Log::info("Found " . $feeds->count() . " feeds for agent {$agent->id}");

                    // Process each feed
                    foreach ($feeds as $feed) {
                        Log::info("Processing feed {$feed->id} with time window from {$feed->from} to {$feed->to}");

                        // Check if feed is within global call window
                        if (!$this->isWithinGlobalCallWindow($feed)) {
                            Log::info("🚫 Feed {$feed->id} is NOT within global call window");
                            continue; // Continue to next feed
                        }

                        // Check if feed is within its specific call window
                        if (!$this->threeCxService->isWithinCallWindow($feed)) {
                            Log::info("🚫 Feed {$feed->id} is NOT within the allowed time range.");
                            continue;
                        }

                        Log::info("✅ Feed {$feed->id} is within the allowed time window");

                        // Now that we know this feed is ready to be processed, mark it as "calling"
                        $feed->update(['is_done' => 'calling']);

                        // Find the next eligible number to call (only one per execution)
                        $dataItem = ADistData::where('feed_id', $feed->id)
                            ->where('state', 'new')
                            ->first();

                        if (!$dataItem) {
                            Log::info("❌ No new numbers to call for feed {$feed->id}");
                            // No new numbers to call - check if all numbers are processed
                            $this->checkIfFeedCompleted($feed);
                            continue;
                        }

                        Log::info("📞 Found number to call: {$dataItem->mobile} for feed {$feed->id}");

                        // Check if there are any ongoing calls for this agent
                        $ongoingCall = AutoDistributerReport::where('extension', $agent->extension)
                            ->whereIn('status', ['Initiating', 'In Progress'])
                            ->exists();

                        if ($ongoingCall) {
                            Log::info("⏳ Agent {$agent->id} has an ongoing call in database. Skipping new call.");
                            continue;
                        }

                        // Make the call
                        Log::info("☎️ Attempting call to {$dataItem->mobile}");
                        try {
                            $callResponse = $this->threeCxService->makeCallDist($agent, $dataItem->mobile);
                            Log::info("✅ Call initiated. Response: " . json_encode($callResponse));

                            DB::transaction(function () use ($callResponse, $dataItem, $agent, $feed) {
                                AutoDistributerReport::create([
                                    'call_id' => $callResponse['result']['callid'],
                                    'status' => "Initiating",
                                    'extension' => $agent->extension,
                                    'phone_number' => $callResponse['result']['party_caller_id'],
                                    'provider' => $feed->file_name,
                                ]);
                                $dataItem->update(['state' => "Initiating", 'call_id' => $callResponse['result']['callid']]);
                            });

                            // Check if this was the last number in the feed
                            $this->checkIfFeedCompleted($feed);

                            // Process only one number per execution
                            break;
                        } catch (\Exception $e) {
                            Log::error("❌ Call to {$dataItem->mobile} failed: " . $e->getMessage());
                            $dataItem->update(['state' => "Failed"]);

                            // Check if this was the last number in the feed
                            $this->checkIfFeedCompleted($feed);
                        }
                    }
                } finally {
                    Cache::forget($lockKey);
                }
            } catch (\Exception $e) {
                Log::error("❌ General error: " . $e->getMessage());
            }
        }
    }

    /**
     * Check if a feed has any remaining calls and update status accordingly
     *
     * @param ADistFeed $feed
     * @return void
     */
    protected function checkIfFeedCompleted(ADistFeed $feed)
    {
        $remainingCalls = ADistData::where('feed_id', $feed->id)->where('state', 'new')->count();
        if ($remainingCalls == 0) {
            $feed->update(['is_done' => "called"]);
            Log::info("ADistMakeCallCommand: ✅ All numbers called for File '{$feed->file_name}'.");
        } else {
            Log::info("ADistMakeCallCommand: 📝 File {$feed->file_name} has {$remainingCalls} calls remaining.");
        }
    }

    /**
     * Check if current time is within global call window
     *
     * @param ADistFeed $feed
     * @return bool
     */
    protected function isWithinGlobalCallWindow($feed)
    {
        $timezone = config('app.timezone');
        $callTimeStart = General_Setting::get('call_time_start');
        $callTimeEnd = General_Setting::get('call_time_end');

        if (!$callTimeStart || !$callTimeEnd) {
            Log::warning("ADistMakeCallCommand: ⚠️ Call time settings not configured.");
            return false;
        }

        $now = now()->timezone($timezone);
        $globalStart = Carbon::parse(date('Y-m-d') . ' ' . $callTimeStart)->timezone($timezone);
        $globalEnd = Carbon::parse(date('Y-m-d') . ' ' . $callTimeEnd)->timezone($timezone);

        if (!$now->between($globalStart, $globalEnd)) {
            Log::info('ADistMakeCallCommand: 🕒🚫📞 Outside global call time.');
            $feed->update(['is_done' => 'not_called']);
            Log::info("ADistMakeCallCommand: 📁 File '{$feed->file_name}' marked as not_called.");
            return false;
        }

        return true;
    }
}
