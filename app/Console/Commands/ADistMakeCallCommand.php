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
                    continue;
                }

                if ($agent->status !== "Available") {
                    Log::info("⏳ Agent {$agent->id} ({$agent->extension}) is not available - Status: {$agent->status}");
                    continue;
                }

                // ✅ Lock agent to avoid multiple processes
                $lockKey = "agent_call_lock_{$agent->id}";
                if (!Cache::add($lockKey, true, now()->addSeconds(10))) {
                    Log::info("🚫 Agent {$agent->id} is locked by another process");
                    continue;
                }

                try {
                    // ✅ Fetch call time settings
                    $callTimeStart = General_Setting::get('call_time_start');
                    $callTimeEnd = General_Setting::get('call_time_end');
                    if (!$callTimeStart || !$callTimeEnd) {
                        Log::warning("⚠️ Call time settings missing. Configure allowed call hours.");
                        continue;
                    }

                    $globalTodayStart = Carbon::parse(today()->format('Y-m-d') . " $callTimeStart", $timezone);
                    $globalTodayEnd = Carbon::parse(today()->format('Y-m-d') . " $callTimeEnd", $timezone);
                    $now = now()->timezone($timezone);

                    if (!$now->between($globalTodayStart, $globalTodayEnd)) {
                        Log::info("⏱️ Current time {$now} is outside allowed call hours.");
                        continue;
                    }

                    // ✅ Fetch Feeds
                    $feeds = ADistFeed::where('agent_id', $agent->id)
                        ->where('allow', true)
                        ->where('is_done', false)
                        ->whereDate('date', today())
                        ->get();

                    foreach ($feeds as $feed) {
                        // ✅ Check if current time is within allowed time window
                        if (!$this->threeCxService->isWithinCallWindow($feed)) {
                            Log::info("🚫 Feed {$feed->id} is NOT within the allowed time range.");
                            continue;
                        }

                        // ✅ Process each feed
                        $feedData = ADistData::where('feed_id', $feed->id)
                            ->where('state', 'new')
                            ->get();

                        foreach ($feedData as $dataItem) {
                            // Check agent availability before each call
                            if ($agent->status !== "Available") {
                                Log::info("⏳ Agent {$agent->id} ({$agent->extension}) is no longer available.");
                                break; // Stop processing numbers if agent becomes unavailable
                            }

                            Log::info("☎️ Attempting call to {$dataItem->mobile}");

                            try {
                                // ✅ Make the call using the ThreeCxService
                                $callResponse = $this->threeCxService->makeCallDist($agent, $dataItem->mobile);

                                // ✅ Update database with call details
                                DB::transaction(function () use ($callResponse, $dataItem, $agent, $feed) {
                                    AutoDistributerReport::create([
                                        'call_id' => $callResponse['result']['callid'],
                                        'status' => "Initiating",
                                        'extension' => $agent->extension,
                                        'phone_number' => $callResponse['result']['party_caller_id'],
                                        'provider' => $feed->file_name,
                                        'attempt_time' => now(),
                                    ]);
                                    $dataItem->update(['state' => "Initiating", 'call_id' => $callResponse['result']['callid']]);
                                });
                            } catch (\Exception $e) {
                                Log::error("❌ Call to {$dataItem->mobile} failed: " . $e->getMessage());
                                $dataItem->update(['state' => "Failed"]);
                            }

                            sleep(2); // Add a delay between calls
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
}
