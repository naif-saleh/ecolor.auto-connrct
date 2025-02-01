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


        foreach ($agents as $agent) {
            $ext_from = $agent->extension;

            try {
                $token = $this->tokenService->getToken();

                $responseState = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $token,
                ])->get(config('services.three_cx.api_url') . "/callcontrol/{$ext_from}/participants");

                if (!$responseState->successful()) {
                    Log::error("Failed to fetch participants for extension {$ext_from}. HTTP Status: {$responseState->status()}. Response: {$responseState->body()}");
                    continue;
                }

                $participants = $responseState->json();

                if (empty($participants)) {
                    Log::warning("⚠️ No participants found for extension {$ext_from}");
                    continue;
                }

                Log::info("✅ Auto Dialer Participants Response: " . print_r($participants, true));

                foreach ($participants as $participant_data) {
                    try {
                        Log::info("✅ Auto Dialer Participants Response: " . print_r($participants, true));
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
                                } else {
                                    AutoDistributerReport::where('call_id', $call['Id'])->update([
                                        'status' => $call['Status']
                                    ]);
                                    ADistData::where('call_id', $call['Id'])->update(['state' => $call['Status']]);
                                    Log::info("✅ Updated status for Call ID {$call['Id']} to: {$call['Status']}");
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


        Log::info("✅ Auto Dialer command execution completed.");
    }
}
