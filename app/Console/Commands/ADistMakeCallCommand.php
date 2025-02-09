<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
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
        Log::info("\n\t********** Auto Distributor Call Execution Started **********\n");

        $agents = ADistAgent::all();
        foreach ($agents as $agent) {
            $feeds = ADistFeed::where('agent_id', $agent->id)
                ->whereDate('date', today())
                ->where('allow', true)
                ->get();

            foreach ($feeds as $feed) {
                $from = Carbon::parse("{$feed->date} {$feed->from}")->subHours(3);
                $to = Carbon::parse("{$feed->date} {$feed->to}")->subHours(3);

                if (!now()->between($from, $to)) {
                    Log::info("⏰ File ID {$feed->id} is not within time range, skipping.");
                    continue;
                }

                Log::info("✅ File ID {$feed->id} is within time range.");

                ADistData::where('feed_id', $feed->id)->where('state', 'new')
                    ->chunk(50, function ($dataChunk) use ($agent) {
                        foreach ($dataChunk as $feedData) {
                            try {
                                if ($agent->status !== "Available") {
                                    Log::warning("📵 Agent {$agent->id} not available, skipping call to {$feedData->mobile}");
                                    continue;
                                }

                                // 🔑 Get authentication token
                                $token = $this->tokenService->getToken();
                                Log::info("🔑 Token Retrieved: " . substr($token, 0, 20) . "... (trimmed)");

                                $ext = $agent->extension;
                                $filter = "contains(Caller, '{$ext}')";
                                $url = config('services.three_cx.api_url') . "/xapi/v1/ActiveCalls?\$filter=" . urlencode($filter);

                                // 🌐 Log API URL before request
                                Log::info("🌐 Checking Active Calls for Extension: {$ext}");
                                Log::info("🔗 API URL: " . $url);

                                $activeCallsResponse = Http::withHeaders(['Authorization' => "Bearer $token"])->get($url);

                                if ($activeCallsResponse->failed()) {
                                    Log::error("❌ API Request Failed for Active Calls!");
                                    Log::error("🔴 Status Code: " . $activeCallsResponse->status());
                                    Log::error("🔴 Response Headers: " . print_r($activeCallsResponse->headers(), true));
                                    Log::error("🔴 Response Body: " . $activeCallsResponse->body());
                                    continue;
                                }

                                $activeCalls = $activeCallsResponse->json();
                                if (!empty($activeCalls['value'])) {
                                    Log::warning("🚫 Extension {$ext} is already on a call, skipping call to {$feedData->mobile}");
                                    continue;
                                }

                                // 📡 Fetch DN Devices
                                Log::info("📡 Fetching DN Devices for Extension {$ext}");
                                $dnDevicesResponse = Http::withHeaders(['Authorization' => "Bearer $token"])
                                    ->get(config('services.three_cx.api_url') . "/callcontrol/{$ext}/devices");

                                if ($dnDevicesResponse->failed()) {
                                    Log::error("❌ Failed to fetch DN devices for extension {$ext}");
                                    Log::error("🔴 Status Code: " . $dnDevicesResponse->status());
                                    Log::error("🔴 Response Headers: " . print_r($dnDevicesResponse->headers(), true));
                                    Log::error("🔴 Response Body: " . $dnDevicesResponse->body());
                                    continue;
                                }

                                $dnDevices = $dnDevicesResponse->json();
                                Log::info("✅ DN Devices Retrieved: " . print_r($dnDevices, true));

                                foreach ($dnDevices as $device) {
                                    if ($device['user_agent'] !== '3CX Mobile Client') continue;

                                    // 📞 Initiate Call
                                    Log::info("📞 Initiating call from {$ext} to {$feedData->mobile}...");
                                    $callResponse = Http::withHeaders(['Authorization' => "Bearer $token"])
                                        ->post(config('services.three_cx.api_url') . "/callcontrol/{$ext}/devices/{$device['device_id']}/makecall", [
                                            'destination' => $feedData->mobile,
                                        ]);

                                    if ($callResponse->failed()) {
                                        Log::error("❌ Call initiation failed for {$feedData->mobile}");
                                        Log::error("🔴 Status Code: " . $callResponse->status());
                                        Log::error("🔴 Response Headers: " . print_r($callResponse->headers(), true));
                                        Log::error("🔴 Response Body: " . $callResponse->body());
                                        continue;
                                    }

                                    $responseData = $callResponse->json();
                                    Log::info("✅ Call Initiated Successfully: " . print_r($responseData, true));

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

                                    Log::info("📞 Call to {$feedData->mobile} is in 'Initiating' state.");
                                    break;
                                }
                            } catch (\Exception $e) {
                                Log::error("❌ Exception during call process: " . $e->getMessage());
                            }
                        }
                    });
            }
        }

        Log::info("\n\t********** Auto Distributor Call Execution Completed **********\n");
    }
}
