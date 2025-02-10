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
use Illuminate\Support\Facades\DB;
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

        Log::info('ADialParticipantsCommand executed at ' . Carbon::now());

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
                            Log::info("✅ Dial:Active Calls Response: " . print_r($activeCalls, True));

                            foreach ($activeCalls['value'] as $call) {

                                $status = $call['Status'];
                                $callId = $call['Id'];

                                $durationTime = null;
                                $durationRouting = null;

                                if ($status === 'Talking') {
                                    $establishedAt = new DateTime($call['EstablishedAt']);
                                    $serverNow = new DateTime($call['ServerNow']);
                                    $durationTime = $establishedAt->diff($serverNow)->format('%H:%I:%S');
                                    // Log::info("✅ Duration Time: ".$durationTime);
                                }

                                if ($status === 'Routing') {
                                    $establishedAt = new DateTime($call['EstablishedAt']);
                                    $serverNow = new DateTime($call['ServerNow']);
                                    $durationRouting = $establishedAt->diff($serverNow)->format('%H:%I:%S');
                                    // Log::info("✅ Duration Routing: ".$durationRouting);
                                }

                                DB::beginTransaction();
                                try {
                                    AutoDailerReport::where('call_id', $callId)
                                        ->update(['status' => $status, 'duration_time' => $durationTime, 'duration_routing' => $durationRouting]);
                                    ADialData::where('call_id', $callId)
                                        ->update(['state' => $status]);
                                    // Log::info("✅ mobile status:: " . $status);
                                    DB::commit();
                                } catch (\Exception $e) {
                                    DB::rollBack();
                                    Log::error("❌ Transaction Failed for call ID {$callId}: " . $e->getMessage());
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
