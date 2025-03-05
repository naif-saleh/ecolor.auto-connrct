<?php
namespace App\Jobs;

use App\Models\AutoDailerReport;
use App\Models\ADialData;
use App\Services\ThreeCxService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class UpdateCallStatusJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $provider;

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
        $providerStartTime = Carbon::now();

        try {
            // Fetch active calls for the provider
            $activeCalls = $threeCxService->getActiveCallsForProvider($this->provider->extension);

            if (empty($activeCalls['value'])) {
                Log::info("ADialParticipantsCommand ⚠️ No active calls found for provider {$this->provider->extension}");
                return;
            }

            // Process the calls
            $this->batchUpdateCallStatuses($activeCalls['value']);

            $providerEndTime = Carbon::now();
            $executionTime = $providerStartTime->diffInMilliseconds($providerEndTime);
            Log::info("ADialParticipantsCommand ⏳ Execution time for provider {$this->provider->extension}: {$executionTime} ms");
        } catch (\Exception $e) {
            Log::error("ADialParticipantsCommand ❌ Failed to process calls for provider {$this->provider->extension}: " . $e->getMessage());
        }
    }

    protected function batchUpdateCallStatuses(array $calls)
    {
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

        DB::beginTransaction();
        try {
            $this->batchUpdateReports($updateData);
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

        $existingRecord = AutoDailerReport::where('call_id', $callId)->first();

        if (isset($call['EstablishedAt'], $call['ServerNow'])) {
            $establishedAt = Carbon::parse($call['EstablishedAt']);
            $serverNow = Carbon::parse($call['ServerNow']);
            $currentDuration = $establishedAt->diff($serverNow)->format('%H:%I:%S');

            if ($existingRecord) {
                $duration = $existingRecord->duration_time;
                $routingDuration = $existingRecord->duration_routing;
            }

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
                    $updates->map(fn($item, $callId) => "WHEN '{$callId}' THEN '{$item['status']}'")->implode(' ') .
                    " END"),
                'duration_time' => DB::raw("CASE call_id " .
                    $updates->map(fn($item, $callId) => "WHEN '{$callId}' THEN " . ($item['duration_time'] ? "'{$item['duration_time']}'" : 'duration_time'))->implode(' ') .
                    " END"),
                'duration_routing' => DB::raw("CASE call_id " .
                    $updates->map(fn($item, $callId) => "WHEN '{$callId}' THEN " . ($item['duration_routing'] ? "'{$item['duration_routing']}'" : 'duration_routing'))->implode(' ') .
                    " END")
            ]);
    }

    protected function batchUpdateDialData(array $callIds, array $updateData)
    {
        $updates = collect($updateData)->keyBy('call_id');

        ADialData::whereIn('call_id', $callIds)
            ->update([
                'state' => DB::raw("CASE call_id " .
                    $updates->map(fn($item, $callId) => "WHEN '{$callId}' THEN '{$item['status']}'")->implode(' ') .
                    " END")
            ]);
    }
}
