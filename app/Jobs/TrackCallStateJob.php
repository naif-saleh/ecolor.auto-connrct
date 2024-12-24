<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\AutoDailerData;
use App\Models\AutoDailerReport;

class TrackCallStateJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $from;
    protected $to;
    protected $recordId;
    protected $token;

    /**
     * Create a new job instance.
     *
     * @param string $from
     * @param string $to
     * @param int $recordId
     * @param string $token
     */
    public function __construct($from, $to, $recordId, $token)
    {
        $this->from = $from;
        $this->to = $to;
        $this->recordId = $recordId;
        $this->token = $token;
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        $autoDailerData = AutoDailerData::find($this->recordId);
        $finalStates = ['answered', 'declined', 'no answer'];
        $maxRetries = 10; // Limit retries
        $retryCount = 0;

        while ($retryCount < $maxRetries) {
            $responseState = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->token,
            ])->get(config('services.three_cx.api_url') . "/callcontrol/{$this->from}/participants");

            if ($responseState->successful()) {
                $responseData = $responseState->json();
                $partyDnType = $responseData[0]['status'] ?? null;

                if ($partyDnType) {
                    if ($partyDnType === "Wextension") {
                        $autoDailerData->state = "answered";
                    } elseif ($partyDnType === "Wspecialmenu") {
                        $autoDailerData->state = "declined";
                    } elseif ($partyDnType === "Dialing") {
                        $autoDailerData->state = "no answer";
                    }

                    // Save state and break loop if final state is reached
                    if (in_array($autoDailerData->state, $finalStates)) {
                        $autoDailerData->save();

                        AutoDailerReport::create([
                            'mobile' => $autoDailerData->mobile,
                            'provider' => $autoDailerData->provider_name,
                            'extension' => $autoDailerData->extension,
                            'state' => $autoDailerData->state,
                            'called_at' => now()->addHours(2),
                        ]);
                        return;
                    }
                }
            } else {
                Log::error('3CX Call State Check Failed', [
                    'mobile' => $this->to,
                    'response' => $responseState->body(),
                ]);
                return;
            }

            $retryCount++;
            sleep(2); // Delay before retrying
        }

        // Fallback: Mark as "unknown state" if retries exceeded
        $autoDailerData->state = "unknown";
        $autoDailerData->save();
        Log::warning('3CX Call State Check Timed Out', ['mobile' => $this->to]);
    }
}
