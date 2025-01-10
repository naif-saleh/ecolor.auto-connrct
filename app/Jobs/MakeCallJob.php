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
use App\Models\AutoDistributorUploadedData;
use App\Models\AutoDistributerReport;

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
        $ext = $this->mobile->extension;

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
                'status' => "Initiating",
                'provider' => $this->mobile->user,
                'extension' => $responseData['result']['dn'],
                'phone_number' => $responseData['result']['party_caller_id'],
            ]);

            $reports->save();

            // Update the mobile record
            $this->mobile->update([
                'state' => "Initiating",
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
    }
}
