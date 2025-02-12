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

            // Get both active and dialing calls
            try {
                $activeResponse = $client->get('/xapi/v1/ActiveCalls', [
                    'headers' => ['Authorization' => "Bearer $token"]
                ]);

                $activeCalls = json_decode($activeResponse->getBody(), true);

                // Also check pending/dialing calls from our database
                $pendingCalls = AutoDistributerReport::where('status', 'Initiating')
                    ->where('created_at', '>=', now()->subMinutes(5))
                    ->get();

            } catch (RequestException $e) {
                Log::error("ADistMakeCallCommand ❌ Failed to fetch call status: " . $e->getMessage());
                return;
            }

            $activeCallsList = $activeCalls['value'] ?? [];
            Log::info("ADistMakeCallCommand Active Calls Retrieved: " . print_r($activeCallsList, true));

            // Create a map of busy agents (including both active and dialing calls)
            $busyAgents = [];
            foreach ($activeCallsList as $call) {
                $busyAgents[$call['Caller']] = true;
            }

            // Add agents with pending/dialing calls to busy list
            foreach ($pendingCalls as $call) {
                $busyAgents[$call->extension] = true;
            }

            $agents = ADistAgent::all();

            foreach ($agents as $agent) {
                // Double check if agent has ANY recent calls (active, dialing, or pending)
                if (isset($busyAgents[$agent->extension])) {
                    Log::info("ADistMakeCallCommand 🚫 Agent {$agent->id} ({$agent->extension}) is busy with active or pending call.");
                    continue;
                }

                // Additional check for pending calls in our system
                $hasPendingCall = ADistData::where('state', 'Initiating')
                    ->where('updated_at', '>=', now()->subMinutes(5))
                    ->whereExists(function ($query) use ($agent) {
                        $query->from('a_dist_feeds')
                            ->whereColumn('a_dist_feeds.id', 'a_dist_data.feed_id')
                            ->where('a_dist_feeds.agent_id', $agent->id);
                    })
                    ->exists();

                if ($hasPendingCall) {
                    Log::info("ADistMakeCallCommand 🚫 Agent {$agent->id} has pending call in system.");
                    continue;
                }

                // Skip if agent isn't available
                if ($agent->status !== "Available") {
                    Log::info("ADistMakeCallCommand 📵 Agent {$agent->id} not available");
                    continue;
                }

                // Lock mechanism to prevent race conditions
                $lockKey = "agent_call_lock_{$agent->id}";
                if (Cache::has($lockKey)) {
                    Log::info("ADistMakeCallCommand 🔒 Agent {$agent->id} has an active lock");
                    continue;
                }

                // Set a lock for 30 seconds
                Cache::put($lockKey, true, now()->addSeconds(30));

                try {
                    // Find eligible feeds for this agent
                    $feeds = ADistFeed::where('agent_id', $agent->id)
                        ->whereDate('date', today())
                        ->where('allow', true)
                        ->get();

                    $callMade = false;
                    foreach ($feeds as $feed) {
                        $from = Carbon::parse("{$feed->date} {$feed->from}")->subHours(3);
                        $to = Carbon::parse("{$feed->date} {$feed->to}")->subHours(3);

                        if (!now()->between($from, $to)) {
                            continue;
                        }

                        // Fetch only one new call
                        $feedData = ADistData::where('feed_id', $feed->id)
                            ->where('state', 'new')
                            ->first();

                        if (!$feedData) {
                            continue;
                        }

                        try {
                            // Fetch devices for agent
                            $devicesResponse = $client->get("/callcontrol/{$agent->extension}/devices", [
                                'headers' => ['Authorization' => "Bearer $token"]
                            ]);

                            $dnDevices = json_decode($devicesResponse->getBody(), true);

                            foreach ($dnDevices as $device) {
                                if ($device['user_agent'] !== '3CX Mobile Client') continue;

                                // Final check before making call
                                if (isset($busyAgents[$agent->extension])) {
                                    Log::info("ADistMakeCallCommand 🛑 Agent became busy during processing");
                                    break;
                                }

                                try {
                                    $responseState = $client->post("/callcontrol/{$agent->extension}/devices/{$device['device_id']}/makecall", [
                                        'headers' => ['Authorization' => "Bearer $token"],
                                        'json' => ['destination' => $feedData->mobile]
                                    ]);

                                    $responseData = json_decode($responseState->getBody(), true);

                                    AutoDistributerReport::updateOrCreate([
                                        'call_id' => $responseData['result']['callid'],
                                    ], [
                                        'status' => "Initiating",
                                        'provider' => $responseData['result']['dn'],
                                        'extension' => $responseData['result']['dn'],
                                        'phone_number' => $responseData['result']['party_caller_id'],
                                    ]);

                                    $feedData->update([
                                        'state' => "Initiating",
                                        'call_date' => now(),
                                        'call_id' => $responseData['result']['callid'],
                                    ]);

                                    Log::info("ADistMakeCallCommand 📞 Call initiated successfully for {$feedData->mobile}");
                                    $callMade = true;
                                    break;
                                } catch (RequestException $e) {
                                    Log::error("ADistMakeCallCommand ❌ Failed to make call to {$feedData->mobile}: " . $e->getMessage());
                                }
                            }

                            if ($callMade) break;
                        } catch (\Exception $e) {
                            Log::error("ADistMakeCallCommand ❌ Error fetching devices: " . $e->getMessage());
                        }
                    }
                } finally {
                    // Always remove the lock
                    Cache::forget($lockKey);
                }

                // Check and update feed status
                foreach ($feeds ?? [] as $feed) {
                    if (!ADistData::where('feed_id', $feed->id)->where('state', 'new')->exists()) {
                        $feed->update(['is_done' => true]);
                        Log::info("ADIST ✅✅✅ All numbers called for feed ID: {$feed->id}");
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error("ADistMakeCallCommand ❌ General error: " . $e->getMessage());
        }
    }
}
