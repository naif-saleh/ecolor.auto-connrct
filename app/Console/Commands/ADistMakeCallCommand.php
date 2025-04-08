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

        $agents = ADistAgent::whereHas('files', function ($query) {
            $query->whereDate('date', today())->where('allow', true);
        })->get();

        foreach ($agents as $agent) {
            try {
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
                    // Standard time checks and other validations...
                    // ...

                    // ✅ Fetch Feeds
                    $feeds = ADistFeed::where('agent_id', $agent->id)
                        ->where('allow', true)
                        ->where('is_done', false)
                        ->whereDate('date', today())
                        ->get();


                    foreach ($feeds as $feed) {
                        // Update each feed individually
                        $feed->update(['is_done' => 'calling']);

                        // Check if feed is within call window in general time
                        if (!$this->isWithinGlobalCallWindow($feed) || !$this->threeCxService->isWithinCallWindow($feed)) {
                            $remainingCalls = ADistData::where('feed_id', $feed->id)->where('state', 'new')->count();
                            $status = $remainingCalls == 0 ? "called" : "not_called";
                            $feed->update(['is_done' => $status]);

                            $logMessage = $status === "called"
                                ? "✅ All numbers called for File '{$feed->file_name}'."
                                : "🚫 Time over for File '{$feed->file_name}'. Not completed.";
                            Log::info("ADistMakeCallCommand: {$logMessage}");

                            return;
                        }

                        // Find the next eligible number to call (only one per execution)
                        $dataItem = ADistData::where('feed_id', $feed->id)
                            ->where('state', 'new')
                            ->first();

                        if (!$dataItem) {
                            $remainingCalls = ADistData::where('feed_id', $feed->id)->where('state', 'new')->count();
                            $status = $remainingCalls == 0 ? "called" : "not_called";
                            $feed->update(['is_done' => $status]);

                            $logMessage = $status === "called"
                                ? "✅ All numbers called for File '{$feed->file_name}'."
                                : "📝 File '{$feed->file_name}' has {$remainingCalls} calls remaining.";
                            Log::info("ADistMakeCallCommand: {$logMessage}");

                            continue; // No new numbers to call
                        }

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
                        } catch (\Exception $e) {
                            Log::error("❌ Call to {$dataItem->mobile} failed: " . $e->getMessage());
                        }

                        // Process only one number per execution
                        break;
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
     * Check if current time is within global call window
     *
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
            Log::info('ADialMakeCallCommand: 🕒🚫📞 Outside global call time.');
            Log::info("ADialMakeCallCommand: 📁 File '{$feed->file_name}' marked as not_called.");
            return false;
        }

        return true;
    }
}
