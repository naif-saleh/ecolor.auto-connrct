<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use App\Services\ThreeCxService;
use App\Models\AutoDailerReport;
use App\Models\ADialData;
use Carbon\Carbon;
use DateTime;
use Illuminate\Support\Facades\DB;

class ADistParticipantsCommand extends Command
{
    protected $signature = 'app:ADist-participants-command';
    protected $description = 'Fetch and process participants data from 3CX API';
    protected $threeCxService;

    public function __construct(ThreeCxService $threeCxService)
    {
        parent::__construct();
        $this->threeCxService = $threeCxService;
    }

    public function handle()
    {
        Log::info('ADistParticipantsCommand executed at ' . Carbon::now());

        try {
            // Get all active calls using the ThreeCxService
            $activeCalls = $this->threeCxService->getAllActiveCalls();

            if (empty($activeCalls['value'])) {
                Log::info("ADistParticipantsCommand ℹ️ No active calls at the moment.");
                return;
            }

            Log::info("ADistParticipantsCommand Active Calls Retrieved: " . print_r($activeCalls, true));

            // Process Active Calls
            $this->processActiveCalls($activeCalls);
        } catch (\Exception $e) {
            Log::error("ADistParticipantsCommand ❌ Error: " . $e->getMessage());
        }

        Log::info("ADistParticipantsCommand ✅ Auto Dialer command execution completed.");
    }

    // Process Active Calls
    protected function processActiveCalls($activeCalls)
    {
        foreach ($activeCalls['value'] as $call) {
            $status = $call['Status'];
            $callId = $call['Id'];

            $durationTime = null;
            $durationRouting = null;

            // Calculate the duration based on status
            if ($status === 'Talking') {
                $establishedAt = new DateTime($call['EstablishedAt']);
                $serverNow = new DateTime($call['ServerNow']);
                $durationTime = $establishedAt->diff($serverNow)->format('%H:%I:%S');
            }

            if ($status === 'Routing') {
                $establishedAt = new DateTime($call['EstablishedAt']);
                $serverNow = new DateTime($call['ServerNow']);
                $durationRouting = $establishedAt->diff($serverNow)->format('%H:%I:%S');
            }

            // Use the ThreeCxService to update the call record in the database
            $this->threeCxService->updateCallRecord($callId, $status, $call);
        }
    }
}
