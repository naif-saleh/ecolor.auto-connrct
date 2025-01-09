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
            $ext = $this->mobile->extension; // Corrected: Access the $mobile property
            if ($this->mobile->userStatus === "Available") { // Corrected: Access the $mobile property
                // Check if there are active calls for this extension
                $filter = "contains(Caller, '{$ext}')";
                $url = "https://ecolor.3cx.agency/xapi/v1/ActiveCalls?\$filter=" . urlencode($filter);

                $activeCallsResponse = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $this->token, // Corrected: Access the $token property
                ])->get($url);

                if ($activeCallsResponse->successful()) {
                    $activeCalls = $activeCallsResponse->json();

                    if (!empty($activeCalls['value'])) {
                        Log::info("Active calls detected for extension {$ext}. Skipping call for mobile {$this->mobile->mobile}."); // Corrected: Access the $mobile property
                        return; // Skip this number if active calls exist
                    }

                    // No active calls, proceed to make the call
                    $responseState = Http::withHeaders([
                        'Authorization' => 'Bearer ' . $this->token, // Corrected: Access the $token property
                    ])->post(config('services.three_cx.api_url') . "/callcontrol/{$ext}/makecall", [
                        'destination' => $this->mobile->mobile, // Corrected: Access the $mobile property
                    ]);

                    if ($responseState->successful()) {
                        $responseData = $responseState->json();

                        // Handle the report creation and update
                        $reports = AutoDistributerReport::firstOrCreate([
                            'call_id' => $responseData['result']['callid'],
                        ], [
                            'status' => $responseData['result']['status'],
                            'provider' => $this->mobile->user, // Corrected: Access the $mobile property
                            'extension' => $responseData['result']['dn'],
                            'phone_number' => $responseData['result']['party_caller_id'],
                        ]);

                        $reports->save();

                        // Update the mobile record
                        $this->mobile->update([ // Corrected: Access the $mobile property
                            'state' => $responseData['result']['status'],
                            'call_date' => Carbon::now(),
                            'call_id' => $responseData['result']['callid'],
                            'party_dn_type' => $responseData['result']['party_dn_type'] ?? null,
                        ]);

                        Log::info('ADist: Call successfully made for mobile ' . $this->mobile->mobile); // Corrected: Access the $mobile property
                    } else {
                        Log::error('ADist: Failed to make call for mobile ' . $this->mobile->mobile . '. Response: ' . $responseState->body()); // Corrected: Access the $mobile property
                        Log::info('ADist: Response Status Code: ' . $responseState->status());
                        Log::info('ADist: Full Response: ' . print_r($responseState, true));
                        Log::info('ADist: Headers: ' . json_encode($responseState->headers()));
                    }
                } else {
                    Log::error('ADist: Error fetching active calls for mobile ' . $this->mobile->mobile); // Corrected: Access the $mobile property
                }
            } else {
                Log::error('ADist: Mobile is not available. Skipping call for mobile ' . $this->mobile->mobile); // Corrected: Access the $mobile property
            }
        } catch (\Exception $e) {
            Log::error('ADist: An error occurred: ' . $e->getMessage());
        }
    }
}
