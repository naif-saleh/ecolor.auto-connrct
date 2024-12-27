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

    protected $record;
    protected $token;

    /**
     * Create a new job instance.
     *
     * @param object $record
     * @param string $token
     */
    public function __construct($record, $token)
    {
        $this->record = $record;
        $this->token = $token;
    }

    
    public function handle()
    {
        try {
            $from = $this->record->extension;
            $to = $this->record->mobile;

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
                return;
            }

            Log::info("Call initiated successfully for mobile: {$to}");

            // Check if the call ended (answered or no answer)
            $callState = 'unknown'; // Start as unknown

            // Poll for the call status
            $maxRetries = 20; // Check up to 100 seconds (20 retries x 5 seconds)
            $retryInterval = 5; // seconds
            for ($i = 0; $i < $maxRetries; $i++) {
                sleep($retryInterval);

                $responseState = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $this->token,
                ])->get(config('services.three_cx.api_url') . "/callcontrol/{$from}/participants");

                if ($responseState->failed()) {
                    Log::warning("Failed to retrieve call state for mobile: {$to}");
                    continue;
                }

                $responseData = $responseState->json();

                // Check if the call is answered or has no answer
                foreach ($responseData as $participant) {
                    if (isset($participant['party_dn_type'])) {
                        $callState = match ($participant['party_dn_type']) {
                            "Wextension" => 'answered',
                            "Wspecialmenu" => 'no answer',
                            default => 'ringing',
                        };

                        // If call ended, exit the loop
                        if (in_array($callState, ['answered', 'no answer'])) {
                            break 2;
                        }
                    }
                }
            }

            // Update the record state based on the final call state
            $record = AutoDailerProviderFeed::find($this->record->id);
            if (!$record) {
                Log::warning("Record not found for ID {$this->record->id}");
                return;
            }

            // Set final call state (answered, no answer, or unknown)
            $record->state = $callState;
            $record->save();

            // Create a report
            AutoDailerReport::create([
                'mobile' => $record->mobile,
                'provider' => $record->provider_name,
                'extension' => $record->extension,
                'state' => $record->state,
                'called_at' => now()->addHours(2)->setTimezone('UTC'),
            ]);
            sleep(15);
        } catch (\Exception $e) {
            Log::error('Job Failed', [
                'error' => $e->getMessage(),
                'record' => $this->record,
            ]);
        }
    }
}
