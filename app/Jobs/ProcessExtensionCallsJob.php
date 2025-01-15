<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\AutoDistributorUploadedData;
use Carbon\Carbon;

class ProcessExtensionCallsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $extension;
    protected $token;

    public function __construct($extension, $token)
    {
        $this->extension = $extension;
        $this->token = $token;
    }

    public function handle()
    {
        $now = Carbon::now();
        $calls = AutoDistributorUploadedData::where('extension', $this->extension)
            ->where('state', 'new')
            ->get();

        foreach ($calls as $call) {
            Log::info("Processing call for extension {$this->extension}, mobile {$call->mobile}");

            // Logic to check active calls and make the call
            $url = config('services.three_cx.api_url') . "/xapi/v1/ActiveCalls?\$filter=contains(Caller, '{$this->extension}')";
            $activeCallsResponse = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->token,
            ])->get($url);

            if ($activeCallsResponse->successful()) {
                $activeCalls = $activeCallsResponse->json();
                if (!empty($activeCalls['value'])) {
                    Log::info("Active calls detected for extension {$this->extension}. Skipping call for mobile {$call->mobile}.");
                    continue;
                }

                $dnDevices = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $this->token,
                ])->get(config('services.three_cx.api_url') . "/callcontrol/{$this->extension}/devices");

                if ($dnDevices->successful()) {
                    $devices = $dnDevices->json();
                    foreach ($devices as $device) {
                        if ($device['user_agent'] === '3CX Mobile Client') {
                            $responseState = Http::withHeaders([
                                'Authorization' => 'Bearer ' . $this->token,
                            ])->post(config('services.three_cx.api_url') . "/callcontrol/{$this->extension}/devices/{$device['device_id']}/makecall", [
                                'destination' => $call->mobile,
                            ]);

                            if ($responseState->successful()) {
                                $responseData = $responseState->json();
                                Log::info('Call successfully made for mobile ' . $call->mobile);
                                $call->update([
                                    'state' => "Initiating",
                                    'call_date' => Carbon::now(),
                                    'call_id' => $responseData['result']['callid'],
                                    'party_dn_type' => $responseData['result']['party_dn_type'] ?? null,
                                ]);
                            } else {
                                Log::error('Failed to make call for mobile ' . $call->mobile . '. Response: ' . $responseState->body());
                            }
                            break;
                        }
                    }
                }
            }
        }

        Log::info("Finished processing calls for extension {$this->extension}");
    }
}
