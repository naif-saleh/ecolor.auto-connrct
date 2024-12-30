<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Models\AutoDailerFeedFile;
use App\Models\AutoDailerReport;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class participantsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:participants-command';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch and process participants data from 3CX API';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        Log::info('participantsCommand executed at ' . now());

        $token = Cache::get('three_cx_token');
        if (!$token) {
            Log::error('3CX token not found in cache');
            return;
        }

        $providersFeeds = AutoDailerFeedFile::whereDate('created_at', Carbon::today())->get();

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
                        Log::debug("Processing participant data: " . json_encode($participant_data));

                        if ($participant_data['status'] === "Connected" && $participant_data['party_dn_type'] === "Wspecialmenu") {
                            $this->updateParticipant($participant_data);

                            // Attempt to drop the call if the status is "Connected"
                            $this->dropCall(
                                $ext_from,
                                $participant_data['id'],
                                $participant_data['party_caller_id'], // Pass party_caller_id dynamically
                                $token
                            );
                        } elseif($participant_data['status'] === "Connected" && $participant_data['party_dn_type'] === "Wextension"){
                            $this->updateParticipant($participant_data);
                        }elseif($participant_data['status'] === "Connected" && $participant_data['party_dn_type'] === "Wexternalline"){
                            $this->updateParticipant($participant_data);
                        }
                        else {
                            Log::info("Skipping dropCall for participant ID {$participant_data['id']} with status: {$participant_data['status']}");
                            AutoDailerReport::updateOrCreate(
                                [
                                    "call_id" => $participant_data['id'],
                                ],
                                [
                                    "status" => "no answer",
                                    "phone_number" => $participant_data['party_caller_id'],
                                    "provider" => $participant_data['dn'],
                                ]
                            );
                        }
                    } catch (\Exception $e) {
                        Log::error('Failed to process participant data for call ID ' . ($participant_data['id'] ?? 'N/A') . ': ' . $e->getMessage());
                    }
                }
            } catch (\Exception $e) {
                Log::error("Error fetching participants for extension {$ext_from}: " . $e->getMessage());
            }
        }
    }


    /**
     * Update or create participant report.
     */
    private function updateParticipant($participant_data)
    {
        AutoDailerReport::updateOrCreate(
            [
                "call_id" => $participant_data['id'],
            ],
            [
                "status" => $participant_data['party_dn_type'] ?? "Unknown",
                "phone_number" => $participant_data['party_caller_id'] ?? "Unknown",
                "provider" => $participant_data['dn'] ?? "Unknown",
            ]
        );
    }

    /**
     * Drop a call for a participant.
     */
    /**
     * Drop a call for a participant with additional payload.
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
                "destination" => $partyCallerId, // Dynamically set destination
                "timeout" => 0,
            ];

            $dropResponse = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json', // Ensure correct content type
            ])->post($url, $payload); // Pass payload as second argument

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
