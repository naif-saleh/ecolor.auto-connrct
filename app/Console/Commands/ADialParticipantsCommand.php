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
        Log::info('✅ ADialParticipantsCommand started at ' . $startTime);

        try {
            $this->processActiveProviders();

            $endTime = Carbon::now();
            $executionTime = $startTime->diffInMilliseconds($endTime);
            Log::info("ADialParticipantsCommand ✅ Execution completed in {$executionTime} ms.");
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

        Log::info("ADialParticipantsCommand: Total active providers found: " . $providers->count());

        foreach ($providers as $provider) {
            $this->processProviderCalls($provider, $now, $timezone);
        }
    }

    protected function getActiveProviders($now, $timezone)
    {
        return ADialProvider::where(function ($query) use ($now, $timezone) {
            // Providers with files in active call window
            $query->whereHas('files', function ($subQuery) use ($now, $timezone) {
                $subQuery->whereDate('date', today())
                    ->where('allow', true)
                    ->where(function ($timeQuery) use ($now, $timezone) {
                        $timeQuery->where(function ($q) use ($now, $timezone) {
                            $q->whereRaw("STR_TO_DATE(CONCAT(date, ' ', `from`), '%Y-%m-%d %H:%i:%s') <= ?", [$now])
                                ->whereRaw("STR_TO_DATE(CONCAT(date, ' ', `to`), '%Y-%m-%d %H:%i:%s') >= ?", [$now]);
                        });
                    });
            });
        })->get();
    }

    protected function processProviderCalls($provider, $now, $timezone)
    {
        $providerStartTime = Carbon::now();

        try {
            // Fetch active calls for this provider
            $activeCalls = $this->fetchActiveProviderCalls($provider);

            if (empty($activeCalls['value'])) {
                Log::info("ADialParticipantsCommand ⚠️ No active calls found for provider {$provider->extension}");
                return;
            }

            // Batch process call updates
            $this->batchUpdateCallStatuses($activeCalls['value']);

            $providerEndTime = Carbon::now();
            $providerExecutionTime = $providerStartTime->diffInMilliseconds($providerEndTime);
            Log::info("ADialParticipantsCommand ⏳ Execution time for provider {$provider->extension}: {$providerExecutionTime} ms");
        } catch (\Exception $e) {
            Log::error("ADialParticipantsCommand ❌ Failed to process calls for provider {$provider->extension}: " . $e->getMessage());
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

    protected function batchUpdateCallStatuses(array $calls)
    {
        // Prepare data for batch update
        $updateData = [];
        $callIds = [];

        foreach ($calls as $call) {
            $callId = $call['Id'] ?? null;
            $callStatus = $call['Status'] ?? null;

            if (!$callId || !$callStatus) {
                Log::warning("ADialParticipantsCommand ⚠️ Incomplete call data: " . json_encode($call));
                continue;
            }

            $callIds[] = $callId;
            $updateData[] = $this->prepareCallUpdateData($call);
        }

        // Perform batch updates within a transaction
        DB::beginTransaction();
        try {
            // Batch update AutoDailerReports
            $this->batchUpdateReports($updateData);

            // Batch update ADialData
            $this->batchUpdateDialData($callIds, $updateData);

            DB::commit();

            Log::info("ADialParticipantsCommand ✅ Batch updated " . count($callIds) . " call records");
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("ADialParticipantsCommand ❌ Batch update failed: " . $e->getMessage());
        }
    }

    protected function prepareCallUpdateData(array $call): array
    {
        $callId = $call['Id'];
        $status = $call['Status'];
        $duration = null;
        $routingDuration = null;

        // Retrieve existing record to preserve previous durations
        $existingRecord = AutoDailerReport::where('call_id', $callId)->first();

        // Calculate durations if possible
        if (isset($call['EstablishedAt'], $call['ServerNow'])) {
            $establishedAt = Carbon::parse($call['EstablishedAt']);
            $serverNow = Carbon::parse($call['ServerNow']);
            $currentDuration = $establishedAt->diff($serverNow)->format('%H:%I:%S');

            // Preserve existing durations and update based on current status
            if ($existingRecord) {
                $duration = $existingRecord->duration_time;
                $routingDuration = $existingRecord->duration_routing;
            }

            // Update durations based on current status
            switch ($status) {
                case 'Talking':
                    $duration = $currentDuration;
                    break;
                case 'Routing':
                    $routingDuration = $currentDuration;
                    break;
            }
        }

        return [
            'call_id' => $callId,
            'status' => $status,
            'duration_time' => $duration,
            'duration_routing' => $routingDuration
        ];
    }

    protected function batchUpdateReports(array $updateData)
    {
        $updates = collect($updateData)->keyBy('call_id');

        AutoDailerReport::whereIn('call_id', $updates->keys())
            ->update([
                'status' => DB::raw("CASE call_id " .
                    $updates->map(function ($item, $callId) {
                        return "WHEN '{$callId}' THEN '{$item['status']}'";
                    })->implode(' ') .
                    " END"),
                'duration_time' => DB::raw("CASE call_id " .
                    $updates->map(function ($item, $callId) {
                        return "WHEN '{$callId}' THEN " .
                            ($item['duration_time'] ? "'{$item['duration_time']}'" : 'duration_time');
                    })->implode(' ') .
                    " END"),
                'duration_routing' => DB::raw("CASE call_id " .
                    $updates->map(function ($item, $callId) {
                        return "WHEN '{$callId}' THEN " .
                            ($item['duration_routing'] ? "'{$item['duration_routing']}'" : 'duration_routing');
                    })->implode(' ') .
                    " END")
            ]);
    }

    protected function batchUpdateDialData(array $callIds, array $updateData)
    {
        $updates = collect($updateData)->keyBy('call_id');

        ADialData::whereIn('call_id', $callIds)
            ->update([
                'state' => DB::raw("CASE call_id " .
                    $updates->map(function ($item, $callId) {
                        return "WHEN '{$callId}' THEN '{$item['status']}'";
                    })->implode(' ') .
                    " END")
            ]);
    }
}
