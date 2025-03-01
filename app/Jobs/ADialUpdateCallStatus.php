<?php

namespace App\Jobs;

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
    public function __construct(array $call, $provider = null, $extension = null, $phoneNumber = null)
    {
        $this->call = $call;
        $this->provider = $provider;
        $this->extension = $extension;
        $this->phoneNumber = $phoneNumber;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(ThreeCxService $threeCxService)
    {
        $callStartTime = Carbon::now();

        $callId = $this->call['Id'] ?? null;
        $status = $this->call['Status'] ?? 'Unknown';

        if (!$callId) {
            Log::warning("UpdateCallStatusJob⚠️ Missing Call ID in response");
            return;
        }

        try {
            // Update call record with full call data for duration calculation
            $threeCxService->updateCallRecord(
                $callId,
                $status,
                $this->call,
                $this->provider,
                $this->extension,
                $this->phoneNumber
            );

            Log::info("UpdateCallStatusJob✅ Updated Call: {$callId}, Status: {$status}");
        } catch (\Exception $e) {
            Log::error("UpdateCallStatusJob❌ Failed to update database for Call ID {$callId}: " . $e->getMessage());
        }

        $callEndTime = Carbon::now();
        $callExecutionTime = $callStartTime->diffInMilliseconds($callEndTime);
        Log::info("UpdateCallStatusJob⏳ Execution time for call {$callId}: {$callExecutionTime} ms");
    }
}
