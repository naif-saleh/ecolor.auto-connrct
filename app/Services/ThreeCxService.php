<?php

namespace App\Services;

use App\Models\ADialData;
use App\Models\AutoDailerReport;
use Carbon\Carbon;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Cache;
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
        $this->client = new Client;
        $this->apiUrl = config('services.three_cx.api_url');
    }

    /**
     * Get a fresh token for API requests (using your existing TokenService)
     */
    public function getToken()
    {
        try {
            $token = $this->tokenService->getToken();
            if (! $token) {
                throw new \Exception('Failed to retrieve a valid token');
            }

            return $token;
        } catch (\Exception $e) {
            Log::error('❌ Failed to retrieve token: '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * Get all active calls for a provider
     */
    public function getActiveCallsForProvider($providerExtension)
    {
        $retries = 0;
        $maxRetries = 1;

        while ($retries <= $maxRetries) {
            try {
                // Get a fresh token on each attempt, not just at the beginning
                $token = $this->getToken();
                $filter = "contains(Caller, '{$providerExtension}')";
                $url = $this->apiUrl.'/xapi/v1/ActiveCalls?$filter='.urlencode($filter);

                $response = $this->client->get($url, [
                    'headers' => [
                        'Authorization' => 'Bearer '.$token,
                        'Accept' => 'application/json',
                    ],
                    'timeout' => 30,
                ]);

                if ($response->getStatusCode() !== 200) {
                    throw new \Exception('Failed to fetch active calls. HTTP Status: '.$response->getStatusCode());
                }

                return json_decode($response->getBody()->getContents(), true);
            } catch (\Exception $e) {
                if ($retries < $maxRetries && strpos($e->getMessage(), '401') !== false) {
                    // Force token refresh then retry
                    $this->tokenService->refreshToken();
                    $retries++;
                    Log::info("Token refresh attempt {$retries} after 401 error for provider {$providerExtension}");

                    continue;
                }

                Log::error("❌ Failed to fetch active calls for provider {$providerExtension}: ".$e->getMessage());
                throw $e;
            }
        }
    }

    /**
     * Get all active calls
     */
    public function getAllActiveCalls()
    {
        $retries = 0;
        $maxRetries = 1;

        while ($retries <= $maxRetries) {
            try {
                // Get a fresh token on each attempt
                $token = $this->getToken();
                $url = $this->apiUrl.'/xapi/v1/ActiveCalls';

                $response = $this->client->get($url, [
                    'headers' => [
                        'Authorization' => 'Bearer '.$token,
                        'Accept' => 'application/json',
                    ],
                    'timeout' => 30,
                ]);

                return json_decode($response->getBody()->getContents(), true);
            } catch (\Exception $e) {
                if ($retries < $maxRetries && (strpos($e->getMessage(), '401') !== false)) {
                    // If we get a 401, force token refresh and retry
                    $this->tokenService->refreshToken();
                    $retries++;
                    Log::info("Token refresh attempt {$retries} after 401 error");

                    continue;
                }

                Log::error('❌ Failed to fetch active calls: '.$e->getMessage());
                throw $e;
            }
        }
    }

    /**
     * Make a call using 3CX API with improved error handling and caching
     *
     * @param  string  $providerExtension
     * @param  string  $destination
     * @return array
     *
     * @throws \Exception
     */
    public function makeCall($providerExtension, $destination)
    {
        // Check cache for recent call to this number
        $cacheKey = "recent_call_{$destination}";
        if (Cache::has($cacheKey)) {
            $recentCall = Cache::get($cacheKey);
            throw new \Exception("Duplicate call attempted to {$destination}. Previous call ID: {$recentCall['callid']}");
        }

        try {
            $token = $this->getToken();
            $url = $this->apiUrl."/callcontrol/{$providerExtension}/makecall";

            $response = $this->client->post($url, [
                'headers' => [
                    'Authorization' => 'Bearer '.$token,
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ],
                'json' => ['destination' => $destination],
                'timeout' => 10,
            ]);
            Log::info("📞 Call made to {$destination} by {$providerExtension}");

            $responseData = json_decode($response->getBody()->getContents(), true);

            if (! isset($responseData['result']['callid'])) {
                throw new \Exception('Missing call ID in response');
            }

            // Cache this call to prevent duplicates
            Cache::put($cacheKey, [
                'callid' => $responseData['result']['callid'],
                'timestamp' => now(),
            ], now()->addMinutes(5));

            return $responseData;
        } catch (\Exception $e) {
            Log::error('❌ Make call failed: '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * Update call record in database with consistent format
     */
    public function updateCallRecord($callId, $status, $call)
    {
        $duration_time = null;
        $duration_routing = null;
        $currentDuration = null;

        // Calculate durations if call data is provided
        if ($call && isset($call['EstablishedAt'], $call['ServerNow'])) {
            $establishedAt = Carbon::parse($call['EstablishedAt']);
            $serverNow = Carbon::parse($call['ServerNow']);
            $currentDuration = $establishedAt->diff($serverNow)->format('%H:%I:%S');

            // Retrieve existing record to preserve any existing durations
            $existingRecord = AutoDailerReport::where('call_id', $callId)->first();

            if ($existingRecord) {
                // Update durations based on current status
                switch ($status) {
                    case 'Talking':
                        $duration_time = $currentDuration;
                        $duration_routing = $existingRecord->duration_routing;
                        break;
                    case 'Routing':
                        $duration_routing = $currentDuration;
                        $duration_time = $existingRecord->duration_time;
                        break;
                    default:
                        $duration_time = $existingRecord->duration_time;
                        $duration_routing = $existingRecord->duration_routing;
                }
            }
        } else {
            // If updating without call data, preserve existing durations
            $existingRecord = AutoDailerReport::where('call_id', $callId)->first();

            if ($existingRecord) {
                $duration_time = $existingRecord->duration_time ?? null;
                $duration_routing = $existingRecord->duration_routing ?? null;
            }
        }

        try {
            DB::beginTransaction();

            $report = AutoDailerReport::where('call_id', $callId)->update([
                'status' => $status,
                'duration_time' => $duration_time,
                'duration_routing' => $duration_routing,
            ]);

            // Also update the data table for consistency
            $updated = ADialData::where('call_id', $callId)->update(['state' => $status]);

            Log::info("ADialParticipantsCommand ☎️✅ Call status updated for call_id: {$callId}, ".
                'Status: '.($call['Status'] ?? 'N/A').
                ', Duration: '.($currentDuration ?? 'N/A'));

            DB::commit();

            return $report;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("❌ Failed to update database for Call ID {$callId}: ".$e->getMessage());
            throw $e;
        }
    }

    // public function updateCallRecords(array $calls)
    // {
    //     if (empty($calls)) {
    //         return;
    //     }

    //     try {
    //         DB::beginTransaction();

    //         $updateData = [];
    //         $updateDataADial = [];

    //         foreach ($calls as $call) {
    //             $callId = $call['call_id'];
    //             $status = $call['status'];
    //             $provider = $call['provider'] ?? null;
    //             $extension = $call['extension'] ?? null;
    //             $phoneNumber = $call['phone_number'] ?? null;
    //             $callData = $call['call_data'] ?? null;

    //             $updateEntry = ['status' => $status];

    //             // ✅ Calculate durations if call data exists
    //             if ($callData && isset($callData['EstablishedAt'], $callData['ServerNow'])) {
    //                 $establishedAt = Carbon::parse($callData['EstablishedAt']);
    //                 $serverNow = Carbon::parse($callData['ServerNow']);
    //                 $currentDuration = $establishedAt->diff($serverNow)->format('%H:%I:%S');

    //                 if ($status === 'Talking') {
    //                     $updateEntry['duration_time'] = $currentDuration;
    //                 } elseif ($status === 'Routing') {
    //                     $updateEntry['duration_routing'] = $currentDuration;
    //                 }
    //             } else {
    //                 // ✅ Preserve existing durations
    //                 $existingRecord = AutoDailerReport::where('call_id', $callId)->first(['duration_time', 'duration_routing']);
    //                 if ($existingRecord) {
    //                     if ($status === 'Talking' && isset($existingRecord->duration_time)) {
    //                         $updateEntry['duration_time'] = $existingRecord->duration_time;
    //                     } elseif ($status === 'Routing' && isset($existingRecord->duration_routing)) {
    //                         $updateEntry['duration_routing'] = $existingRecord->duration_routing;
    //                     }
    //                 }
    //             }

    //             // ✅ Add provider info if available
    //             if ($provider) $updateEntry['provider'] = $provider;
    //             if ($extension) $updateEntry['extension'] = $extension;
    //             if ($phoneNumber) $updateEntry['phone_number'] = $phoneNumber;

    //             // ✅ Prepare bulk update data
    //             $updateData[] = array_merge(['call_id' => $callId], $updateEntry);
    //             $updateDataADial[] = ['call_id' => $callId, 'state' => $status];
    //         }

    //         // ✅ Bulk update `AutoDailerReport`
    //         DB::table('auto_dailer_reports')
    //             ->upsert($updateData, ['call_id'], array_keys($updateEntry));

    //         // ✅ Bulk update `ADialData`
    //         DB::table('a_dial_data')
    //             ->upsert($updateDataADial, ['call_id'], ['state']);

    //         DB::commit();

    //         Log::info("✅ Successfully updated " . count($calls) . " calls in batch.");
    //     } catch (\Exception $e) {
    //         DB::rollBack();
    //         Log::error("❌ Batch update failed: " . $e->getMessage());
    //     }
    // }

    // public function updateCallRecordsBatch($calls)
    // {
    //     if (empty($calls)) {
    //         return;
    //     }

    //     $updateADialData = [];
    //     $callUpdates = [];

    //     foreach ($calls as $call) {
    //         $callId = $call['Id'] ?? null;
    //         $status = $call['Status'] ?? 'Unknown';

    //         if (!$callId) {
    //             continue;
    //         }

    //         $establishedAt = isset($call['EstablishedAt']) ? Carbon::parse($call['EstablishedAt']) : null;
    //         $serverNow = isset($call['ServerNow']) ? Carbon::parse($call['ServerNow']) : Carbon::now();
    //         $currentDuration = $establishedAt ? $establishedAt->diff($serverNow)->format('%H:%I:%S') : null;

    //         $callUpdates[$callId] = [
    //             'status' => $status,
    //             'duration_time' => ($status === 'Talking' && $currentDuration) ? $currentDuration : null,
    //             'duration_routing' => ($status === 'Routing' && $currentDuration) ? $currentDuration : null,
    //             'phone_number' =>  DB::raw('phone_number') ? DB::raw('phone_number') : 'Missing',
    //         ];

    //         $updateADialData[$callId] = ['state' => $status];
    //     }

    //     try {
    //         DB::beginTransaction();

    //         // ✅ Bulk Update ADialData
    //         foreach ($updateADialData as $callId => $updateRecord) {
    //             ADialData::where('call_id', $callId)->update($updateRecord);
    //         }

    //         // ✅ Bulk Update AutoDailerReport
    //         foreach ($callUpdates as $callId => $updateRecord) {
    //             AutoDailerReport::where('call_id', $callId)->update($updateRecord);
    //         }

    //         DB::commit();
    //     } catch (\Exception $e) {
    //         DB::rollBack();
    //         Log::error("❌ Batch update failed: " . $e->getMessage());
    //     }
    // }
}
