<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use App\Models\AutoDistributerReport;

class MakeUserCallJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    protected $mobile;
    protected $token;

    public function __construct($mobile, $token)
    {
        $this->mobile = $mobile;
        $this->token = $token;
    }

    public function handle()
    {
        try {
            if (!$this->mobile || !$this->mobile->extension || !$this->mobile->mobile) {
                Log::error('ADist: Invalid mobile data for mobile ' . $this->mobile);
                return;
            }

            $ext = $this->mobile->extension;
            if ($this->mobile->userStatus === "Available") {
                // Check if there are active calls for this extension
                $filter = "contains(Caller, '{$ext}')";
                $url = "https://ecolor.3cx.agency/xapi/v1/ActiveCalls?\$filter=" . urlencode($filter);

                // Logging the API call URL for debugging
                Log::info('Fetching active calls from URL: ' . $url);

                $activeCallsResponse = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $this->token,
                ])->get($url);

                if ($activeCallsResponse->failed()) {
                    Log::error('ADist: Failed to fetch active calls for mobile ' . $this->mobile->mobile . '. Response: ' . $activeCallsResponse->body());
                    return;
                }

                if ($activeCallsResponse->successful()) {
                    $activeCalls = $activeCallsResponse->json();

                    if (!empty($activeCalls['value'])) {
                        Log::info("Active calls detected for extension {$ext}. Skipping call for mobile {$this->mobile->mobile}.");
                        return; // Skip this number if active calls exist
                    }

                    // No active calls, proceed to make the call
                    $responseState = Http::withHeaders([
                        'Authorization' => 'Bearer ' . $this->token,
                    ])->post(config('services.three_cx.api_url') . "/callcontrol/{$ext}/makecall", [
                        'destination' => $this->mobile->mobile,
                    ]);

                    if ($responseState->successful()) {
                        $responseData = $responseState->json();

                        // Handle the report creation and update
                        $reports = AutoDistributerReport::firstOrCreate([
                            'call_id' => $responseData['result']['callid'],
                        ], [
                            'status' => $responseData['result']['status'],
                            'provider' => $this->mobile->user,
                            'extension' => $responseData['result']['dn'],
                            'phone_number' => $responseData['result']['party_caller_id'],
                        ]);

                        $reports->save();

                        // Update the mobile record
                        $this->mobile->update([
                            'state' => $responseData['result']['status'],
                            'call_date' => Carbon::now(),
                            'call_id' => $responseData['result']['callid'],
                            'party_dn_type' => $responseData['result']['party_dn_type'] ?? null,
                        ]);

                        Log::info('ADist: Call successfully made for mobile ' . $this->mobile->mobile);
                    } else {
                        Log::error('ADist: Failed to make call for mobile ' . $this->mobile->mobile . '. Response: ' . $responseState->body());
                        Log::info('ADist: Response Status Code: ' . $responseState->status());
                        Log::info('ADist: Full Response: ' . print_r($responseState, true));
                        Log::info('ADist: Headers: ' . json_encode($responseState->headers()));
                    }
                } else {
                    Log::error('ADist: Error fetching active calls for mobile ' . $this->mobile->mobile);
                }
            } else {
                Log::error('ADist: Mobile is not available. Skipping call for mobile ' . $this->mobile->mobile);
            }
        } catch (\Exception $e) {
            Log::error('ADist: An error occurred: ' . $e->getMessage());
        }
    }
}
