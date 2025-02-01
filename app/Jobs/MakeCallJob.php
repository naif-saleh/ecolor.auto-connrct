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
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;


class MakeCallJob implements  ShouldBeUniqueUntilProcessing
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
                Log::info("dadad call id " .  $responseData['result']['callid']. ' mobile ' .$this->feedData->mobile );
                AutoDailerReport::updateOrCreate(
                    ['call_id' => $responseData['result']['callid']],
                    [
                        'status' => $responseData['result']['status'],
                        'provider' => $this->feedData->provider_name,
                        'extension' => $this->extension,
                        'phone_number' => $this->feedData->mobile,
                    ]
                );

                $this->feedData->update([
                    'state' => "Routing",
                    'call_date' => now(),
                    'call_id' => $responseData['result']['callid'],
                ]);

                Log::info("📞✅ Call successful for: " . $this->feedData->mobile);
            } else {
                Log::error("❌ Failed call: " . $this->feedData->mobile);
            }
        } catch (\Exception $e) {
            Log::error("❌ Exception: " . $e->getMessage());
        }
    }
    /**
     * Define unique job key (Ensures uniqueness for each mobile)
     */
    public function uniqueId(): string
    {
        return $this->feedData->mobile . "aa"; // Unique identifier
    }
}
