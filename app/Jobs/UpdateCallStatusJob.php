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

    protected $call;
    protected $provider;
    protected $extension;
    protected $phoneNumber;

    /**
     * Create a new job instance.
     *
     * @param array $call
     * @param string|null $provider
     * @param string|null $extension
     * @param string|null $phoneNumber
     */
    public function __construct(array $call, $provider)
    {
        $this->call = $call;
        $this->provider = $provider;

    }

    /**
     * Execute the job.
     *
     * @return void
     */
    // public function handle(ThreeCxService $threeCxService)
    // {
    //     $callStartTime = Carbon::now();

    //     $callId = $this->call['Id'] ?? null;
    //     $status = $this->call['Status'] ?? 'Unknown';

    //     if (!$callId) {
    //         Log::warning("UpdateCallStatusJob⚠️ Missing Call ID in response");
    //         return;
    //     }

    //     $temporaryCall = TemporaryCall::updateOrCreate(
    //         ['call_id' => $callId],
    //         [
    //             'provider' => $this->provider,
    //             'extension' => $this->extension,
    //             'phone_number' => $this->phoneNumber,
    //             'call_data' => json_encode($this->call),
    //             'status' => 'pending',
    //         ]
    //     );

    //     try {
    //         // Update call record with full call data for duration calculation
    //         $threeCxService->updateCallRecord(
    //             $callId,
    //             $status,
    //             $this->call,
    //             $this->provider,
    //             $this->extension,
    //             $this->phoneNumber
    //         );

    //         $temporaryCall->update(['status' => 'processed']);

    //         Log::info("UpdateCallStatusJob✅ Updated Call: {$callId}, Status: {$status}, mobile: {$this->phoneNumber}");
    //     } catch (\Exception $e) {
    //         Log::error("UpdateCallStatusJob❌ Failed to update database for Call ID {$callId}: " . $e->getMessage());
    //         $temporaryCall->update(['status' => 'failed']);
    //     }

    //     $callEndTime = Carbon::now();
    //     $callExecutionTime = $callStartTime->diffInMilliseconds($callEndTime);
    //     Log::info("UpdateCallStatusJob⏳ Execution time for call {$callId}: {$callExecutionTime} ms");
    // }

    public function handle(ThreeCxService $threeCxService)
    {
        $callStartTime = microtime(true);
        $batchData = [];

        foreach ($this->call as $calls) {
            $callId = $calls['Id'] ?? null;
            $status = $calls['Status'] ?? 'Unknown';
            $phoneNumber = $calls['Caller'] ?? 'missing';
             
            if (!$callId) {
                Log::warning("⚠️ Missing Call ID in response");
                continue;
            }

            $batchData[] = [
                'call_id' => $callId,
                'status' => $status,
                'provider' => $this->provider['name'],
                'extension' => $this->provider['extension'],
                'phone_number' => $phoneNumber,

            ];
        }

        if (!empty($batchData)) {
            $threeCxService->updateCallRecords($batchData);
        }

        $executionTime = round((microtime(true) - $callStartTime) * 1000, 2);
        Log::info("⏳ Batch Execution time: {$executionTime} ms for " . count($batchData) . " calls.");
    }
}
