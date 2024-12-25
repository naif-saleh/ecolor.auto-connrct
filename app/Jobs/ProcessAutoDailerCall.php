<?php

namespace App\Jobs;

use App\Models\AutoDailerData;
use App\Models\AutoDailerReport;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class ProcessAutoDailerCall implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $record;
    protected $token;

    /**
     * Create a new job instance.
     */
    public function __construct($record, $token)
    {
        $this->record = $record;
        $this->token = $token;
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        $from = $this->record['extension'];
        $to = $this->record['mobile'];

        // Make the call
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->post(config('services.three_cx.api_url') . "/callcontrol/{$from}/makecall", [
            'destination' => $to,
        ]);

        if ($response->failed()) {
            Log::error('3CX Call Failed', [
                'mobile' => $to,
                'response' => $response->body(),
            ]);
            return;
        }

        // Poll for call status
        $maxRetries = 10;
        $retryInterval = 5; // seconds


        for ($i = 0; $i < $maxRetries; $i++) {
            sleep($retryInterval);

            $responseState = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->token,
            ])->get(config('services.three_cx.api_url') . "/callcontrol/{$from}/participants");

            if ($responseState->successful()) {
                $responseData = $responseState->json();
                foreach ($responseData as $participant) {

                    $partyDnType = $participant['party_dn_type'] ?? "None";
                    // Break if the call reaches a terminal state
                    if (in_array($partyDnType, ["Wextension", "Wspecialmenu", "None"])) {
                        break 2; // Exit both loops
                    }
                }
            }
            dd($partyDnType);
        }
         
        // Update record state
        $autoDailerData = AutoDailerData::find($this->record['id']);
        if ($partyDnType === "Wextension") {
            $autoDailerData->state = "answered";
        } elseif ($partyDnType === "Wspecialmenu") {
            $autoDailerData->state = "declined";
        } elseif ($partyDnType === "None") {
            $autoDailerData->state = "no answer";
        } else {
            $autoDailerData->state = "unknown";
        }
        $autoDailerData->save();

        // Add to report
        AutoDailerReport::create([
            'mobile' => $autoDailerData->mobile,
            'provider' => $autoDailerData->provider_name,
            'extension' => $autoDailerData->extension,
            'state' => $autoDailerData->state,
            'called_at' => now()->addHours(2),
        ]);
    }
}
