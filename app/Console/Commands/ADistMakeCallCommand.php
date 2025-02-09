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
        Log::info("\n\t********** Auto Distributor Call Execution **********\n");

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
                    Log::info("✅ File ID {$feed->id} is within time range");

                    ADistData::where('feed_id', $feed->id)->where('state', 'new')
                        ->chunk(50, function ($dataChunk) use ($agent) {
                            foreach ($dataChunk as $feedData) {
                                try {
                                    if ($agent->status !== "Available") {
                                        Log::error("📵 Agent {$agent->id} not available, skipping call to {$feedData->mobile}");
                                        continue;
                                    }

                                    $token = $this->tokenService->getToken();
                                    $ext = $agent->extension;
                                    $filter = "contains(Caller, '{$ext}')";
                                    $url = config('services.three_cx.api_url') . "/xapi/v1/ActiveCalls?\$filter=" . urlencode($filter);

                                    $activeCallsResponse = Http::withHeaders(['Authorization' => "Bearer $token"])->get($url);
                                    if ($activeCallsResponse->failed()) {
                                        Log::error("❌ Failed to fetch active calls for {$feedData->mobile}" , print_r([
                                            'response' => $activeCallsResponse->json(),
                                            'status' => $activeCallsResponse->status(),
                                            'headers' => $activeCallsResponse->headers(),
                                        ], true));
                                        continue;
                                    }

                                    $activeCalls = $activeCallsResponse->json();
                                    if (!empty($activeCalls['value'])) {
                                        Log::info("🚫 Extension {$ext} is busy, skipping call to {$feedData->mobile}" , [
                                            'response' => $activeCalls->json(),
                                            'status' => $activeCalls->status(),
                                            'headers' => $activeCalls->headers(),
                                        ]);
                                        continue;
                                    }

                                    $dnDevices = Http::withHeaders(['Authorization' => "Bearer $token"])
                                        ->get(config('services.three_cx.api_url') . "/callcontrol/{$ext}/devices");

                                    if ($dnDevices->failed()) {
                                        Log::error("❌ Error fetching devices for extension {$ext}", [
                                            'response' => $activeCalls->json(),
                                            'status' => $activeCalls->status(),
                                            'headers' => $activeCalls->headers(),
                                        ]);
                                        continue;
                                    }


                                    foreach ($dnDevices->json() as $device) {
                                        if ($device['user_agent'] !== '3CX Mobile Client') continue;

                                        $responseState = Http::withHeaders(['Authorization' => "Bearer $token"])
                                            ->post(config('services.three_cx.api_url') . "/callcontrol/{$ext}/devices/{$device['device_id']}/makecall", [
                                                'destination' => $feedData->mobile,
                                            ]);

                                        if ($responseState->failed()) {
                                            Log::error("❌ Failed to make call to {$feedData->mobile}");
                                            continue;
                                        }

                                        $responseData = $responseState->json();
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

                                        Log::info("📞 Call initiated successfully for {$feedData->mobile}");
                                        break;
                                    }
                                } catch (\Exception $e) {
                                    Log::error("❌ Error making call: " . $e->getMessage());
                                }
                            }
                        });
                } else {
                    Log::info("⏰ File ID {$feed->id} is not within time range");
                }
            }
        }
    }
}
