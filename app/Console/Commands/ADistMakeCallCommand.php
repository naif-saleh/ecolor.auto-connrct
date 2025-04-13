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
use App\Notifications\AgentCallFailed;

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
            $query->where('is_done', '!=' , 'called')
                  ->where('allow', true)
                  ->whereDate('created_at', Carbon::today());
        })->get();
        Log::info('Agents query executed.', ['agents_count' => $agents->count()]);

        if ($agents->isEmpty()) {
            Log::warning('⚠️ No agents with allowed files found for today.');
        }

        foreach ($agents as $agent) {
            try {
                // Skip if agent is already on a call
                if ($this->threeCxService->isAgentInCall($agent)) {
                    Log::info("⚠️ Agent {$agent->id} ({$agent->extension}) is currently in a call.");
                    continue;
                }

                // Skip if agent is not available
                if ($agent->status !== "Available") {
                    Log::info("⏳ Agent {$agent->id} ({$agent->extension}) is not available - Status: {$agent->status}");
                    continue;
                }

                // Lock the agent process to prevent race condition
                $lockKey = "agent_call_lock_{$agent->id}";
                if (!Cache::add($lockKey, true, now()->addSeconds(10))) {
                    Log::info("🚫 Agent {$agent->id} is locked by another process");
                    continue;
                }

                try {
                    // Fetch feeds for this agent
                    $feeds = ADistFeed::where('agent_id', $agent->id)
                        ->where('allow', true)
                        ->where('is_done', '!=', 'called')
                        ->whereDate('date', today())
                        ->get();

                    Log::info('Feeds fetched for agent.', ['agent_id' => $agent->id, 'feeds_count' => $feeds->count()]);

                    foreach ($feeds as $feed) {
                        // Check call windows
                        $feed->update([
                            'is_done' => "calling",
                        ]);

                        // Get new numbers to call
                        $dataItems = ADistData::where('feed_id', $feed->id)
                            ->where('state', 'new')
                            ->get();

                        $isComplate = $this->checkIfFeedCompleted($feed, $agent);
                        $isGlobalWindow = $this->isWithinGlobalCallWindow($feed);
                        $isAgentWindow = $this->threeCxService->isWithinCallWindow($feed);
                        $remainingCalls = ADistData::where('feed_id', $feed->id)->where('state', '!=', 'new')->count();

                        $notCalled = ADistData::where('feed_id', $feed->id)
                            ->where('state', 'new')
                            ->count();
                        $status = $remainingCalls == 0 ? "called" : ($remainingCalls === $dataItems ? 0 : "not_called");
                        $feed->update(['is_done' => $status]);



                        if (!$isGlobalWindow || !$isAgentWindow) {
                            if (!$isComplate) {
                                Log::info("ADistMakeCallCommand: 🚫 Time over for File '{$feed->file_name}' - Agent '{$agent->extension}'. Not completed.");
                                $feed->update(['is_done' => "not_called"]);
                            } elseif ($notCalled === $dataItems->count()) {
                                Log::info("ADistMakeCallCommand: ⏳ File '{$feed->file_name}' - Agent '{$agent->extension}' has not started yet.");
                                $feed->update(['is_done' => 0]);
                            } elseif ($isComplate) {
                                Log::info("ADistMakeCallCommand: ✅ All numbers called for File '{$feed->file_name}' - Agent '{$agent->extension}'.");
                                $feed->update(['is_done' => "called"]);
                            }
                            Log::info("ADistMakeCallCommand: 🚫 Time is not within for File '{$feed->file_name}' - Agent '{$agent->extension}'");
                            continue;
                        } else {
                            if($isComplate) {
                                Log::info("ADistMakeCallCommand: ✅ All numbers called for File '{$feed->file_name}' - Agent '{$agent->extension}'.");

                            } else {
                                Log::info("ADistMakeCallCommand: 📝 File {$feed->file_name} is calling.");
                                $feed->update(['is_done' => "calling"]);
                            }
                            Log::info("ADistMakeCallCommand: ✅ Time is within for File '{$feed->file_name}' - Agent '{$agent->extension}'");

                        }




                        foreach ($dataItems as $dataItem) {


                            // ✅ Attempt to make the call
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
                                    $dataItem->update([
                                        'state' => "Initiating",
                                        'call_id' => $callResponse['result']['callid'],
                                    ]);
                                });

                                break; // ✅ Only make one call per agent per execution
                            } catch (\Exception $e) {
                                Log::error("☎️❌ Call to {$dataItem->mobile} failed: " . $e->getMessage());
                            }
                        }

                        break; // ✅ Stop after processing one feed per agent
                    }
                } finally {
                    Cache::forget($lockKey);
                }
            } catch (\Exception $e) {
                Log::error("❌ General error for agent {$agent->id}: " . $e->getMessage());
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
            return false;
        }

        return true;
    }


    /**
     * Check if a feed has any remaining calls and update status accordingly
     *
     * @param ADistFeed $feed
     * @return void
     */
    protected function checkIfFeedCompleted(ADistFeed $feed, ADistAgent $agent)
    {
        $remainingCalls = ADistData::where('feed_id', $feed->id)->where('state', 'new')->count();
        if ($remainingCalls == 0) {
            $feed->update(['is_done' => "called"]);
            Log::info("ADistMakeCallCommand: ✅ All numbers called for File '{$feed->file_name}' - Agent '{$agent->extension}.");
        } else {
            Log::info("ADistMakeCallCommand: 📝 File {$feed->file_name} has {$remainingCalls} calls remaining.");
        }
    }
}
