<?php
namespace App\Jobs;

use App\Models\AutoDailerReport;
use App\Models\ADialData;
use App\Services\ThreeCxService;
use Illuminate\Bus\Queueable;
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

    public function __construct($provider)
    {
        $this->provider = $provider;
    }

    public function handle(ThreeCxService $threeCxService)
    {
        try {
            $activeCalls = $threeCxService->getActiveCallsForProvider($this->provider->extension);

            if (empty($activeCalls['value'])) {
                Log::info("ADialParticipantsCommand: No active calls found for provider {$this->provider->extension}");
                return;
            }

            $this->batchUpdateCallStatuses($activeCalls['value']);
        } catch (\Exception $e) {
            Log::error("ADialParticipantsCommand: Failed to process calls: " . $e->getMessage());
        }
    }

    protected function batchUpdateCallStatuses(array $calls)
    {
        $updateData = [];
        $callIds = [];

        // Fetch all existing records in one query instead of querying per call
        $existingRecords = AutoDailerReport::whereIn('call_id', array_column($calls, 'Id'))
            ->get()
            ->keyBy('call_id');

        foreach ($calls as $call) {
            $callId = $call['Id'] ?? null;
            $callStatus = $call['Status'] ?? null;

            if (!$callId || !$callStatus) {
                continue; // Skip incomplete data
            }

            $callIds[] = $callId;
            $updateData[] = $this->prepareCallUpdateData($call, $existingRecords[$callId] ?? null);
        }

        DB::beginTransaction();
        try {
            if (!empty($updateData)) {
                $this->batchUpdateReports($updateData);
                $this->batchUpdateDialData($callIds, $updateData);
            }
            DB::commit();
            Log::info("ADialParticipantsCommand: ✅ Updated " . count($callIds) . " calls");
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("ADialParticipantsCommand: ❌ Batch update failed: " . $e->getMessage());
        }
    }

    protected function prepareCallUpdateData(array $call, $existingRecord)
    {
        $callId = $call['Id'];
        $status = $call['Status'];
        $duration = null;
        $routingDuration = null;

        if (isset($call['EstablishedAt'], $call['ServerNow'])) {
            $establishedAt = Carbon::createFromFormat('Y-m-d\TH:i:s.uP', $call['EstablishedAt']);
            $serverNow = Carbon::createFromFormat('Y-m-d\TH:i:s.uP', $call['ServerNow']);
            $currentDuration = $establishedAt->diff($serverNow)->format('%H:%I:%S');

            if ($existingRecord) {
                $duration = $existingRecord->duration_time;
                $routingDuration = $existingRecord->duration_routing;
            }

            if ($status === 'Talking') {
                $duration = $currentDuration;
            } elseif ($status === 'Routing') {
                $routingDuration = $currentDuration;
            }
        }

        return [
            'call_id' => $callId,
            'status' => $status,
            'duration_time' => $duration,
            'duration_routing' => $routingDuration,
        ];
    }

    protected function batchUpdateReports(array $updateData)
    {
        $updateChunks = array_chunk($updateData, 500); // Process in chunks to avoid overload

        foreach ($updateChunks as $chunk) {
            DB::table('auto_dailer_reports')->upsert($chunk, ['call_id'], ['status', 'duration_time', 'duration_routing']);
        }
    }

    protected function batchUpdateDialData(array $callIds, array $updateData)
    {
        $updates = collect($updateData)->keyBy('call_id');

        $updateChunks = array_chunk($callIds, 500);
        foreach ($updateChunks as $chunk) {
            DB::table('a_dial_data')
                ->whereIn('call_id', $chunk)
                ->update([
                    'state' => DB::raw("CASE call_id " .
                        $updates->map(fn ($item, $callId) => "WHEN '{$callId}' THEN '{$item['status']}'")->implode(' ') .
                        " END"),
                ]);
        }
    }
}
