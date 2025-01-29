<?php

namespace App\Jobs;

use App\Models\AutoDailerReport;
use App\Models\AutoDailerUploadedData;
use App\Services\TokenService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;

class MakeCallJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $feedData;
    protected $tokenService;

    /**
     * Create a new job instance.
     */
    public function __construct($feedData, TokenService $tokenService)
    {
        $this->feedData = $feedData;
        $this->tokenService = $tokenService;
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        try {
            $token = $this->tokenService->getToken();
            $ext = $this->feedData->extension;

            $responseState = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token,
            ])->post(config('services.three_cx.api_url') . "/callcontrol/{$ext}/makecall", [
                'destination' => $this->feedData->mobile,
            ]);

            if ($responseState->successful()) {
                $responseData = $responseState->json();

                Log::info("
                    \t********** Auto Dialer Response Call **********
                    \tResponse Data:
                    \t" . print_r($responseData, true) . "
                    \t***********************************************
                ");

                AutoDailerReport::updateOrCreate(
                    ['call_id' => $responseData['result']['callid']],
                    [
                        'status' => $responseData['result']['status'],
                        'provider' => $this->feedData->provider,
                        'extension' => $responseData['result']['dn'],
                        'phone_number' => $responseData['result']['party_caller_id'],
                    ]
                );

                $this->feedData->update([
                    'state' => "Routing",
                    'call_date' => Carbon::now(),
                    'call_id' => $responseData['result']['callid'],
                    'party_dn_type' => $responseData['result']['party_dn_type'] ?? null,
                ]);

                Log::info("
                    \t📞 ✅ Auto Dialer Called Successfully for Mobile: " . $this->feedData->mobile . " 📞
                ");
            } else {
                Log::error("
                    \t❌ 🚨 Auto Dialer Failed 🚨 ❌
                    \t| Failed to make call for Mobile Number: " . $this->feedData->mobile . " |
                    \t| Response: " . $responseState->body() . " |
                ");
            }
        } catch (\Exception $e) {
            Log::error("
                \t❌ ❗ Error in Auto Dialer Job ❗ ❌
                \t| Exception: " . $e->getMessage() . " |
            ");
        }
    }
}
