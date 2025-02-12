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
                'headers' => ['Accept' => 'application/json'],
            ]);

            $token = $this->tokenService->getToken();

            // ✅ Auto-reset stale calls (older than 2 minutes)
            AutoDistributerReport::whereIn('status', ['Initiating', 'InProgress', 'Ringing'])
                ->where('created_at', '<', now()->subMinutes(2))
                ->update(['status' => 'Ended']);

            // ✅ Get latest active calls from 3CX API
            $activeCallsResponse = $client->get('/xapi/v1/ActiveCalls', [
                'headers' => ['Authorization' => "Bearer $token"],
                'timeout' => 3
            ]);

            $activeCalls = json_decode($activeCallsResponse->getBody(), true);
            $activeCallsList = $activeCalls['value'] ?? [];

            Log::info("📞 Active calls from 3CX API: " . print_r($activeCallsList, true));

            // ✅ Track busy extensions
            $busyExtensions = [];
            $busyReasons = [];

            foreach ($activeCallsList as $call) {
                $busyExtensions[$call['Caller']] = true;
                $busyReasons[$call['Caller']] = "Active 3CX call as caller with {$call['Callee']}";

                if (isset($call['Callee']) && strlen($call['Callee']) <= 6) {
                    $busyExtensions[$call['Callee']] = true;
                    $busyReasons[$call['Callee']] = "Active 3CX call as receiver from {$call['Caller']}";
                }
            }

            // ✅ Get agents
            $agents = ADistAgent::all();

            foreach ($agents as $agent) {
                Log::info("🔍 Checking agent {$agent->id} ({$agent->extension})");

                // ✅ Final busy check before processing the agent
                $isBusy = isset($busyExtensions[$agent->extension]);

                if ($isBusy) {
                    Log::warning("⚠️ Agent {$agent->id} ({$agent->extension}) is STILL busy. Reason: " . ($busyReasons[$agent->extension] ?? 'Unknown'));
                    continue;
                }

                if ($agent->status !== "Available") {
                    Log::info("⏳ Agent {$agent->id} ({$agent->extension}) is not available - Status: {$agent->status}");
                    continue;
                }

                // ✅ Lock agent to prevent race conditions
                $lockKey = "agent_call_lock_{$agent->id}";
                if (!Cache::add($lockKey, true, now()->addSeconds(10))) {
                    Log::info("🚫 Agent {$agent->id} ({$agent->extension}) is locked by another process");
                    continue;
                }

                try {
                    // ✅ Find eligible feeds
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

                        // ✅ Get one new call
                        $feedData = ADistData::where('feed_id', $feed->id)
                            ->where('state', 'new')
                            ->lockForUpdate()
                            ->first();

                        if (!$feedData) {
                            Cache::forget($lockKey);
                            Log::info("✅ Unlocking agent {$agent->id} ({$agent->extension}) - No available call data.");
                            continue;
                        }

                        try {
                            // ✅ Final verification before making the call
                            $finalCheck = AutoDistributerReport::where('extension', $agent->extension)
                                ->whereIn('status', ['Initiating', 'InProgress', 'Ringing'])
                                ->whereNotIn('status', ['Ended', 'Completed', 'Failed', 'NoAnswer'])
                                ->exists();

                            if ($finalCheck) {
                                Log::info("⚠️ Agent {$agent->id} ({$agent->extension}) became busy before call initiation");
                                continue;
                            }

                            // ✅ Get agent devices
                            $devicesResponse = $client->get("/callcontrol/{$agent->extension}/devices", [
                                'headers' => ['Authorization' => "Bearer $token"],
                                'timeout' => 2
                            ]);

                            $dnDevices = json_decode($devicesResponse->getBody(), true);

                            foreach ($dnDevices as $device) {
                                if ($device['user_agent'] !== '3CX Mobile Client') continue;

                                // ✅ Make the call
                                $responseState = $client->post("/callcontrol/{$agent->extension}/devices/{$device['device_id']}/makecall", [
                                    'headers' => ['Authorization' => "Bearer $token"],
                                    'json' => ['destination' => $feedData->mobile],
                                    'timeout' => 2
                                ]);

                                $responseData = json_decode($responseState->getBody(), true);

                                // ✅ Update records in a transaction
                                DB::transaction(function () use ($responseData, $feedData) {
                                    AutoDistributerReport::create([
                                        'call_id' => $responseData['result']['callid'],
                                        'status' => "Initiating",
                                        'provider' => $responseData['result']['dn'],
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

                                Log::info("📞 Call initiated for {$feedData->mobile} by Agent {$agent->id} ({$agent->extension})");
                                break 3; // ✅ Exit all loops after a successful call
                            }
                        } catch (RequestException $e) {
                            Log::error("❌ Call failed for {$feedData->mobile}: " . $e->getMessage());
                        }
                    }

                    // ✅ Mark feeds as done if no more new calls
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
            Log::error("❌ General error: " . $e->getMessage());
        }

    }
}
