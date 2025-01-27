<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Models\AutoDistributorUploadedData;
use App\Models\AutoDistributerReport;
use Illuminate\Support\Facades\Http;
use DateTime;
use App\Services\TokenService;


class MakeUserParticipantCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:make-user-participant-command';
    protected $threeCXTokenService;
    protected $tokenService;
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';
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
                    \t\t\t********** Auto Distributor **********\n
                    \t-----------------------------------------------------------------------
                    \t| 📞 ✅ PartisipantCommand executed at " . now() . "            |
                    \t-----------------------------------------------------------------------
                ");


        $providersFeeds = AutoDistributorUploadedData::all();

        foreach ($providersFeeds as $feed) {
            $ext_from = $feed->extension;

            try {
                $token = $this->tokenService->getToken();

                Log::info("tokenServices: MakeUserParticipantCommand" . $token );
                // Log::error("participantsCommand token " . $token);

                Log::error("participantsCommand token " . $token);

                // Fetch participants for the extension
                $responseState = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $token,
                ])->get(config('services.three_cx.api_url') . "/callcontrol/{$ext_from}/participants");

                if (!$responseState->successful()) {
                    Log::error("participantsCommand Failed to fetch participants for extension {$ext_from}. HTTP Status: {$responseState->status()}. Response: {$responseState->body()}");
                    Log::info('participantsCommand:  Response Status Code: ' . $responseState->status());
                    Log::info('participantsCommand:  Full Response: ' . print_r($responseState, TRUE));
                    Log::info('participantsCommand: Headers: ' . json_encode($responseState->headers()));
                    continue;
                }

                $participants = $responseState->json();

                if (empty($participants)) {
                    Log::warning("
                                    \t-----------------------------------------------------------------------
                                    \t\t********** Auto Distributor Warning **********\n
                                    \t-----------------------------------------------------------------------
                                    \t ⚠️  No participantsCommand for {$ext_from}
                                    \t-----------------------------------------------------------------------
                            ");
                    continue;
                }

                foreach ($participants as $participant_data) {
                    try {
                        // Log::debug("Processing participant data For Auto Dailer: " . print_r($participant_data, true));

                        $filter = "contains(Caller, '{$participant_data['dn']}')";
                        $url = config('services.three_cx.api_url') . "/xapi/v1/ActiveCalls?\$filter=" . urlencode($filter);

                        $activeCallsResponse = Http::withHeaders([
                            'Authorization' => 'Bearer ' . $token,
                        ])->get($url);

                        if ($activeCallsResponse->successful()) {
                            $activeCalls = $activeCallsResponse->json();
                            Log::info("
                                        \t********** Auto Distributor Response Participant Active Call **********
                                        \tResponse Data:
                                        \t" . print_r($activeCalls, true) . "
                                        \t******************************************************************
                                    ");
                            foreach ($activeCalls['value'] as $call) {
                                if (isset($call['Id']) && isset($call['Status']) && isset($call['EstablishedAt']) && isset($call['ServerNow'])) {
                                    Log::info("Processing Call ID {$call['Id']} with status {$call['Status']}");

                                    // Only calculate duration if the call is in 'Talking' status
                                    if ($call['Status'] === 'Talking') {
                                        // Calculate call duration
                                        $establishedAt = new DateTime($call['EstablishedAt']);
                                        $serverNow = new DateTime($call['ServerNow']);
                                        $interval = $establishedAt->diff($serverNow);
                                        $durationTime = $interval->format('%H:%I:%S');

                                        // Update call status and duration_time
                                        AutoDistributerReport::where('call_id', $call['Id'])->update([
                                            'status' => $call['Status'],
                                            'duration_time' => $durationTime
                                        ]);
                                    } else {
                                        // If not in 'Talking' status, update only the status
                                        AutoDistributerReport::where('call_id', $call['Id'])->update([
                                            'status' => $call['Status']
                                        ]);
                                    }

                                    // Update the status in AutoDistributorUploadedData
                                    AutoDistributorUploadedData::where('call_id', $call['Id'])->update(['state' => $call['Status']]);

                                    Log::info("
                                                \t-----------------------------------------------------------------------
                                                \t\t********** Auto Distributor Call Status Updated **********
                                                \t-----------------------------------------------------------------------
                                                \t| ✅ Updated status for Call ID {$call['Id']} to: {$call['Status']} |
                                                \t-----------------------------------------------------------------------
                                                ");
                                } else {
                                    // Log a warning for calls missing necessary data
                                    Log::warning("
                                                \t-----------------------------------------------------------------------
                                                \t\t********** Auto Distributor Warning **********
                                                \t-----------------------------------------------------------------------
                                                \t| ⚠️ Call missing 'Id', 'Status', or other required fields for Call Data: " . print_r($call, true) . " |
                                                \t-----------------------------------------------------------------------
                                                ");
                                }
                            }
                        } else {
                            Log::error('Auto Distributor Error: ❌ Failed to fetch active calls. Response: ' . $activeCallsResponse->body());
                        }
                    } catch (\Exception $e) {
                        Log::error('Auto Distributor Error: ❌ Failed to process participant data for call ID ' . ($participant_data['callid'] ?? 'N/A') . ': ' . $e->getMessage());
                    }
                }
            } catch (\Exception $e) {
                Log::error("Auto Distributor Error: ❌ Failed participants for provider {$ext_from}: " . $e->getMessage());
            }
        }
    }

    /**
     * Update or create participant report.
     */
    private function updateReportStatus($callId, $status)
    {
        AutoDistributerReport::where('call_id', $callId)->update(['status' => $status]);
    }

    /**
     * Drop a call for a participant.
     */
    /**
     * Drop a call for a participant with additional payload.
     */



    // private function dropCall($ext_from, $participantId, $partyCallerId, $token)
    // {
    //     $drop = "false";
    //     try {
    //         $action = "drop";
    //         $url = config('services.three_cx.api_url') . "/callcontrol/{$ext_from}/participants/{$participantId}/{$action}";

    //         // Request payload with dynamic destination
    //         $payload = [
    //             "reason" => "new call",
    //             "destination" => $partyCallerId,
    //             "timeout" => 0,
    //         ];

    //         $dropResponse = Http::withHeaders([
    //             'Authorization' => 'Bearer ' . $token,
    //             'Content-Type' => 'application/json',
    //         ])->post($url, $payload);

    //         if ($dropResponse->successful()) {
    //             $drop = "true";
    //             Log::info("Successfully dropped the call for extension {$ext_from}, participant ID {$participantId}: " . json_encode($dropResponse->json()));
    //         } else {
    //             $drop = "false";
    //             Log::error("Failed to drop the call for extension {$ext_from}, participant ID {$participantId}. HTTP Status: {$dropResponse->status()}. Response: {$dropResponse->body()}");
    //         }
    //     } catch (\Exception $e) {
    //         Log::error("Error dropping call for extension {$ext_from}, participant ID {$participantId}: " . $e->getMessage());
    //     }
    // }
}
