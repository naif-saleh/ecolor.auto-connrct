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
                if (!Cache::add($lockKey, true, now()->addMinutes(30))) { // Extended lock time
                    Log::info("🚫 Agent {$agent->id} is locked by another process");
                    continue; // Skip agent if they are locked
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
                        $numbers = ADistData::where('feed_id', $feed->id)
                            ->where('state', 'new')
                            ->orderBy('id') // Ensure consistent order
                            ->get();

                        if ($numbers->isEmpty()) {
                            Log::info("No new numbers found for feed {$feed->id}");
                            continue;
                        }

                        // Process one number at a time, tracking completion status
                        foreach ($numbers as $dataItem) {
                            // Double-check agent status before each call
                            if (!$this->checkAgentReadiness($agent)) {
                                Log::info("⚠️ Agent {$agent->id} no longer ready for calls");
                                break; // Exit the loop if agent is not ready
                            }

                            Log::info("☎️ Attempting call to {$dataItem->mobile} for feed {$feed->id}");

                            try {
                                // ✅ Make the call
                                $callResponse = $this->threeCxService->makeCallDist($agent, $dataItem->mobile);
                                $callId = $callResponse['result']['callid'];

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

                                // ✅ Wait for call to complete before processing next number
                                $this->waitForCallToComplete($agent, $callId);
                            } catch (\Exception $e) {
                                Log::error("❌ Call to {$dataItem->mobile} failed: " . $e->getMessage());
                                $dataItem->update(['state' => "Failed"]);
                                // Wait briefly before trying next number after failure
                                sleep(3);
                            }
                        }
                    }
                } finally {
                    Cache::forget($lockKey);
                }
            } catch (\Exception $e) {
                Log::error("❌ General error processing agent {$agent->id}: " . $e->getMessage());
            }
        }
    }

    /**
     * Check if agent is ready to make calls
     *
     * @param ADistAgent $agent
     * @return bool
     */
    private function checkAgentReadiness(ADistAgent $agent)
    {
        // Refresh agent status
        $agent->refresh();

        // Check if agent is in a call
        if ($this->threeCxService->isAgentInCall($agent)) {
            return false;
        }

        // Check if agent is available
        if ($agent->status !== "Available") {
            return false;
        }

        return true;
    }

    /**
     * Wait for a call to complete before moving to the next number
     *
     * @param ADistAgent $agent
     * @param string $callId
     * @return void
     */
    private function waitForCallToComplete(ADistAgent $agent, $callId)
    {
        Log::info("⏳ Monitoring call $callId for completion");

        $maxWaitTime = 300; // Max wait time (5 minutes)
        $checkInterval = 5; // Check every 5 seconds
        $elapsed = 0;

        while ($elapsed < $maxWaitTime) {
            // Check if agent is still in a call
            if (!$this->threeCxService->isAgentInCall($agent)) {
                Log::info("✅ Call $callId completed - Agent no longer in call");

                // Update call status in database
                $report = AutoDistributerReport::where('call_id', $callId)->first();
                if ($report) {
                    $report->update(['status' => 'Completed', 'end_time' => now()]);
                }

                // Wait a moment before allowing next call
                sleep(2);
                return;
            }

            // Wait before checking again
            sleep($checkInterval);
            $elapsed += $checkInterval;
        }

        // If we reach here, the call exceeded the maximum wait time
        Log::warning("⚠️ Timeout waiting for call $callId to complete after {$maxWaitTime} seconds");

        // Update report with timeout status
        $report = AutoDistributerReport::where('call_id', $callId)->first();
        if ($report) {
            $report->update(['status' => 'Timeout']);
        }
    }
}
