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
        $providerStartTime = Carbon::now();

        try {
            $activeCalls = $threeCxService->getActiveCallsForProvider($this->provider->extension);

            if (empty($activeCalls['value'])) {
                Log::info("ADialParticipantsCommand ⚠️ No active calls found for provider {$this->provider->extension}");
                return;
            }

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

        foreach ($calls as $call) {
            $callId = $call['Id'] ?? null;
            $callStatus = $call['Status'] ?? null;

            if (!$callId || !$callStatus) {
                Log::warning("ADialParticipantsCommand ⚠️ Incomplete call data: " . json_encode($call));
                continue;
            }

            $updateData[$callId] = $this->prepareCallUpdateData($call);
        }

        if (!empty($updateData)) {
            // Break the updates into smaller chunks
            $chunks = array_chunk($updateData, 50, true);

            foreach ($chunks as $chunk) {
                DB::transaction(function () use ($chunk) {
                    $this->batchUpdateReports($chunk);
                    $this->batchUpdateDialData($chunk);
                });
            }
        }

        Log::info("ADialParticipantsCommand ✅ Batch updated " . count($updateData) . " call records");
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
        $casesStatus = "";
        $casesDuration = "";
        $casesRouting = "";
        $ids = [];

        foreach ($updateData as $callId => $data) {
            AutoDailerReport::where('call_id', $callId)->update([
                'status' => $data['status'],
                'duration_time' => $data['duration_time'] ?? DB::raw('duration_time'),
                'duration_routing' => $data['duration_routing'] ?? DB::raw('duration_routing'),
            ]);
        }

        if (!empty($ids)) {
            DB::update("
                UPDATE auto_dailer_reports
                SET
                    status = CASE call_id {$casesStatus} END,
                    duration_time = CASE call_id {$casesDuration} END,
                    duration_routing = CASE call_id {$casesRouting} END
                WHERE call_id IN (" . implode(',', $ids) . ")
            ");
        }
    }

    protected function batchUpdateDialData(array $updateData)
    {
        $casesState = "";
        $ids = [];
        foreach ($updateData as $callId => $data) {
            ADialData::where('call_id', $callId)->update([
                'state' => $data['status']
            ]);
        }

        if (!empty($ids)) {
            DB::update("
                UPDATE a_dial_data
                SET state = CASE call_id {$casesState} END
                WHERE call_id IN (" . implode(',', $ids) . ")
            ");
        }
    }
}
