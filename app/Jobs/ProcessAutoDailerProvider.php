<?php

namespace App\Jobs;

use App\Models\AutoDailerProviderFeed;
use App\Models\AutoDailerReport;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class ProcessAutoDailerProvider implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $token;

    /**
     * Create a new job instance.
     */
    public function __construct($token)
    {
        $this->token = $token;
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        // Fetch all records with state 'new'
        $records = AutoDailerProviderFeed::where('state', 'new')->get();

        if ($records->isEmpty()) {
            Log::info("No new records to process.");
            return;
        }

        foreach ($records as $record) {
            try {
                $from = $record->extension;
                $to = $record->mobile;

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
                    continue;
                }

                $partyDnType = "None";

                // Retry logic to check call status
                for ($i = 0; $i < 10; $i++) {
                    sleep(5);

                    $responseState = Http::withHeaders([
                        'Authorization' => 'Bearer ' . $this->token,
                    ])->get(config('services.three_cx.api_url') . "/callcontrol/{$from}/participants");

                    if ($responseState->failed()) {
                        continue;
                    }

                    $responseData = $responseState->json();
                    foreach ($responseData as $participant) {
                        if (isset($participant['party_dn_type']) && in_array($participant['party_dn_type'], ["Wextension", "Wspecialmenu", "None"])) {
                            $partyDnType = $participant['party_dn_type'];
                            break 2;
                        }
                    }
                }

                // Update the record state
                $record->state = match ($partyDnType) {
                    "Wextension" => "answered",
                    "Wspecialmenu" => "no answer",
                    default => "unknown",
                };

                $record->save();

                // Create a report
                AutoDailerReport::create([
                    'mobile' => $record->mobile,
                    'provider' => $record->provider_name,
                    'extension' => $record->extension,
                    'state' => $record->state,
                    'called_at' => now()->addHours(2)->setTimezone('UTC'),
                ]);

                // Log the successful call
                Log::info("Processed call for mobile: {$to}, state: {$record->state}");

                // Delay 15 seconds before the next call
                sleep(15);
            } catch (\Exception $e) {
                Log::error('Error processing record', [
                    'error' => $e->getMessage(),
                    'record' => $record,
                ]);
            }
        }
    }
}
