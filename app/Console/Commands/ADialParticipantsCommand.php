<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
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
    protected $signature = 'app:ADial-participants-command';

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
            // Check DB connection first
            if (!$this->checkDatabaseConnection()) {
                Log::error("ADialParticipantsCommand ❌ Database connection test failed");
                return 1;
            }

            $this->processActiveProviders();
            $endTime = Carbon::now();
            $executionTime = $startTime->diffInMilliseconds($endTime);
            Log::info("ADialParticipantsCommand ✅ Execution completed in {$executionTime} ms.");
        } catch (\Exception $e) {
            Log::error("ADialParticipantsCommand ❌ Execution failed: " . $e->getMessage());
            return 1;
        }
    }

    /**
     * Verify database connection is working
     */
    protected function checkDatabaseConnection()
    {
        try {
            DB::connection()->getPdo();
            Log::info("ADialParticipantsCommand ✅ Database connection successful");
            return true;
        } catch (\Exception $e) {
            Log::error("ADialParticipantsCommand ❌ Database connection failed: " . $e->getMessage());
            return false;
        }
    }

    protected function processActiveProviders()
    {
        $timezone = config('app.timezone');
        $now = now()->timezone($timezone);

        // Get providers with active feeds or ongoing calls
        $providers = $this->getActiveProviders($now, $timezone);

        Log::info("ADialParticipantsCommand: 🟢🔍 Total active providers found: " . $providers->count());

        foreach ($providers as $provider) {
            $this->processProviderCalls($provider, $now, $timezone);
        }
    }

    protected function getActiveProviders($now, $timezone)
    {
        $maxRetries = 3;
        $attempts = 0;
        $backoffSeconds = 2;

        while ($attempts < $maxRetries) {
            try {
                return ADialProvider::whereExists(function ($query) use ($now) {
                    $query->select(DB::raw(1))
                        ->from('a_dial_feeds')
                        ->whereColumn('a_dial_providers.id', 'a_dial_feeds.provider_id')
                        ->where('allow', 1)
                        ->where(function ($q) use ($now) {
                            // Same-day call windows
                            $q->where(function ($inner) use ($now) {
                                $inner->whereRaw("STR_TO_DATE(CONCAT(date, ' ', from), '%Y-%m-%d %H:%i:%s') <= ?", [$now])
                                    ->whereRaw("STR_TO_DATE(CONCAT(date, ' ', to), '%Y-%m-%d %H:%i:%s') >= ?", [$now]);
                            })
                            // Overnight call windows
                            ->orWhere(function ($inner) use ($now) {
                                $inner->whereRaw("STR_TO_DATE(CONCAT(date, ' ', from), '%Y-%m-%d %H:%i:%s') <= ?", [$now])
                                    ->whereRaw("TIME(to) < TIME(from)")
                                    ->whereRaw("STR_TO_DATE(CONCAT(DATE_ADD(date, INTERVAL 1 DAY), ' ', to), '%Y-%m-%d %H:%i:%s') >= ?", [$now]);
                            });
                        });
                })->get();
            } catch (\Exception $e) {
                $attempts++;
                Log::warning("ADialParticipantsCommand ⚠️ Connection attempt {$attempts} failed: " . $e->getMessage());

                if ($attempts >= $maxRetries) {
                    Log::error("ADialParticipantsCommand ❌ All connection attempts failed");
                    return collect([]);
                }

                // Wait before retry with exponential backoff
                sleep($backoffSeconds * $attempts);
            }
        }

        return collect([]);
    }

    protected function processProviderCalls($provider, $now, $timezone)
    {
        try {
            UpdateCallStatusJob::dispatch($provider);
            Log::info("ADialParticipantsCommand ✅ Dispatched job for provider {$provider->id}");
        } catch (\Exception $e) {
            Log::error("ADialParticipantsCommand ❌ Failed to dispatch job for provider {$provider->id}: " . $e->getMessage());
        }
    }

    protected function fetchActiveProviderCalls($provider)
    {
        try {
            return $this->threeCxService->getActiveCallsForProvider($provider->extension);
        } catch (\Exception $e) {
            Log::error("ADialParticipantsCommand ❌ Failed to fetch active calls: " . $e->getMessage());
            return ['value' => []];
        }
    }
}
