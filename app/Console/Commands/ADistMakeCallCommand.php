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

            // Get active calls first
            try {
                $response = $client->get('/xapi/v1/ActiveCalls', [
                    'headers' => ['Authorization' => "Bearer $token"]
                ]);

                $activeCalls = json_decode($response->getBody(), true);
            } catch (RequestException $e) {
                Log::error("ADistMakeCallCommand ❌ Failed to fetch active calls: " . $e->getMessage());
                return;
            }

            $activeCallsList = $activeCalls['value'] ?? [];
            Log::info("ADistMakeCallCommand Active Calls Retrieved: " . print_r($activeCallsList, true));

            $agents = ADistAgent::all();

            foreach ($agents as $agent) {
                // Check if the agent already has an active call
                $isAgentBusy = false;
                foreach ($activeCallsList as $call) {
                    if ($call['Agent'] === (string) $agent->extension) {
                        $isAgentBusy = true;
                        Log::info("ADistMakeCallCommand 🚫 Agent {$agent->id} ({$agent->extension}) is already in a call.");
                        break;
                    }
                }

                if ($isAgentBusy) {
                    continue;
                }

                $feeds = ADistFeed::where('agent_id', $agent->id)
                    ->whereDate('date', today())
                    ->where('allow', true)
                    ->get();

                foreach ($feeds as $feed) {
                    $from = Carbon::parse("{$feed->date} {$feed->from}")->subHours(3);
                    $to = Carbon::parse("{$feed->date} {$feed->to}")->subHours(3);

                    if (!now()->between($from, $to)) {
                        Log::info("ADistMakeCallCommand ⏰ File ID {$feed->id} is not within time range.");
                        continue;
                    }

                    Log::info("ADistMakeCallCommand ✅ File ID {$feed->id} is within time range");

                    // Fetch only one new call
                    $feedData = ADistData::where('feed_id', $feed->id)
                        ->where('state', 'new')
                        ->first();

                    $feedData->update(['state' => 'Initiating']);

                    try {
                        if ($agent->status !== "Available") {
                            Log::error("ADistMakeCallCommand 📵 Agent {$agent->id} not available, skipping call to {$feedData->mobile}");
                            continue;
                        }

                        // Fetch devices for agent
                        try {
                            $devicesResponse = $client->get("/callcontrol/{$agent->extension}/devices", [
                                'headers' => ['Authorization' => "Bearer $token"]
                            ]);

                            $dnDevices = json_decode($devicesResponse->getBody(), true);
                        } catch (RequestException $e) {
                            Log::error("ADistMakeCallCommand ❌ Error fetching devices for extension {$agent->extension}: " . $e->getMessage());
                            continue;
                        }

                        foreach ($dnDevices as $device) {
                            if ($device['user_agent'] !== '3CX Mobile Client') continue;

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

                                break; // ✅ Stop after making one call
                            } catch (RequestException $e) {
                                Log::error("ADistMakeCallCommand ❌ Failed to make call to {$feedData->mobile}: " . $e->getMessage());
                            }
                        }
                    } catch (\Exception $e) {
                        Log::error("ADistMakeCallCommand ❌ General error making call: " . $e->getMessage());
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error("ADistMakeCallCommand ❌ General error in fetching active calls: " . $e->getMessage());
        }
    }
}
