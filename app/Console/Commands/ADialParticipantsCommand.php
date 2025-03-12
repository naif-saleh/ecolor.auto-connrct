<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\ADialProvider;
use App\Models\ADialData;
use App\Models\ADialFeed;
use App\Services\ThreeCxService;
use App\Models\AutoDailerReport;
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
            $this->processActiveProviders();
            // $endTime = Carbon::now();
            // $executionTime = $startTime->diffInMilliseconds($endTime);
            // Log::info("ADialParticipantsCommand ✅ Execution completed in {$executionTime} ms.");
        } catch (\Exception $e) {
            Log::error("ADialParticipantsCommand ❌ Execution failed: " . $e->getMessage());
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
        return ADialProvider::where(function ($query) use ($now, $timezone) {
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
        })->get();

    }

    protected function processProviderCalls($provider, $now, $timezone)
    {
        UpdateCallStatusJob::dispatch($provider);
    }

    protected function fetchActiveProviderCalls($provider)
    {
        try {
            // return $this->threeCxService->getActiveCallsForProvider($provider->extension);
            return $this->threeCxService->getAllActiveCalls();
        } catch (\Exception $e) {
            Log::error("ADialParticipantsCommand ❌ Failed to fetch active calls: " . $e->getMessage());
            return ['value' => []];
        }
    }


}
