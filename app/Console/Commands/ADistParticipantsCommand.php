<?php

namespace App\Console\Commands;

use App\Models\ADistAgent;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Models\ADistData;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use App\Models\AutoDistributerReport;
use DateTime;
use App\Services\TokenService;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;


class ADistParticipantsCommand extends Command
{
    protected $signature = 'app:ADist-participants-command';
    protected $description = 'Fetch and process participants data from 3CX API';
    protected $tokenService;

    public function __construct(TokenService $tokenService)
    {
        parent::__construct();
        $this->tokenService = $tokenService;
    }

    public function handle()
    {
        Log::info('ADistParticipantsCommand executed at ' . Carbon::now());



        try {
            $client = new Client([
                'base_uri' => config('services.three_cx.api_url'),
                'headers' => [
                    'Accept' => 'application/json'
                ],
            ]);

            $token = $this->tokenService->getToken();

            // Fetch Active Calls
            try {
                $response = $client->get('/xapi/v1/ActiveCalls', [
                    'headers' => ['Authorization' => "Bearer $token"]
                ]);

                $activeCalls = json_decode($response->getBody(), true);
            } catch (RequestException $e) {
                Log::error("ADistParticipantsCommand ❌ Failed to fetch active calls: " . $e->getMessage());
                return;
            }

            if (empty($activeCalls['value'])) {
                Log::info("ADistParticipantsCommand ℹ️ No active calls at the moment.");
                return;
            }

            Log::info("ADistParticipantsCommand Active Calls Retrieved: " . print_r($activeCalls, true));

            foreach ($activeCalls['value'] as $call) {
                $status = $call['Status'];
                $callId = $call['Id'];

                // Initialize variables
                $routing = null;
                $talking = null;

                // Get existing report to preserve previous values
                $existingReport = AutoDistributerReport::where('call_id', $callId)->first();

                // Calculate times based on status
                $establishedAt = new DateTime($call['EstablishedAt']);
                $serverNow = new DateTime($call['ServerNow']);

                if ($status === "Routing") {
                    $routing = $establishedAt->diff($serverNow)->format('%H:%I:%S');
                    // Preserve existing talking time if any
                    $talking = $existingReport ? $existingReport->duration_time : null;
                }

                if ($status === "Talking") {
                    $talking = $establishedAt->diff($serverNow)->format('%H:%I:%S');
                    // Preserve the last routing time
                    $routing = $existingReport ? $existingReport->duration_routing : null;
                }

                // Transaction to update database
                DB::beginTransaction();
                try {
                    // Update report with new values while preserving existing ones
                    $updateData = [
                        'status' => $status
                    ];

                    // Only update times if they are not null
                    if ($routing !== null) {
                        $updateData['duration_routing'] = $routing;
                    }
                    if ($talking !== null) {
                        $updateData['duration_time'] = $talking;
                    }

                    AutoDistributerReport::where('call_id', $callId)
                        ->update($updateData);

                    ADistData::where('call_id', $callId)
                        ->update(['state' => $status]);

                    Log::info("ADistParticipantsCommand ✅ Call ID: {$callId}, Status: {$status}, Routing: {$routing}, Talking: {$talking}");

                    DB::commit();
                } catch (\Exception $e) {
                    DB::rollBack();
                    Log::error("ADistParticipantsCommand ❌ Transaction Failed for call ID {$callId}: " . $e->getMessage());
                }
            }
        } catch (\Exception $e) {
            Log::error("ADistParticipantsCommand ❌ General error in fetching active calls: " . $e->getMessage());
        }
        Log::info("ADistParticipantsCommand ✅ Auto Dialer command execution completed.");
    }
}
