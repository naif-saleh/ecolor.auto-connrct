<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Models\AutoDailerReport;

class MakeCallJob implements ShouldQueue
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
            $responseState = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->token,
            ])->post(config('services.three_cx.api_url') . "/callcontrol/{$this->mobile->extension}/makecall", [
                'destination' => $this->mobile->mobile,
            ]);

            // Process the response as before
            if ($responseState->successful()) {
                $responseData = $responseState->json();
                // Update the report and the mobile as before
            } else {
                Log::error('Failed to make call for mobile ' . $this->mobile->mobile);
            }
        } catch (\Exception $e) {
            Log::error('Error: ' . $e->getMessage());
        }
    }





















    // public function handle()
    // {
    //     try {
    //         $responseState = Http::withHeaders([
    //             'Authorization' => 'Bearer ' . $this->token,
    //         ])->post(config('services.three_cx.api_url') . "/callcontrol/{$this->mobile->extension}/makecall", [
    //             'destination' => $this->mobile->mobile,
    //         ]);

    //         if ($responseState->successful()) {
    //             $responseData = $responseState->json();
    //             // Update or create report
    //             AutoDailerReport::firstOrCreate([
    //                 'call_id' => $responseData['result']['callid'],
    //                 'status' => $responseData['result']['status'],
    //                 'provider' => $this->mobile->provider,
    //                 'extension' => $responseData['result']['dn'],
    //                 'phone_number' => $responseData['result']['party_caller_id'],
    //             ]);

    //             $this->mobile->update([
    //                 'state' => $responseData['result']['status'],
    //                 'call_date' => Carbon::now(),
    //                 'call_id' => $responseData['result']['callid'],
    //             ]);

    //             Log::info('Call successfully made for mobile ' . $this->mobile->mobile);
    //         } else {
    //             Log::error('Failed to make call for mobile ' . $this->mobile->mobile . '. Response: ' . $responseState->body());
    //         }
    //     } catch (\Exception $e) {
    //         Log::error('An error occurred: ' . $e->getMessage());
    //     }
    // }
}
