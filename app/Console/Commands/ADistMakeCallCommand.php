<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\ADistAgent;
use App\Models\ADistFeed;
use App\Models\ADistData;
use App\Models\AutoDistributerReport;
use App\Services\TokenService;
use DateTime;
use Illuminate\Support\Facades\Cache;

class ADistMakeCallCommand extends Command
{
    protected $signature = 'app:ADist-make-call-command';
    protected $description = 'Initiate auto-distributor calls';
    protected $tokenService;

    public function __construct(TokenService $tokenService)
    {
        parent::__construct();
        $this->tokenService = $tokenService;
    }

    public function handle()
    {
        Log::info('ADistMakeCallCommand executed at ' . Carbon::now());

        try {
            $client = new Client([
                'base_uri' => config('services.three_cx.api_url'),
                'headers' => [
                    'Accept' => 'application/json'
                ],
            ]);

            $token = $this->tokenService->getToken();

            // Get active calls
            try {
                $activeResponse = $client->get('/xapi/v1/ActiveCalls', [
                    'headers' => ['Authorization' => "Bearer $token"],
                    'timeout' => 3
                ]);

                $activeCalls = json_decode($activeResponse->getBody(), true);

                // Check only very recent pending calls
                $pendingCalls = AutoDistributerReport::where('status', 'Initiating')
                    ->where('created_at', '>=', now()->subSeconds(30))
                    ->get();

                // Check only active calls in our system (reduced time window)
                $activeSysCalls = AutoDistributerReport::whereIn('status', ['Initiating', 'InProgress', 'Ringing'])
                    ->where('created_at', '>=', now()->subSeconds(30))  // Only check last 30 seconds
                    ->whereNotIn('status', ['Ended', 'Completed', 'Failed', 'NoAnswer'])  // Explicitly exclude finished calls
                    ->get();

            } catch (RequestException $e) {
                Log::error("ADistMakeCallCommand ❌ Failed to fetch call status: " . $e->getMessage());
                return;
            }

            $activeCallsList = $activeCalls['value'] ?? [];
            Log::info("ADistMakeCallCommand Active Calls Retrieved: " . print_r($activeCallsList, true));

            // Enhanced busy extensions tracking with reason logging
            $busyExtensions = [];
            $busyReasons = [];

            // Track from 3CX active calls
            foreach ($activeCallsList as $call) {
                $busyExtensions[$call['Caller']] = true;
                $busyReasons[$call['Caller']] = "Active 3CX call as caller with {$call['Callee']}";

                if (isset($call['Callee']) && strlen($call['Callee']) <= 6) {
                    $busyExtensions[$call['Callee']] = true;
                    $busyReasons[$call['Callee']] = "Active 3CX call as receiver from {$call['Caller']}";
                }
            }

            // Track from very recent pending calls
            foreach ($pendingCalls as $call) {
                $busyExtensions[$call->extension] = true;
                $busyReasons[$call->extension] = "Pending call initiated at {$call->created_at}";
            }

            // Track from active system calls
            foreach ($activeSysCalls as $call) {
                $busyExtensions[$call->extension] = true;
                $busyReasons[$call->extension] = "Active system call in {$call->status} status since {$call->created_at}";
            }

            // Get available agents
            $agents = ADistAgent::all();

            foreach ($agents as $agent) {
                Log::info("ADistMakeCallCommand Processing Agent {$agent->id} ({$agent->extension})");

                // Check current 3CX/System call status
                if (isset($busyExtensions[$agent->extension])) {
                    Log::info("ADistMakeCallCommand ⚠️ Agent {$agent->id} ({$agent->extension}) is busy: {$busyReasons[$agent->extension]}");
                    continue;
                }

                // Check only very recent system calls
                $activeSystemCall = AutoDistributerReport::where('extension', $agent->extension)
                    ->whereIn('status', ['Initiating', 'InProgress', 'Ringing'])
                    ->where('created_at', '>=', now()->subSeconds(30))
                    ->whereNotIn('status', ['Ended', 'Completed', 'Failed', 'NoAnswer'])
                    ->first();

                if ($activeSystemCall) {
                    Log::info("ADistMakeCallCommand ⚠️ Agent {$agent->id} ({$agent->extension}) has active system call: Status {$activeSystemCall->status} since {$activeSystemCall->created_at}");
                    continue;
                }

                // Check if agent is available
                if ($agent->status !== "Available") {
                    Log::info("ADistMakeCallCommand Agent {$agent->id} ({$agent->extension}) not available - Status: {$agent->status}");
                    continue;
                }

                // Check for very recent pending calls
                $pendingCall = ADistData::where('state', 'Initiating')
                    ->where('updated_at', '>=', now()->subSeconds(30))
                    ->whereExists(function ($query) use ($agent) {
                        $query->from('a_dist_feeds')
                            ->whereColumn('a_dist_feeds.id', 'a_dist_data.feed_id')
                            ->where('a_dist_feeds.agent_id', $agent->id);
                    })
                    ->first();

                if ($pendingCall) {
                    Log::info("ADistMakeCallCommand ⚠️ Agent {$agent->id} ({$agent->extension}) has pending call initiated at {$pendingCall->updated_at}");
                    continue;
                }

                // Use lock to prevent race conditions
                $lockKey = "agent_call_lock_{$agent->id}";
                if (!Cache::add($lockKey, true, now()->addSeconds(10))) {
                    Log::info("ADistMakeCallCommand Agent {$agent->id} ({$agent->extension}) is locked by another process");
                    continue;
                }

                try {
                    // Find eligible feeds
                    $feeds = ADistFeed::where('agent_id', $agent->id)
                        ->whereDate('date', today())
                        ->where('allow', true)
                        ->where('is_done', false)
                        ->get();

                    foreach ($feeds as $feed) {
                        $from = Carbon::parse("{$feed->date} {$feed->from}")->subHours(3);
                        $to = Carbon::parse("{$feed->date} {$feed->to}")->subHours(3);

                        if (!now()->between($from, $to)) {
                            continue;
                        }

                        // Get one new call
                        $feedData = ADistData::where('feed_id', $feed->id)
                            ->where('state', 'new')
                            ->lockForUpdate()
                            ->first();

                        if (!$feedData) {
                            continue;
                        }

                        try {
                            // Final busy check before making the call
                            if (isset($busyExtensions[$agent->extension])) {
                                Log::info("ADistMakeCallCommand ⚠️ Agent {$agent->id} ({$agent->extension}) became busy before call initiation: {$busyReasons[$agent->extension]}");
                                continue;
                            }

                            // Get devices
                            $devicesResponse = $client->get("/callcontrol/{$agent->extension}/devices", [
                                'headers' => ['Authorization' => "Bearer $token"],
                                'timeout' => 2
                            ]);

                            $dnDevices = json_decode($devicesResponse->getBody(), true);

                            foreach ($dnDevices as $device) {
                                if ($device['user_agent'] !== '3CX Mobile Client') continue;

                                // Make the call
                                $responseState = $client->post("/callcontrol/{$agent->extension}/devices/{$device['device_id']}/makecall", [
                                    'headers' => ['Authorization' => "Bearer $token"],
                                    'json' => ['destination' => $feedData->mobile],
                                    'timeout' => 2
                                ]);

                                $responseData = json_decode($responseState->getBody(), true);

                                // Update records in transaction
                                DB::transaction(function() use ($responseData, $feedData) {
                                    AutoDistributerReport::create([
                                        'call_id' => $responseData['result']['callid'],
                                        'status' => "Initiating",
                                        'provider' => $feedData->file->file_name,
                                        'extension' => $responseData['result']['dn'],
                                        'phone_number' => $responseData['result']['party_caller_id'],
                                        'attempt_time' => now(),
                                    ]);

                                    $feedData->update([
                                        'state' => "Initiating",
                                        'call_date' => now(),
                                        'call_id' => $responseData['result']['callid'],
                                    ]);
                                });

                                Log::info("ADistMakeCallCommand 📞 Call initiated for {$feedData->mobile} by Agent {$agent->id} ({$agent->extension})");
                                break 3; // Exit all loops after successful call
                            }
                        } catch (RequestException $e) {
                            Log::error("ADistMakeCallCommand ❌ Call failed for {$feedData->mobile}: " . $e->getMessage());
                        }
                    }

                    // Update feed status
                    ADistFeed::where('agent_id', $agent->id)
                        ->whereDate('date', today())
                        ->whereNotExists(function ($query) {
                            $query->from('a_dist_data')
                                ->whereColumn('feed_id', 'a_dist_feeds.id')
                                ->where('state', 'new');
                        })
                        ->update(['is_done' => true]);

                } finally {
                    Cache::forget($lockKey);
                }
            }
        } catch (\Exception $e) {
            Log::error("ADistMakeCallCommand ❌ General error: " . $e->getMessage());
        }
    }
}
