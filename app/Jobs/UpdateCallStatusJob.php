<?php
namespace App\Jobs;

use App\Services\ThreeCxService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class UpdateCallStatusJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $provider;

    // Job configuration
    public $timeout = 60;
    public $tries = 3;
    public $backoff = [5, 15, 30]; // Exponential backoff between retries
    public $maxExceptions = 3;

    /**
     * Get a unique ID for the job to prevent duplicate processing
     */
    public function uniqueId()
    {
        return 'update_call_status_' . $this->provider->extension;
    }

    /**
     * Create a new job instance.
     */
    public function __construct($provider)
    {
        $this->provider = $provider;
    }

    /**
     * Execute the job.
     */
    public function handle(ThreeCxService $threeCxService)
    {
        try {
            // Fetch active calls for the provider with retry mechanism
            $activeCalls = $threeCxService->getActiveCallsForProvider($this->provider->extension);

            if (empty($activeCalls['value'])) {
                Log::info("No active calls found for provider {$this->provider->extension}");
                return;
            }

            // Use the optimized batch update method
            $result = $threeCxService->batchUpdateCallStatuses($activeCalls['value']);

            Log::info("Call status update for provider {$this->provider->extension}: {$result['status']} - {$result['message']} ({$result['count']} calls)");
        } catch (Throwable $e) {
            Log::error("❌ Failed to process calls for provider {$this->provider->extension}: " . $e->getMessage());

            // If this is the last retry, report the exception
            if ($this->attempts() >= $this->tries) {
                report($e);
            }

            // Re-throw to trigger the job retry mechanism
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(Throwable $exception)
    {
        Log::error("UpdateCallStatusJob failed for provider {$this->provider->extension} after {$this->attempts()} attempts: " . $exception->getMessage());
    }
}
