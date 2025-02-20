<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Models\ADialProvider;
use App\Models\AutoDailerReport;
use App\Models\ADialData;
use GuzzleHttp\Client;
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
        // Log::info("Total providers found: " . $providers->count());
        // Log::info("Providers query SQL: " . ADialProvider::toSql());

        // // You can also dump the full provider data
        // Log::info("All providers data: " . print_r($providers->toArray(), true));

        foreach ($providers as $provider) {
            $ext_from = $provider->extension;
            Log::warning("****Providers: {$provider->extension}");
            // ADial Partisipant
            try {
                $client = new Client();
                $token = $this->tokenService->getToken();

                $responseState = $client->get(config('services.three_cx.api_url') . "/callcontrol/{$ext_from}/participants", [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $token,
                        'Accept' => 'application/json',
                    ],
                    'timeout' => 20,
                ]);

                if ($responseState->getStatusCode() !== 200) {
                    Log::error("❌ ADialParticipantsCommand Failed to fetch participants even after token refresh. HTTP Status: {$responseState->getStatusCode()}");
                    continue;
                }

                $participants = json_decode($responseState->getBody()->getContents(), true);

                if (empty($participants)) {
                    Log::warning("⚠️ ADialParticipantsCommand No participants found for extension {$provider->extension}");

                    continue;
                }

                Log::info("✅ ADialParticipantsCommand Auto Dialer Participants Response: " . print_r($participants, true));

                foreach ($participants as $participant_data) {
                    try {
                        Log::info("✅ ADialParticipantsCommand Processing participant: " . json_encode($participant_data));

                        $filter = "contains(Caller, '{$ext_from}')";
                        $url = config('services.three_cx.api_url') . "/xapi/v1/ActiveCalls?\$filter=" . urlencode($filter);

                        $activeCallsResponse = $client->get($url, [
                            'headers' => [
                                'Authorization' => 'Bearer ' . $token,
                                'Accept' => 'application/json',
                            ],
                            'timeout' => 10,
                        ]);

                        if ($activeCallsResponse->getStatusCode() === 200) {
                            $activeCalls = json_decode($activeCallsResponse->getBody()->getContents(), true);
                            Log::info("✅ Active Calls Response: " . print_r($activeCalls, true));

                            foreach ($activeCalls['value'] as $call) {
                                $status = $call['Status'];
                                $callId = $call['Id'];

                                // Get existing record to preserve previous durations
                                $existingRecord = AutoDailerReport::where('call_id', $callId)->first();
                                $durationTime = $existingRecord ? $existingRecord->duration_time : null;
                                $durationRouting = $existingRecord ? $existingRecord->duration_routing : null;

                                // Calculate current duration
                                if (isset($call['EstablishedAt']) && isset($call['ServerNow'])) {
                                    $establishedAt = new DateTime($call['EstablishedAt']);
                                    $serverNow = new DateTime($call['ServerNow']);
                                    $currentDuration = $establishedAt->diff($serverNow)->format('%H:%I:%S');

                                    // Update appropriate duration based on status
                                    if ($status === 'Talking') {
                                        $durationTime = $currentDuration;
                                    } elseif ($status === 'Routing') {
                                        $durationRouting = $currentDuration;
                                    }
                                }

                                // Database Transaction
                                DB::beginTransaction();
                                try {
                                    AutoDailerReport::where('call_id', $callId)
                                        ->update([
                                            'status' => $status,
                                            'duration_time' => $durationTime,
                                            'duration_routing' => $durationRouting,
                                            
                                        ]);

                                    ADialData::where('call_id', $callId)
                                        ->update(['state' => $status]);

                                    Log::info("✅ ADialParticipantsCommand Call Updated: Status: {$status}, Mobile: " . $call['Callee'] .
                                        ", Routing Duration: {$durationRouting}, Talking Duration: {$durationTime}");

                                    DB::commit();
                                } catch (\Exception $e) {
                                    DB::rollBack();
                                    Log::error("❌ ADialParticipantsCommand Transaction Failed for Call ID {$callId}: " . $e->getMessage());
                                }
                            }
                        } else {
                            Log::error("❌ ADialParticipantsCommand Failed to fetch active calls. HTTP Status: " . $activeCallsResponse->getStatusCode());
                        }
                    } catch (\Exception $e) {
                        Log::error("❌ ADialParticipantsCommand Failed to process participant data: " . $e->getMessage());
                    }
                }
            } catch (\Exception $e) {
                Log::error("❌ ADialParticipantsCommand Failed fetching participants for provider {$provider->extension}: " . $e->getMessage());
            }


            // try {
            //     $token = $this->tokenService->getToken();

            //     $responseState = Http::withHeaders([
            //         'Authorization' => 'Bearer ' . $token,
            //     ])->get(config('services.three_cx.api_url') . "/callcontrol/{$ext_from}/participants");

            //     if (!$responseState->successful()) {
            //         Log::error("Failed to fetch participants for extension {$ext_from}. HTTP Status: {$responseState->status()}. Response: {$responseState->body()}");
            //         continue;
            //     }

            //     $participants = $responseState->json();

            //     if (empty($participants)) {
            //         Log::warning("⚠️ No participants found for extension {$ext_from}");
            //         continue;
            //     }

            //     Log::info("✅ Auto Dialer Participants Response: " . print_r($participants, true));

            //     foreach ($participants as $participant_data) {
            //         try {
            //             Log::info("✅ Auto Dialer Participants Response: " . print_r($participants, true));
            //             $filter = "contains(Caller, '{$participant_data['dn']}')";
            //             $url = config('services.three_cx.api_url') . "/xapi/v1/ActiveCalls?\$filter=" . urlencode($filter);

            //             $activeCallsResponse = Http::withHeaders([
            //                 'Authorization' => 'Bearer ' . $token,
            //             ])->get($url);

            //             if ($activeCallsResponse->successful()) {
            //                 $activeCalls = $activeCallsResponse->json();
            //                 Log::info("✅ Dial:Active Calls Response: " . print_r($activeCalls, True));

            //                 foreach ($activeCalls['value'] as $call) {

            //                     $status = $call['Status'];
            //                     $callId = $call['Id'];

            //                     $durationTime = null;
            //                     $durationRouting = null;

            //                     if ($status === 'Talking') {
            //                         $establishedAt = new DateTime($call['EstablishedAt']);
            //                         $serverNow = new DateTime($call['ServerNow']);
            //                         $durationTime = $establishedAt->diff($serverNow)->format('%H:%I:%S');
            //                         // Log::info("✅ Duration Time: ".$durationTime);
            //                     }

            //                     if ($status === 'Routing') {
            //                         $establishedAt = new DateTime($call['EstablishedAt']);
            //                         $serverNow = new DateTime($call['ServerNow']);
            //                         $durationRouting = $establishedAt->diff($serverNow)->format('%H:%I:%S');
            //                         // Log::info("✅ Duration Routing: ".$durationRouting);
            //                     }

            //                     DB::beginTransaction();
            //                     try {
            //                         AutoDailerReport::where('call_id', $callId)
            //                             ->update(['status' => $status, 'duration_time' => $durationTime, 'duration_routing' => $durationRouting]);
            //                         ADialData::where('call_id', $callId)
            //                             ->update(['state' => $status]);
            //                         // Log::info("✅ mobile status:: " . $status);
            //                         DB::commit();
            //                     } catch (\Exception $e) {
            //                         DB::rollBack();
            //                         Log::error("❌ Transaction Failed for call ID {$callId}: " . $e->getMessage());
            //                     }
            //                 }
            //             } else {
            //                 Log::error("❌ Failed to fetch active calls. Response: " . $activeCallsResponse->body());
            //             }
            //         } catch (\Exception $e) {
            //             Log::error("❌ Failed to process participant data for call ID " . ($participant_data['callid'] ?? 'N/A') . ": " . $e->getMessage());
            //         }
            //     }
            // } catch (\Exception $e) {
            //     Log::error("❌ Failed fetching participants for provider {$ext_from}: " . $e->getMessage());
            // }
        }


        Log::info("✅ Auto Dialer command execution completed.");
    }
}
