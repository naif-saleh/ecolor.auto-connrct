<?php

namespace App\Jobs;

use App\Models\TemporaryCall;
use App\Services\ThreeCxService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class UpdateCallStatusJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $calls;

    public function __construct(array $calls)
    {
        $this->calls = $calls;
    }

    public function handle(ThreeCxService $threeCxService)
    {
        $startTime = microtime(true);

        $callRecords = [];
        foreach ($this->calls as $call) {
            $callId = $call['Id'] ?? null;
            $status = $call['Status'] ?? 'Unknown';

            if (!$callId) {
                Log::warning("⚠️ Missing Call ID in call data.");
                continue;
            }

            // Store temporary record to track processing
            TemporaryCall::updateOrCreate(
                ['call_id' => $callId],
                [
                    'call_data' => json_encode($call),
                    'status' => $status,
                ]
            );

            $callRecords[] = $call;
        }

        // Batch update calls in ThreeCxService
        $threeCxService->updateCallRecordsBatch($callRecords);

        // Mark calls as processed
        TemporaryCall::whereIn('call_id', array_column($callRecords, 'Id'))->update(['status' => 'processed']);

        $executionTime = round((microtime(true) - $startTime) * 1000, 2);
        Log::info("✅ Processed " . count($this->calls) . " calls in {$executionTime} ms.");
    }
}
