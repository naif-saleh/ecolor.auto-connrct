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
     *
     * @param string $token
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
        try {
            // Fetch all records with state 'new'
            $records = AutoDailerProviderFeed::where('state', 'new')->get();

            if ($records->isEmpty()) {
                Log::info("No new records to process.");
                return;
            }

            foreach ($records as $record) {
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
                        'response_code' => $response->status(),
                        'response_body' => $response->body(),
                    ]);
                    continue;
                }

                Log::info("Call initiated successfully for mobile: {$to}");

                $partyDnType = "None";

                // Check the call status
                for ($i = 0; $i < 10; $i++) {
                    sleep(5);

                    $responseState = Http::withHeaders([
                        'Authorization' => 'Bearer ' . $this->token,
                    ])->get(config('services.three_cx.api_url') . "/callcontrol/{$from}/participants");

                    if ($responseState->failed()) {
                        Log::warning("Failed to retrieve call state for mobile: {$to}");
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

                // Update the record state based on the result
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

                Log::info("Call processed for mobile: {$to}, state: {$record->state}");

                // Wait 15 seconds before the next call
                sleep(15);
            }
        } catch (\Exception $e) {
            Log::error('Job Failed', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
