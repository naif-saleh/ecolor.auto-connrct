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
use App\Services\LicenseService;

class ADistMakeCallCommand extends Command
{
    protected $signature = 'app:ADist-make-call-command';
    protected $description = 'Initiate auto-distributor calls';
    protected $threeCxService;

     /**
     * LicenseService Service
     *
     * @var LicenseService
     */
    protected $licenseService;


    public function __construct(ThreeCxService $threeCxService, LicenseService $licenseService)
    {
        parent::__construct();
        $this->threeCxService = $threeCxService;
        $this->licenseService = $licenseService;

    }

    public function handle()
    {
        Log::info('ADistMakeCallCommand executed at ' . Carbon::now());
        $timezone = config('app.timezone');
        Log::info("Using timezone: {$timezone}");

        $agents = ADistAgent::whereHas('files', function ($query) {
            $query->where('is_done', '!=', 'called')
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
                        ->whereDate('created_at', Carbon::today())
                        ->get();

                    Log::info('Feeds fetched for agent.', ['agent_id' => $agent->id, 'feeds_count' => $feeds->count()]);

                    foreach ($feeds as $feed) {
                        $isComplate = $this->checkIfFeedCompleted($feed, $agent);
                        $isGlobalWindow = $this->isWithinGlobalCallWindow($feed);
                        $isAgentWindow = $this->threeCxService->isWithinCallWindow($feed);
                        $remainingCalls = ADistData::where('feed_id', $feed->id)->where('state', '!=', 'new')->count();

                        $notCalled = ADistData::where('feed_id', $feed->id)
                            ->where('state', 'new')
                            ->count();

                        // Get new numbers to call
                        $dataItems = ADistData::where('feed_id', $feed->id)
                            ->where('state', 'new')
                            ->get();
                        $status = $remainingCalls == 0 ? "called" : ($remainingCalls === $dataItems ? 0 : "not_called");

                        // $feed->update(['is_done' => $status]);
                        // // Check call windows
                        // if ($feed->is_done !== "called") {
                        //     $newStatus = "calling";
                        //     $feed->update([
                        //         'is_done' => $newStatus,
                        //     ]);
                        //     Log::info("ADistMakeCallCommand: File '{$feed->file_name}' - Agent '{$agent->extension}'. file status updated to '{$newStatus}'");
                        // }

                        if (!$isGlobalWindow || !$isAgentWindow) {
                            if ($remainingCalls != 0 ) {
                                Log::info("ADistMakeCallCommand: 🚫 Time over for File '{$feed->file_name}' - Agent '{$agent->extension}'. Not completed.");
                                $feed->update(['is_done' => "not_called"]);
                            } elseif ($notCalled === $dataItems->count()) {
                                Log::info("ADistMakeCallCommand: ⏳ File '{$feed->file_name}' - Agent '{$agent->extension}' has not started yet.");
                                $feed->update(['is_done' => 0]);
                            } elseif ($isComplate) {
                                Log::info("ADistMakeCallCommand: ✅ All numbers called for File '{$feed->file_name}' - Agent '{$agent->extension}'. file status updated to '{$feed->is_done}' - slug: {$feed->slug}");
                            }
                            Log::info("ADistMakeCallCommand: 🚫 Time is not within for File '{$feed->file_name}' - Agent '{$agent->extension}'");
                            continue;
                        } else {
                            if ($isComplate) {
                                Log::info("ADistMakeCallCommand: ✅ All numbers called for File '{$feed->file_name}' - Agent '{$agent->extension}'. File status updated to '{$feed->is_done}' - slug: {$feed->slug}.");
                                continue;
                            } else {

                                if ($this->checkIfFeedCompleted($feed, $agent)) {
                                    Log::info("ADistMakeCallCommand: ✅ All numbers called for File '{$feed->file_name}' - Agent '{$agent->extension}'. file status updated to '{$feed->is_done}' - slug: {$feed->slug}");
                                }else{
                                    Log::info("ADistMakeCallCommand: 📝 File {$feed->file_name} - Agent '{$agent->extension}' is calling.");
                                    $feed->update(['is_done' => "calling"]);
                                }
                            }
                            Log::info("ADistMakeCallCommand: ✅ Time is within for File '{$feed->file_name}' - Agent '{$agent->extension}'");
                        }




                        foreach ($dataItems as $dataItem) {


                            // ✅ Attempt to make the call
                            Log::info("☎️ Attempting call to {$dataItem->mobile}");

                            try {
                                $this->checkLicenseAndMakeCall($agent, $dataItem, $callResponse);

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
     * Check license validity and make call if license is valid
     *
     * @param  object  $dataItem
     * @param  object  $agent
     * @return void
     */
    private function checkLicenseAndMakeCall($agent, $dataItem, &$callResponse)
    {
        try {
            // Check if license is valid (not expired and active)
            if (! $this->licenseService->isLicenseValid()) {
                if ($this->licenseService->isLicenseExpaired()) {
                    Log::info('Auto-Distributor: ❌ License expired. Please renew your license.') ;
                    return false;
                }

                if (! $this->licenseService->isLicenseActive()) {
                    Log::info('Auto-Distributor: 🚫 License not activated. Please activate your license.');
                    return false;
                }

                Log::info('Auto-Distributor: ❌ Your license is not valid. Please check your license status.') ;
                return false;
            }

            // Get Auto Distributor module settings
            $moduleSettings = $this->licenseService->getModuleSettings('auto_distributor_moduales');

            if (! $moduleSettings) {
                Log::info('Auto-Distributor: 🚫 Auto Distributor module is not enabled in your license. Please upgrade.') ;
                return false;
            }


            if (!$this->licenseService->checkDistCallsCount()) {
                Log::info('Auto-Distributor: 🚫 Maximum Calls limit reached. Please upgrade your license. ') ;
                return false;
            }

            // All checks passed
            $callResponse = $this->threeCxService->makeCallDist($agent, $dataItem->mobile);
             $this->licenseService->decrementDistCalls();
        } catch (\Exception $e) {
            report($e); // Log the exception

            Log::info('Auto-Distributor: An error occurred while checking license. Please try again later.') ;
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
            Log::info("ADistMakeCallCommand: ✅ All numbers called for File '{$feed->file_name}' - Agent '{$agent->extension}. file status updated to '{$feed->is_done}' - slug: {$feed->slug}.");
        } else {
            Log::info("ADistMakeCallCommand: 📝 File {$feed->file_name} has {$remainingCalls} calls remaining.");
        }
    }
}
