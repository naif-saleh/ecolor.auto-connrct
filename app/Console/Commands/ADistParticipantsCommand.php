<?php

namespace App\Console\Commands;

use App\Models\ADistAgent;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Models\ADistData;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use App\Models\AutoDistributerReport;
use DateTime;
use App\Services\TokenService;


class ADistParticipantsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:ADist-participants-command';

    protected $tokenService;
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch and process participants data from 3CX API';
    public function __construct(TokenService $tokenService)
    {
        parent::__construct(); // This is required
        $this->tokenService = $tokenService;
    }


    /**
     * Execute the console command.
     */
    public function handle()
    {
        Log::info("
        \t-----------------------------------------------------------------------
        \t\t\t********** Auto Dialer **********\n
        \t-----------------------------------------------------------------------
        \t| 📞 ✅ ParticipantCommand executed at " . now() . "            |
        \t-----------------------------------------------------------------------
    ");
        // Fetch all providers
        $agents = ADistAgent::all();


        try {
            $token = $this->tokenService->getToken();

            // Fetch all active calls first
            $activeCallsResponse = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token,
            ])->get(config('services.three_cx.api_url') . "/xapi/v1/ActiveCalls");

            if (!$activeCallsResponse->successful()) {
                Log::error("❌ Failed to fetch active calls. Response: " . $activeCallsResponse->body());
                return;
            }

            $activeCalls = $activeCallsResponse->json();

            if (empty($activeCalls['value'])) {
                Log::info("ℹ️ No active calls at the moment.");
                return;
            }

            Log::info("✅ Active Calls Retrieved: " . print_r($activeCalls, true));

            // Extract active extensions
            $activeExtensions = [];
            foreach ($activeCalls['value'] as $call) {
                if (!empty($call['Caller'])) {
                    $activeExtensions[] = $call['Caller']; // Store active extensions
                }
            }

            // Process only active agents
            foreach ($agents as $agent) {
                $ext_from = $agent->extension;

                if (!in_array($ext_from, $activeExtensions)) {
                    continue; // Skip agents not in a call
                }

                try {
                    $responseState = Http::withHeaders([
                        'Authorization' => 'Bearer ' . $token,
                    ])->get(config('services.three_cx.api_url') . "/callcontrol/{$ext_from}/participants");

                    if (!$responseState->successful()) {
                        Log::error("❌ Failed to fetch participants for extension {$ext_from}. HTTP Status: {$responseState->status()}.");
                        continue;
                    }

                    $participants = $responseState->json();

                    if (empty($participants)) {
                        Log::warning("⚠️ No participants found for active call on extension {$ext_from}");
                        continue;
                    }

                    Log::info("✅ Auto Dialer Participants for {$ext_from}: " . print_r($participants, true));

                    foreach ($participants as $participant_data) {
                        try {
                            $filter = "contains(Caller, '{$participant_data['dn']}')";
                            $url = config('services.three_cx.api_url') . "/xapi/v1/ActiveCalls?\$filter=" . urlencode($filter);

                            $activeCallsResponse = Http::withHeaders([
                                'Authorization' => 'Bearer ' . $token,
                            ])->get($url);

                            if ($activeCallsResponse->successful()) {
                                $activeCalls = $activeCallsResponse->json();
                                Log::info("✅ Active Calls Response: " . print_r($activeCalls, true));

                                foreach ($activeCalls['value'] as $call) {
                                    if ($call['Status'] === 'Talking') {
                                        $establishedAt = new DateTime($call['EstablishedAt']);
                                        $serverNow = new DateTime($call['ServerNow']);
                                        $interval = $establishedAt->diff($serverNow);
                                        $durationTime = $interval->format('%H:%I:%S');

                                        AutoDistributerReport::where('call_id', $call['Id'])->update([
                                            'status' => $call['Status'],
                                            'duration_time' => $durationTime
                                        ]);

                                        $aADistData = ADistData::where('call_id', $call['Id'])->first();
                                        $status = AutoDistributerReport::where('call_id', $call['Id'])->value('status');

                                        if ($aADistData && $status) {
                                            $aADistData->state = $status;
                                            $aADistData->save();
                                        }
                                    } else {
                                        AutoDistributerReport::where('call_id', $call['Id'])->update([
                                            'status' => $call['Status']
                                        ]);
                                    }
                                }
                            } else {
                                Log::error("❌ Failed to fetch active calls. Response: " . $activeCallsResponse->body());
                            }
                        } catch (\Exception $e) {
                            Log::error("❌ Failed to process participant data for call ID " . ($participant_data['callid'] ?? 'N/A') . ": " . $e->getMessage());
                        }
                    }
                } catch (\Exception $e) {
                    Log::error("❌ Failed fetching participants for provider {$ext_from}: " . $e->getMessage());
                }
            }
        } catch (\Exception $e) {
            Log::error("❌ General error in fetching active calls: " . $e->getMessage());
        }




        Log::info("✅ Auto Dialer command execution completed.");
    }
}
