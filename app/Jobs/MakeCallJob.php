<?php

namespace App\Jobs;

use App\Models\AutoDailerReport;
use App\Models\ADialProvider;
use App\Services\TokenService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use DateTime;
use App\Models\ADialData;

class MakeCallJob implements ShouldBeUniqueUntilProcessing
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $feedData;
    protected $extension;
    protected $tokenService;

    /**
     * Create a new job instance.
     */
    public $uniqueFor = 60;

    public function __construct($feedData, TokenService $tokenService, $extension)
    {
        $this->feedData = $feedData;
        $this->tokenService = $tokenService;
        $this->extension = $extension;
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        try {
            $token = $this->tokenService->getToken();
            Log::info("Calling API for extension: " . $this->extension);

            $responseState = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token,
            ])->post(config('services.three_cx.api_url') . "/callcontrol/{$this->extension}/makecall", [
                'destination' => $this->feedData->mobile,
            ]);

            if ($responseState->successful()) {
                $responseData = $responseState->json();
                Log::info("dadad call id " . $responseData['result']['callid'] . ' mobile ' . $this->feedData->mobile);

                AutoDailerReport::updateOrCreate(
                    ['call_id' => $responseData['result']['callid']],
                    [
                        'status' => $responseData['result']['status'],
                        'provider' => $responseData['result']['dn'],
                        'extension' => $this->extension,
                        'phone_number' => $this->feedData->mobile,
                    ]
                );

                $this->feedData->update([
                    'state' => $responseData['result']['status'],
                    'call_date' => now(),
                    'call_id' => $responseData['result']['callid'],
                ]);

                Log::info("📞✅ Call successful for: " . $this->feedData->mobile);
            } else {
                Log::error("❌ Failed call: " . $this->feedData->mobile);
            }
            $providers = ADialProvider::all();
            foreach ($providers as $provider) {
                $ext_from = $provider->extension;

                try {
                    if (empty($token)) {
                        Log::error("Empty token for 3CX API access");
                        continue;
                    }

                    $responseState = Http::withHeaders([
                        'Authorization' => 'Bearer ' . $token,
                    ])->get(config('services.three_cx.api_url') . "/callcontrol/{$ext_from}/participants");

                    // Handle 401 specifically
                    if ($responseState->status() === 401) {
                        Log::error("Authentication failed for 3CX API. Token may be expired or invalid.");
                        $token = $this->tokenService->getToken();

                        $responseState = Http::withHeaders([
                            'Authorization' => 'Bearer ' . $token,
                        ])->get(config('services.three_cx.api_url') . "/callcontrol/{$ext_from}/participants");

                        if (!$responseState->successful()) {
                            Log::error("Failed to fetch participants even after token refresh. HTTP Status: {$responseState->status()}");
                            continue;
                        }
                    }

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
                                    if (!$callId || !$status) {
                                        Log::error("Missing call ID or status in API response");
                                        return;
                                    }
                                    // Get current record to preserve existing values
                                    $currentReport = AutoDailerReport::where('call_id', $callId)->first();
                                    $durationTime = $currentReport ? $currentReport->duration_time : null;
                                    $durationRouting = $currentReport ? $currentReport->duration_routing : null;

                                    // Update the appropriate duration based on current status
                                    $serverNow = new DateTime($call['ServerNow']);

                                    if ($status === 'Talking' && isset($call['EstablishedAt'])) {
                                        $establishedAt = new DateTime($call['EstablishedAt']);
                                        $durationTime = $establishedAt->diff($serverNow)->format('%H:%I:%S');
                                    }

                                    if ($status === 'Routing' && isset($call['EstablishedAt'])) {
                                        $establishedAt = new DateTime($call['EstablishedAt']);
                                        $durationRouting = $establishedAt->diff($serverNow)->format('%H:%I:%S');
                                    }

                                    // For the table display, you might also want to handle "Ringing" status
                                    if ($status === 'Ringing' && isset($call['EstablishedAt'])) {
                                        $establishedAt = new DateTime($call['EstablishedAt']);
                                        $durationRouting = $establishedAt->diff($serverNow)->format('%H:%I:%S');
                                    }

                                    DB::beginTransaction();
                                    try {
                                        AutoDailerReport::where('call_id', $callId)
                                            ->update([
                                                'status' => $status,
                                                'duration_time' => $durationTime,
                                                'duration_routing' => $durationRouting
                                            ]);
                                        ADialData::where('call_id', $callId)
                                            ->update(['state' => $status]);
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
        } catch (\Exception $e) {
            Log::error("❌ Exception: " . $e->getMessage());
        }

        return 0;
    }
    /**
     * Define unique job key (Ensures uniqueness for each mobile)
     */
    public function uniqueId(): string
    {
        return $this->feedData->mobile . "."; // Unique identifier
    }
}
