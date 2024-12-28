<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;


class ProcessParticipants implements ShouldQueue
{
    use Queueable;
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
    public function handle(): void
    {
        Log::warning("ProcessParticipants Started");
        $from = $this->record->extension;
        $to = $this->record->mobile;
        
        $callState = 'unknown';


        $responseData = $responseState->json();


        $callState = 'unknown';
        foreach ($responseData as $participant) {
            if (isset($participant['party_dn_type'])) {
                $callState = match ($participant['party_dn_type']) {
                    "Wextension" => 'answered',
                    "Wspecialmenu" => 'no answer',
                    default => 'ringing',
                };

                if (in_array($callState, ['answered', 'no answer'])) {
                    break 2;
                }
            }
        }
    }
}
