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
            $from = $this->record['extension'];
            $to = $this->record['mobile'];

            // Make the call
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->token,
            ])->post(config('services.three_cx.api_url') . "/callcontrol/{$from}/makecall", [
                'destination' => $to,
            ]);

            if ($response->failed()) {
                throw new \Exception('3CX Call Failed: ' . $response->body());
            }

            $partyDnType = "None";

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

            $autoDailerData = AutoDailerProviderFeed::find($this->record['id']);
            if (!$autoDailerData) {
                Log::warning("AutoDailerData not found for ID {$this->record['id']}");
                return;
            }

            $autoDailerData->state = match ($partyDnType) {
                "Wextension" => "answered",
                "Wspecialmenu" => "no answer",
                default => "unknown",
            };

            $autoDailerData->save();

            AutoDailerReport::create([
                'mobile' => $autoDailerData->mobile,
                'provider' => $autoDailerData->provider_name,
                'extension' => $autoDailerData->extension,
                'state' => $autoDailerData->state,
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
