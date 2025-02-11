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

        $client = new Client([
            'base_uri' => config('services.three_cx.api_url'),
            'headers' => [
                'Accept' => 'application/json'
            ],
        ]);

        $agents = ADistAgent::all();

        foreach ($agents as $agent) {
            $feeds = ADistFeed::where('agent_id', $agent->id)
                ->whereDate('date', today())
                ->where('allow', true)
                ->get();

            foreach ($feeds as $feed) {
                $from = Carbon::parse("{$feed->date} {$feed->from}")->subHours(3);
                $to = Carbon::parse("{$feed->date} {$feed->to}")->subHours(3);

                if (now()->between($from, $to)) {
                    Log::info("ADistMakeCallCommand ✅ File ID {$feed->id} is within time range");

                    $feedDataList = ADistData::where('feed_id', $feed->id)->where('state', 'new')->get();

                    foreach ($feedDataList as $feedData) {
                        try {
                            if ($agent->status !== "Available") {
                                Log::error("ADistMakeCallCommand 📵 Agent {$agent->id} not available, skipping call to {$feedData->mobile}");
                                continue;
                            }

                            $token = $this->tokenService->getToken();
                            $ext = $agent->extension;
                            $filter = "contains(Caller, '{$ext}')";
                            $url = "/xapi/v1/ActiveCalls?\$filter=" . urlencode($filter);

                            try {
                                $response = $client->get($url, [
                                    'headers' => ['Authorization' => "Bearer $token"]
                                ]);

                                $activeCalls = json_decode($response->getBody(), true);
                                Log::info('ADist Active Call ' . print_r($activeCalls, true));

                                if (!empty($activeCalls['value'])) {
                                    Log::info("ADistMakeCallCommand 🚫 Extension {$ext} is busy, skipping call to {$feedData->mobile}");
                                    continue;
                                }
                            } catch (RequestException $e) {
                                Log::error("ADistMakeCallCommand ❌ Failed to fetch active calls: " . $e->getMessage());
                                continue;
                            }

                            // Fetch devices
                            try {
                                $devicesResponse = $client->get("/callcontrol/{$ext}/devices", [
                                    'headers' => ['Authorization' => "Bearer $token"]
                                ]);

                                $dnDevices = json_decode($devicesResponse->getBody(), true);
                            } catch (RequestException $e) {
                                Log::error("ADistMakeCallCommand ❌ Error fetching devices for extension {$ext}: " . $e->getMessage());
                                continue;
                            }

                            foreach ($dnDevices as $device) {
                                if ($device['user_agent'] !== '3CX Mobile Client') continue;

                                try {
                                    $callResponse = $client->post("/callcontrol/{$ext}/devices/{$device['device_id']}/makecall", [
                                        'headers' => ['Authorization' => "Bearer $token"],
                                        'json' => [
                                            'destination' => $feedData->mobile,
                                        ],
                                    ]);

                                    $responseData = json_decode($callResponse->getBody(), true);

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
                                    break;
                                } catch (RequestException $e) {
                                    Log::error("ADistMakeCallCommand ❌ Failed to make call to {$feedData->mobile}: " . $e->getMessage());
                                }
                            }
                        } catch (\Exception $e) {
                            Log::error("ADistMakeCallCommand ❌ Error making call: " . $e->getMessage());
                        }
                    }
                } else {
                    Log::info("ADistMakeCallCommand ⏰ File ID {$feed->id} is not within time range");
                }
            }
        }
    }
}
