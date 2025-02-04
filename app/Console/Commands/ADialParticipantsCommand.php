<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Models\ADialProvider;
use App\Models\AutoDailerReport;
use App\Models\ADialData;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use App\Models\AutoDailerUploadedData;
use DateTime;
use App\Services\TokenService;


class ADialParticipantsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:ADial-participants-command';

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
        Log::info("ADIAL
        \t-----------------------------------------------------------------------
        \t\t\t********** Auto Dialer **********\n
        \t-----------------------------------------------------------------------
        \t| 📞 ✅ ParticipantCommand executed at " . now() . "            |
        \t-----------------------------------------------------------------------
    ");
        // Fetch all providers
        $providers = ADialProvider::all();


        foreach ($providers as $provider) {
            $ext_from = $provider->extension;

            try {
                $token = $this->tokenService->getToken();

                $responseState = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $token,
                ])->get(config('services.three_cx.api_url') . "/callcontrol/{$ext_from}/participants");

                if (!$responseState->successful()) {
                    Log::error("ADIAL Failed to fetch participants for extension {$ext_from}. HTTP Status: {$responseState->status()}. Response: {$responseState->body()}");
                    continue;
                }

                $participants = $responseState->json();

                if (empty($participants)) {
                    Log::warning("ADIAL ⚠️ No participants found for extension {$ext_from}");
                    continue;
                }

                Log::info("ADIAL ✅ Auto Dialer Participants Response: " . print_r($participants, true));

                foreach ($participants as $participant_data) {
                    try {
                        Log::info("ADIAL ✅ Auto Dialer Participants Response: " . print_r($participants, true));
                        $filter = "contains(Caller, '{$participant_data['dn']}')";
                        $url = config('services.three_cx.api_url') . "/xapi/v1/ActiveCalls?\$filter=" . urlencode($filter);

                        $activeCallsResponse = Http::withHeaders([
                            'Authorization' => 'Bearer ' . $token,
                        ])->get($url);

                        if ($activeCallsResponse->successful()) {
                            $activeCalls = $activeCallsResponse->json();
                            Log::info("ADIAL ✅ Active Calls Response: " . print_r($activeCalls, true));


                            foreach ($activeCalls['value'] as $call) {
                                if ($call['Status'] === 'Talking') {
                                    $establishedAt = new DateTime($call['EstablishedAt']);
                                    $serverNow = new DateTime($call['ServerNow']);
                                    $interval = $establishedAt->diff($serverNow);
                                    $durationTime = $interval->format('%H:%I:%S');

                                    AutoDailerReport::where('call_id', $call['Id'])->update([
                                        'status' => $call['Status'],
                                        'duration_time' => $durationTime
                                    ]);
                                    $aDialData = ADialData::where('call_id', $call['Id'])->first();
                                    $status = AutoDailerReport::where('call_id', $call['Id'])->value('status');

                                    if ($aDialData && $status) {
                                        $aDialData->state = $status;
                                        $aDialData->save();
                                    }

                                } else {
                                    AutoDailerReport::where('call_id', $call['Id'])->update([
                                        'status' => $call['Status']
                                    ]);
                      }
                            }
                        } else {
                            Log::error("ADIAL ❌ Failed to fetch active calls. Response: " . $activeCallsResponse->body());
                        }
                    } catch (\Exception $e) {
                        Log::error("ADIAL ❌ Failed to process participant data for call ID " . ($participant_data['callid'] ?? 'N/A') . ": " . $e->getMessage());
                    }
                }
            } catch (\Exception $e) {
                Log::error("ADIAL ❌ Failed fetching participants for provider {$ext_from}: " . $e->getMessage());
            }
        }


        Log::info("ADIAL ✅ Auto Dialer command execution completed.");
    }
}
