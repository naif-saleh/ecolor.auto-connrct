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
        Log::info('✅ ADialParticipantsCommand started at ' . Carbon::now());

        $providers = ADialProvider::all();
        Log::info("Total providers found: " . $providers->count());

        $client = new Client();

        try {
            $token = $this->tokenService->getToken();
        } catch (\Exception $e) {
            Log::error("❌ Failed to retrieve token: " . $e->getMessage());
            return;
        }

        foreach ($providers as $provider) {
            Log::info("Processing provider: {$provider->extension}");

            // ✅ Fetch participants for the provider
            try {
                $responseState = $client->get(config('services.three_cx.api_url') . "/callcontrol/{$provider->extension}/participants", [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $token,
                        'Accept' => 'application/json',
                    ],
                    'timeout' => 20,
                ]);

                if ($responseState->getStatusCode() !== 200) {
                    Log::error("❌ Failed to fetch participants. HTTP Status: {$responseState->getStatusCode()}");
                    continue;
                }

                $participants = json_decode($responseState->getBody()->getContents(), true);
                if (empty($participants)) {
                    Log::warning("⚠️ No participants found for extension {$provider->extension}");
                    continue;
                }
            } catch (\Exception $e) {
                Log::error("❌ Failed to fetch participants for provider {$provider->extension}: " . $e->getMessage());
                continue;
            }

            // ✅ Fetch Active Calls (Only Per Provider)
            try {
                $filter = "contains(Caller, '{$provider->extension}')";
                $url = config('services.three_cx.api_url') . "/xapi/v1/ActiveCalls?\$filter=" . urlencode($filter);

                $activeCallsResponse = $client->get($url, [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $token,
                        'Accept' => 'application/json',
                    ],
                    'timeout' => 30,
                ]);

                if ($activeCallsResponse->getStatusCode() !== 200) {
                    Log::error("❌ Failed to fetch active calls. HTTP Status: " . $activeCallsResponse->getStatusCode());
                    continue;
                }

                $activeCalls = json_decode($activeCallsResponse->getBody()->getContents(), true);
                // Log::info("Active Call per Provider: ",print_r($activeCalls, True));
                if (empty($activeCalls['value'])) {
                    Log::warning("⚠️ No active calls found for provider {$provider->extension}");
                    continue;
                }
            } catch (\Exception $e) {
                Log::error("❌ Failed to fetch active calls for provider {$provider->extension}: " . $e->getMessage());
                continue;
            }

            // Update database per bluk batching...........
            $updates = [];
            $dataUpdates = [];

            foreach ($activeCalls['value'] as $call) {
                $callId = $call['Id'];
                $status = $call['Status'];

                try {
                    // Fetch existing call record
                    $existingRecord = AutoDailerReport::where('call_id', $callId)->first(['duration_time', 'duration_routing']);

                    $durationTime = $existingRecord->duration_time ?? null;
                    $durationRouting = $existingRecord->duration_routing ?? null;

                    if (isset($call['EstablishedAt'], $call['ServerNow'])) {
                        $establishedAt = Carbon::parse($call['EstablishedAt']);
                        $serverNow = Carbon::parse($call['ServerNow']);
                        $currentDuration = $establishedAt->diff($serverNow)->format('%H:%I:%S');

                        if ($status === 'Talking') {
                            $durationTime = $currentDuration;
                        } elseif ($status === 'Routing') {
                            $durationRouting = $currentDuration;
                        }
                    }


                    $updates[] = [
                        'call_id' => $callId,
                        'status' => $status,
                        'duration_time' => $durationTime,
                        'duration_routing' => $durationRouting,
                        'updated_at' => now(),
                    ];

                    $dataUpdates[] = [
                        'call_id' => $callId,
                        'state' => $status,
                        'updated_at' => now(),
                    ];

                    Log::info("✅ Updated Call: {$callId}, Status: {$status}, Talking Duration: {$durationTime}, Routing Duration: {$durationRouting}");
                } catch (\Exception $e) {
                    Log::error("❌ Failed to process Call ID {$callId}: " . $e->getMessage());
                }
            }


            if (!empty($updates)) {
                AutoDailerReport::upsert($updates, ['call_id'], ['status', 'duration_time', 'duration_routing', 'updated_at']);
            }

            if (!empty($dataUpdates)) {
                ADialData::upsert($dataUpdates, ['call_id'], ['state', 'updated_at']);
            }
        }

        Log::info("✅ ADialParticipantsCommand execution completed.");
    }


}
