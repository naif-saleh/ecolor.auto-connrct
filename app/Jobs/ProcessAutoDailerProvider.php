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

    /**
     * Execute the job.
     */
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

            // Wait for the call to terminate
            $maxRetries = 10;
            $retryInterval = 5; // seconds
            $callActive = true;

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

                // Check if the call is still active
                $callActive = false;
                foreach ($responseData as $participant) {
                    if (isset($participant['status']) && $participant['status'] === 'connected') {
                        $callActive = true;
                        break;
                    }
                }

                if (!$callActive) {
                    Log::info("Call terminated for mobile: {$to}");
                    break;
                }
            }

            if ($callActive) {
                Log::warning("Call did not terminate within the expected time for mobile: {$to}");
            }

            // Update the record state
            $record = AutoDailerProviderFeed::find($this->record->id);
            if (!$record) {
                Log::warning("Record not found for ID {$this->record->id}");
                return;
            }

            $record->state = $callActive ? 'unknown' : 'completed';
            $record->save();

            // Create a report
            AutoDailerReport::create([
                'mobile' => $record->mobile,
                'provider' => $record->provider_name,
                'extension' => $record->extension,
                'state' => $record->state,
                'called_at' => now()->addHours(2)->setTimezone('UTC'),
            ]);

        } catch (\Exception $e) {
            Log::error('Job Failed', [
                'error' => $e->getMessage(),
                'record' => $this->record,
            ]);
        }
    }
}
