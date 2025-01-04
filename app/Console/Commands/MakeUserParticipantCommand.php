<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Models\AutoDistributerFeedFile;
use App\Models\AutoDistributerReport;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use App\Models\AutoDistributerExtensionFeed;
use phpDocumentor\Reflection\PseudoTypes\True_;

class MakeUserParticipantCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:make-user-participant-command';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        Log::info('participantsCommand executed at ' . now());
        $now = Carbon::now();
        $token = Cache::get('three_cx_token');
        if (!$token) {
            Log::error('3CX token not found in cache');
            return;
        }

        $providersFeeds = AutoDistributerFeedFile::whereDate('created_at', Carbon::today())->get();

        foreach ($providersFeeds as $feed) {
            $ext_from = $feed->extension;

            try {
                // Fetch participants for the extension
                $responseState = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $token,
                ])->get(config('services.three_cx.api_url') . "/callcontrol/{$ext_from}/participants");

                if (!$responseState->successful()) {
                    Log::error("Failed to fetch participants for extension {$ext_from}. HTTP Status: {$responseState->status()}. Response: {$responseState->body()}");
                    continue;
                }

                $participants = $responseState->json();

                if (empty($participants)) {
                    Log::warning("No participants data for extension {$ext_from}");
                    continue;
                }

                foreach ($participants as $participant_data) {
                    try {
                        Log::debug("Processing participant data: " . print_r($participant_data, true));

                        $filter = "contains(Caller, '{$participant_data['dn']}')";
                        $url = "https://ecolor.3cx.agency/xapi/v1/ActiveCalls?\$filter=" . urlencode($filter);

                        $activeCallsResponse = Http::withHeaders([
                            'Authorization' => 'Bearer ' . $token,
                        ])->get($url);

                        if ($activeCallsResponse->successful()) {
                            $activeCalls = $activeCallsResponse->json();
                            Log::info("User Participant Active Call Response: " . print_r($activeCalls, true));

                            if (!empty($activeCalls['value'])) {
                                // Iterate through all active calls to find matching callId
                                foreach ($activeCalls['value'] as $call) {
                                    // Check if the call contains the required information
                                    Log::info("User Participant Active Call Response: " . print_r($activeCalls, true));
                                    if (isset($call['Id']) && isset($call['Status'])) {
                                        // Log the status to track each call's behavior
                                        Log::info("Processing Call ID {$call['Id']} with status {$call['Status']}");

                                        // Check if the call is in progress
                                        if ($call['Status'] === "Talking") { // Routing When Ringing
                                            AutoDistributerReport::where('call_id', $call['Id'])->update(['status' => "Wexternalline"]);
                                            Log::info("Updated status for call ID {$call['Id']} to 'Wexternalline'.");
                                        }
                                    } else {
                                        Log::warning("Call missing 'Id' or 'Status' for participant DN {$participant_data['dn']}. Call Data: " . print_r($call, true));
                                    }
                                }
                            } else {
                                Log::warning("No active calls found for participant DN {$participant_data['dn']}");
                            }
                        } else {
                            Log::error('Failed to fetch active calls. Response: ' . $activeCallsResponse->body());
                        }
                    } catch (\Exception $e) {
                        Log::error('Failed to process participant data for call ID ' . ($participant_data['callid'] ?? 'N/A') . ': ' . $e->getMessage());
                    }
                }
            } catch (\Exception $e) {
                Log::error("Error fetching participants for provider {$ext_from}: " . $e->getMessage());
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



    private function dropCall($ext_from, $participantId, $partyCallerId, $token)
    {
        $drop = "false";
        try {
            $action = "drop";
            $url = config('services.three_cx.api_url') . "/callcontrol/{$ext_from}/participants/{$participantId}/{$action}";

            // Request payload with dynamic destination
            $payload = [
                "reason" => "new call",
                "destination" => $partyCallerId,
                "timeout" => 0,
            ];

            $dropResponse = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
            ])->post($url, $payload);

            if ($dropResponse->successful()) {
                $drop = "true";
                Log::info("Successfully dropped the call for extension {$ext_from}, participant ID {$participantId}: " . json_encode($dropResponse->json()));
            } else {
                $drop = "false";
                Log::error("Failed to drop the call for extension {$ext_from}, participant ID {$participantId}. HTTP Status: {$dropResponse->status()}. Response: {$dropResponse->body()}");
            }
        } catch (\Exception $e) {
            Log::error("Error dropping call for extension {$ext_from}, participant ID {$participantId}: " . $e->getMessage());
        }
    }
}
