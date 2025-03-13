<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Bus;
use Carbon\Carbon;
use App\Models\ADialProvider;
use App\Services\ThreeCxService;
use App\Jobs\UpdateCallStatusJob;

class ADialParticipantsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:ADial-participants-command {--providers=all : Specific provider IDs or "all"} {--batch=10 : Number of jobs to process in parallel}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update call statuses for active participants';

    protected $threeCxService;

    public function __construct(ThreeCxService $threeCxService)
    {
        parent::__construct();
        $this->threeCxService = $threeCxService;
    }

    public function handle()
    {
        $startTime = Carbon::now();
        Log::info('✅ 📡 ADialParticipantsCommand started at ' . $startTime);

        try {
            $providersOption = $this->option('providers');
            $batchSize = (int)$this->option('batch');

            // Get active providers
            $providers = $this->getActiveProviders($providersOption);

            $count = $providers->count();
            if ($count === 0) {
                $this->info("No active providers found");
                Log::info("ADialParticipantsCommand: No active providers found");
                return 0;
            }

            $this->info("Processing {$count} active providers");
            Log::info("ADialParticipantsCommand: 🟢🔍 Total active providers found: {$count}");

            // Process providers in batches for better performance
            $providerBatches = $providers->chunk($batchSize);

            foreach ($providerBatches as $index => $batch) {
                $this->dispatchBatchJobs($batch);
                $this->info("Dispatched batch " . ($index + 1) . " of " . $providerBatches->count());
            }

            $endTime = Carbon::now();
            $executionTime = $startTime->diffInMilliseconds($endTime);
            $this->info("Command completed in {$executionTime}ms");
            Log::info("ADialParticipantsCommand ✅ Job dispatching completed in {$executionTime}ms.");

            return 0;
        } catch (\Exception $e) {
            $this->error("Command failed: " . $e->getMessage());
            Log::error("ADialParticipantsCommand ❌ Execution failed: " . $e->getMessage());

            return 1;
        }
    }

    /**
     * Get active providers based on command options
     */
    protected function getActiveProviders($providersOption)
    {
        $timezone = config('app.timezone');
        $now = now()->timezone($timezone);

        // Get specific providers or all active ones
        if ($providersOption !== 'all') {
            $providerIds = explode(',', $providersOption);

            $query = ADialProvider::whereIn('id', $providerIds);

            // Show status message
            $this->info("Fetching specific providers: " . implode(', ', $providerIds));
        } else {
            // Get providers with active feeds or ongoing calls
            $query = ADialProvider::where(function ($query) use ($now, $timezone) {
                // Providers with files in an active call window
                $query->whereHas('files', function ($subQuery) use ($now, $timezone) {
                    $subQuery->where('allow', true)
                        ->where(function ($timeQuery) use ($now, $timezone) {
                            $timeQuery->where(function ($q) use ($now, $timezone) {
                                // Handle same-day call windows
                                $q->whereRaw("STR_TO_DATE(CONCAT(date, ' ', `from`), '%Y-%m-%d %H:%i:%s') <= ?", [$now])
                                    ->whereRaw("STR_TO_DATE(CONCAT(date, ' ', `to`), '%Y-%m-%d %H:%i:%s') >= ?", [$now]);
                            })
                                ->orWhere(function ($q) use ($now, $timezone) {
                                    // Handle overnight call windows
                                    $q->whereRaw("STR_TO_DATE(CONCAT(date, ' ', `from`), '%Y-%m-%d %H:%i:%s') <= ?", [$now])
                                        ->whereRaw("TIME(`to`) < TIME(`from`)")
                                        ->whereRaw("STR_TO_DATE(CONCAT(DATE_ADD(date, INTERVAL 1 DAY), ' ', `to`), '%Y-%m-%d %H:%i:%s') >= ?", [$now]);
                                });
                        });
                });
            });

            $this->info("Fetching all active providers");
        }

        // Add logging for query performance analysis (optional)
        $queryStart = microtime(true);
        $providers = $query->get();
        $queryTime = (microtime(true) - $queryStart) * 1000;

        Log::info("ADialParticipantsCommand: Provider query completed in {$queryTime}ms");

        return $providers;
    }

    /**
     * Dispatch jobs for a batch of providers
     */
    protected function dispatchBatchJobs($providers)
    {
        $jobs = [];

        foreach ($providers as $provider) {
            $jobs[] = new UpdateCallStatusJob($provider);
        }

        // Dispatch jobs as a batch to monitor progress
        Bus::batch($jobs)
            ->allowFailures()
            ->onQueue('call-updates')
            ->dispatch();
    }
}
