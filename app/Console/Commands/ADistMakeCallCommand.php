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

            // Get ALL possible call states
            try {
                $activeResponse = $client->get('/xapi/v1/ActiveCalls', [
                    'headers' => ['Authorization' => "Bearer $token"],
                    'timeout' => 3
                ]);

                $activeCalls = json_decode($activeResponse->getBody(), true);

                // Check ANY recent calls in our system (including completed ones)
                $recentCalls = AutoDistributerReport::where('created_at', '>=', now()->subSeconds(30))
                    ->whereIn('status', ['Initiating', 'Routing', 'Talking'])
                    ->get();

            } catch (RequestException $e) {
                Log::error("ADistMakeCallCommand ❌ Failed to fetch call status: " . $e->getMessage());
                return;
            }

            $activeCallsList = $activeCalls['value'] ?? [];

            // Track ALL busy extensions more comprehensively
            $busyExtensions = [];

            // Track from active 3CX calls
            foreach ($activeCallsList as $call) {
                $busyExtensions[$call['Caller']] = true;
                // Also track called numbers
                $busyExtensions[$call['Callee']] = true;
            }

            // Track from our recent system calls
            foreach ($recentCalls as $call) {
                $busyExtensions[$call->extension] = true;
                $busyExtensions[$call->phone_number] = true;
            }

            // Get only truly available agents
            $agents = ADistAgent::where('status', 'Available')
                ->whereNotExists(function ($query) {
                    $query->from('auto_distributer_reports')
                        ->whereRaw('auto_distributer_reports.extension = a_dist_agents.extension')
                        ->where('created_at', '>=', now()->subSeconds(30))
                        ->whereIn('status', ['Initiating', 'Routing', 'Talking']);
                })
                ->get();

            foreach ($agents as $agent) {
                // Multiple busy checks
                if (isset($busyExtensions[$agent->extension])) {
                    Log::info("ADistMakeCallCommand 🚫 Agent {$agent->extension} is busy");
                    continue;
                }

                // Distributed lock with very short timeout
                $lockKey = "agent_call_lock_{$agent->extension}";
                if (!Cache::add($lockKey, true, now()->addSeconds(5))) {
                    Log::info("ADistMakeCallCommand 🔒 Agent {$agent->extension} is locked");
                    continue;
                }

                try {
                    // Double-check agent status before proceeding
                    $currentAgent = ADistAgent::where('id', $agent->id)
                        ->where('status', 'Available')
                        ->lockForUpdate()
                        ->first();

                    if (!$currentAgent) {
                        Log::info("ADistMakeCallCommand 📵 Agent {$agent->extension} no longer available");
                        continue;
                    }

                    // Find valid feeds
                    $feeds = ADistFeed::where('agent_id', $agent->id)
                        ->whereDate('date', today())
                        ->where('allow', true)
                        ->where('is_done', false)
                        ->lockForUpdate()
                        ->get();

                    foreach ($feeds as $feed) {
                        $from = Carbon::parse("{$feed->date} {$feed->from}")->subHours(3);
                        $to = Carbon::parse("{$feed->date} {$feed->to}")->subHours(3);

                        if (!now()->between($from, $to)) {
                            continue;
                        }

                        // Get one new call with strict locking
                        $feedData = ADistData::where('feed_id', $feed->id)
                            ->where('state', 'new')
                            ->lockForUpdate()
                            ->first();

                        if (!$feedData) {
                            continue;
                        }

                        // Final verification before call
                        $isExtensionBusy = AutoDistributerReport::where('extension', $agent->extension)
                            ->where('created_at', '>=', now()->subSeconds(30))
                            ->whereIn('status', ['Initiating', 'Routing', 'Talking'])
                            ->exists();

                        if ($isExtensionBusy) {
                            Log::info("ADistMakeCallCommand ⚠️ Last-minute check showed agent {$agent->extension} is busy");
                            continue;
                        }

                        try {
                            $devicesResponse = $client->get("/callcontrol/{$agent->extension}/devices", [
                                'headers' => ['Authorization' => "Bearer $token"],
                                'timeout' => 2
                            ]);

                            $dnDevices = json_decode($devicesResponse->getBody(), true);

                            foreach ($dnDevices as $device) {
                                if ($device['user_agent'] !== '3CX Mobile Client') continue;

                                // Execute call in transaction
                                DB::transaction(function() use ($client, $token, $agent, $device, $feedData) {
                                    // Final status check within transaction
                                    if (AutoDistributerReport::where('extension', $agent->extension)
                                        ->where('created_at', '>=', now()->subSeconds(30))
                                        ->whereIn('status', ['Initiating', 'Routing', 'Talking'])
                                        ->exists()) {
                                        throw new \Exception("Agent became busy during transaction");
                                    }

                                    $responseState = $client->post("/callcontrol/{$agent->extension}/devices/{$device['device_id']}/makecall", [
                                        'headers' => ['Authorization' => "Bearer $token"],
                                        'json' => ['destination' => $feedData->mobile],
                                        'timeout' => 2
                                    ]);

                                    $responseData = json_decode($responseState->getBody(), true);

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

                                Log::info("ADistMakeCallCommand 📞 Call successfully initiated for {$feedData->mobile}");
                                return; // Exit completely after successful call
                            }
                        } catch (\Exception $e) {
                            Log::error("ADistMakeCallCommand ❌ Error making call: " . $e->getMessage());
                            continue;
                        }
                    }
                } finally {
                    Cache::forget($lockKey);
                }
            }
        } catch (\Exception $e) {
            Log::error("ADistMakeCallCommand ❌ General error: " . $e->getMessage());
        }
    }
}
