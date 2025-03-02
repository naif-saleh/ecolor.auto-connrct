<?php

namespace App\Services;

use App\Models\ADialData;
use App\Models\AutoDailerReport;
use Carbon\Carbon;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ThreeCxService
{
    protected $client;
    protected $tokenService;
    protected $apiUrl;

    public function __construct(TokenService $tokenService)
    {
        $this->tokenService = $tokenService;
        $this->client = new Client();
        $this->apiUrl = config('services.three_cx.api_url');
    }

    /**
     * Get a fresh token for API requests (using your existing TokenService)
     */
    public function getToken()
    {
        try {
            $token = $this->tokenService->getToken();
            if (!$token) {
                throw new \Exception("Failed to retrieve a valid token");
            }
            return $token;
        } catch (\Exception $e) {
            Log::error("❌ Failed to retrieve token: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get all active calls for a provider
     */
    public function getActiveCallsForProvider($providerExtension)
    {
        try {
            $token = $this->getToken();
            $filter = "contains(Caller, '{$providerExtension}')";
            $url = $this->apiUrl . "/xapi/v1/ActiveCalls?\$filter=" . urlencode($filter);

            $response = $this->client->get($url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Accept' => 'application/json',
                ],
                'timeout' => 30,
            ]);

            if ($response->getStatusCode() !== 200) {
                throw new \Exception("Failed to fetch active calls. HTTP Status: " . $response->getStatusCode());
            }

            return json_decode($response->getBody()->getContents(), true);
        } catch (\Exception $e) {
            Log::error("❌ Failed to fetch active calls for provider {$providerExtension}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get all active calls in the system
     */
    public function getAllActiveCalls()
    {
        try {
            $token = $this->getToken();
            $url = $this->apiUrl . "/xapi/v1/ActiveCalls";

            $response = $this->client->get($url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Accept' => 'application/json',
                ],
                'timeout' => 30,
            ]);

            if ($response->getStatusCode() !== 200) {
                throw new \Exception("Failed to fetch active calls. HTTP Status: " . $response->getStatusCode());
            }

            return json_decode($response->getBody()->getContents(), true);
        } catch (\Exception $e) {
            Log::error("❌ Failed to fetch all active calls: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Make a call using 3CX API
     */
    public function makeCall($providerExtension, $destination)
    {
        try {
            $token = $this->getToken();
            $url = $this->apiUrl . "/callcontrol/{$providerExtension}/makecall";

            $response = $this->client->post($url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json'
                ],
                'json' => ['destination' => $destination],
                'timeout' => 10,
            ]);

            $responseData = json_decode($response->getBody()->getContents(), true);

            if (!isset($responseData['result']['callid'])) {
                throw new \Exception("Missing call ID in response");
            }

            return $responseData;
        } catch (\Exception $e) {
            Log::error("❌ Make call failed: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Update call record in database with consistent format
     */
    // public function updateCallRecord($callId, $status, $call = null, $provider = null, $extension = null, $phoneNumber = null)
    // {
    //     $updateData = ['status' => $status];

    //     // Calculate durations if call data is provided
    //     if ($call && isset($call['EstablishedAt'], $call['ServerNow'])) {
    //         $establishedAt = Carbon::parse($call['EstablishedAt']);
    //         $serverNow = Carbon::parse($call['ServerNow']);
    //         $currentDuration = $establishedAt->diff($serverNow)->format('%H:%I:%S');

    //         if ($status === 'Talking') {
    //             $updateData['duration_time'] = $currentDuration;
    //         } elseif ($status === 'Routing') {
    //             $updateData['duration_routing'] = $currentDuration;
    //         }
    //     } else {
    //         // If updating without call data, preserve existing durations
    //         $existingRecord = AutoDailerReport::where('call_id', $callId)->first(['duration_time', 'duration_routing']);

    //         if ($existingRecord) {
    //             if ($status === 'Talking' && isset($existingRecord->duration_time)) {
    //                 $updateData['duration_time'] = $existingRecord->duration_time;
    //             } elseif ($status === 'Routing' && isset($existingRecord->duration_routing)) {
    //                 $updateData['duration_routing'] = $existingRecord->duration_routing;
    //             }
    //         }
    //     }

    //     // Add provider info if available
    //     if ($provider) $updateData['provider'] = $provider;
    //     if ($extension) $updateData['extension'] = $extension;
    //     if ($phoneNumber) $updateData['phone_number'] = $phoneNumber;

    //     // Use updateOrCreate to handle both new and existing calls
    //     try {
    //         DB::beginTransaction();

    //         $report = AutoDailerReport::where('call_id', $callId)->update(
    //             $updateData
    //         );

    //         // Also update the data table for consistency
    //         $updated = ADialData::where('call_id', $callId)
    //             ->update(['state' => $status]);

    //         DB::commit();

    //         return $report;
    //     } catch (\Exception $e) {
    //         DB::rollBack();
    //         Log::error("❌ Failed to update database for Call ID {$callId}: " . $e->getMessage());
    //         throw $e;
    //     }
    // }

    public function updateCallRecordsBatch($calls)
    {
        if (empty($calls)) {
            return;
        }

        $updateData = [];
        $updateADialData = [];

        foreach ($calls as $call) {
            $callId = $call['Id'] ?? null;
            $status = $call['Status'] ?? 'Unknown';

            if (!$callId) {
                continue;
            }

            $establishedAt = isset($call['EstablishedAt']) ? Carbon::parse($call['EstablishedAt']) : null;
            $serverNow = isset($call['ServerNow']) ? Carbon::parse($call['ServerNow']) : Carbon::now();
            $currentDuration = $establishedAt ? $establishedAt->diff($serverNow)->format('%H:%I:%S') : null;

            $updateRecord = [
                'call_id' => $callId,
                'status' => $status,
                'phone_number' => $call['Caller'] ?? null,
                'provider' => $call['Callee'] ?? null,
                'extension' => null,
                'duration_time' => ($status === 'Talking' && $currentDuration) ? $currentDuration : null,
                'duration_routing' => ($status === 'Routing' && $currentDuration) ? $currentDuration : null,
            ];

            $updateData[] = $updateRecord;
            $updateADialData[] = ['call_id' => $callId, 'state' => $status];
        }

        try {
            DB::beginTransaction();

            // Batch update AutoDailerReport
            AutoDailerReport::upsert($updateData, ['call_id'], ['status', 'phone_number', 'provider', 'extension', 'duration_time', 'duration_routing']);

            // Batch update ADialData
            ADialData::upsert($updateADialData, ['call_id'], ['state']);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("❌ Batch update failed: " . $e->getMessage());
        }
    }
}
