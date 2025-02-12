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

            // Get active calls
            $activeResponse = $client->get('/xapi/v1/ActiveCalls', [
                'headers' => ['Authorization' => "Bearer $token"],
                'timeout' => 3
            ]);

            $activeCalls = json_decode($activeResponse->getBody(), true);
            $activeCallsList = $activeCalls['value'] ?? [];

            Log::info("ADistMakeCallCommand Active Calls Retrieved: " . print_r($activeCallsList, true));

            // Track busy agents
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

            // Ensure completed calls are cleared from the busy list
            $endedCalls = AutoDistributerReport::whereIn('status', ['Ended', 'Completed', 'Failed', 'NoAnswer'])->get();
            foreach ($endedCalls as $call) {
                unset($busyExtensions[$call->extension]);
            }

            // Get available agents
            $agents = ADistAgent::all();

            foreach ($agents as $agent) {
                Log::info("ADistMakeCallCommand Processing Agent {$agent->id} ({$agent->extension})");

                // Ensure agent is not on an active call
                $activeSystemCall = AutoDistributerReport::where('extension', $agent->extension)
                    ->whereIn('status', ['Initiating', 'InProgress', 'Ringing'])
                    ->whereNotIn('status', ['Ended', 'Completed', 'Failed', 'NoAnswer'])
                    ->exists();

                if ($activeSystemCall) {
                    Log::info("ADistMakeCallCommand ⚠️ Agent {$agent->id} ({$agent->extension}) is currently on a call");
                    continue;
                }

                if (isset($busyExtensions[$agent->extension])) {
                    Log::info("ADistMakeCallCommand ⚠️ Agent {$agent->id} ({$agent->extension}) is busy: {$busyReasons[$agent->extension]}");
                    continue;
                }

                if ($agent->status !== "Available") {
                    Log::info("ADistMakeCallCommand Agent {$agent->id} ({$agent->extension}) not available - Status: {$agent->status}");
                    continue;
                }

                $lockKey = "agent_call_lock_{$agent->id}";
                if (!Cache::add($lockKey, true, now()->addSeconds(10))) {
                    Log::info("ADistMakeCallCommand Agent {$agent->id} ({$agent->extension}) is locked by another process");
                    continue;
                }

                try {
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

                        $feedData = ADistData::where('feed_id', $feed->id)
                            ->where('state', 'new')
                            ->lockForUpdate()
                            ->first();

                        if (!$feedData) {
                            continue;
                        }

                        $finalCheck = AutoDistributerReport::where('extension', $agent->extension)
                            ->whereIn('status', ['Initiating', 'InProgress', 'Ringing', 'Answered'])
                            ->whereNotIn('status', ['Ended', 'Completed', 'Failed', 'NoAnswer'])
                            ->exists();

                        if ($finalCheck) {
                            Log::info("ADistMakeCallCommand ⚠️ Agent {$agent->id} ({$agent->extension}) became busy before call initiation");
                            continue;
                        }

                        $devicesResponse = $client->get("/callcontrol/{$agent->extension}/devices", [
                            'headers' => ['Authorization' => "Bearer $token"],
                            'timeout' => 2
                        ]);

                        $dnDevices = json_decode($devicesResponse->getBody(), true);

                        foreach ($dnDevices as $device) {
                            if ($device['user_agent'] !== '3CX Mobile Client') continue;

                            $responseState = $client->post("/callcontrol/{$agent->extension}/devices/{$device['device_id']}/makecall", [
                                'headers' => ['Authorization' => "Bearer $token"],
                                'json' => ['destination' => $feedData->mobile],
                                'timeout' => 2
                            ]);

                            $responseData = json_decode($responseState->getBody(), true);

                            DB::transaction(function () use ($responseData, $feedData, $agent) {
                                AutoDistributerReport::create([
                                    'call_id' => $responseData['result']['callid'],
                                    'status' => "Answered",
                                    'provider' => $feedData->file->file_name,
                                    'extension' => $responseData['result']['dn'],
                                    'phone_number' => $responseData['result']['party_caller_id'],
                                    'attempt_time' => now(),
                                ]);

                                $feedData->update([
                                    'state' => "Answered",
                                    'call_date' => now(),
                                    'call_id' => $responseData['result']['callid'],
                                ]);
                            });

                            Log::info("ADistMakeCallCommand 📞 Call initiated for {$feedData->mobile} by Agent {$agent->id} ({$agent->extension})");
                            break 3;
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
