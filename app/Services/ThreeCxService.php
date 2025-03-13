<?php

namespace App\Services;

use App\Models\ADialData;
use App\Models\AutoDailerReport;
use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\Promise\Utils;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use GuzzleHttp\Exception\RequestException;
use Throwable;

class ThreeCxService
{
    protected $client;
    protected $tokenService;
    protected $apiUrl;
    protected $tokenCacheKey = 'threecx_api_token';
    protected $tokenCacheTime = 3600; // 1 hour

    public function __construct(TokenService $tokenService)
    {
        $this->tokenService = $tokenService;
        $this->client = new Client([
            'timeout' => 15,
            'connect_timeout' => 5,
            'http_errors' => false,
            'verify' => true,
        ]);
        $this->apiUrl = config('services.three_cx.api_url');
    }

    /**
     * Get a fresh token for API requests with caching
     */
    public function getToken()
    {
        try {
            // Try to get token from cache first
            $token = Cache::get($this->tokenCacheKey);

            if (!$token) {
                $token = $this->tokenService->getToken();
                if (!$token) {
                    throw new \Exception("Failed to retrieve a valid token");
                }
                // Store token in cache
                Cache::put($this->tokenCacheKey, $token, $this->tokenCacheTime);
            }

            return $token;
        } catch (\Exception $e) {
            Log::error("❌ Failed to retrieve token: " . $e->getMessage());
            throw $e;
        }
    }


    public function makeCall($data, $provider)
    {
        try {
            // Make the call using ThreeCxService
            $responseData = $this->threeCxService->makeCall(
                $provider->extension,
                $data->mobile
            );

            $callId = $responseData['result']['callid'] ?? null;
            $status = $responseData['result']['status'] ?? 'unknown';

            // Log call details
            Log::info("makeCall: 📞✅ Call successful for mobile: {$data->mobile}. Call ID: {$callId}, Status: {$status}");

            // Save call details in AutoDialerReport
            AutoDailerReport::create([
                'call_id' => $callId,
                'status' => $status,
                'provider' => $provider->name,
                'extension' => $provider->extension,
                'phone_number' => $data->mobile
            ]);

            // Update ADialData state
            $data->update([
                'state' => $status,
                'call_date' => now(),
                'call_id' => $callId
            ]);

            // Wait for 300ms before making the next call
            usleep(300000);
        } catch (\Exception $e) {
            Log::error("makeCall: ❌ Call failed for number {$data->mobile}: " . $e->getMessage());
        }
    }




    /**
     * Get all active calls for a provider with retry mechanism
     */
    public function getActiveCallsForProvider($providerExtension, $retryCount = 2)
    {
        $attempt = 0;

        while ($attempt <= $retryCount) {
            try {
                $token = $this->getToken();
                $filter = "contains(Caller, '{$providerExtension}')";
                $url = $this->apiUrl . "/xapi/v1/ActiveCalls?\$filter=" . urlencode($filter);

                $response = $this->client->get($url, [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $token,
                        'Accept' => 'application/json',
                    ]
                ]);

                $statusCode = $response->getStatusCode();

                if ($statusCode === 200) {
                    return json_decode($response->getBody()->getContents(), true);
                }

                if ($statusCode === 401 && $attempt < $retryCount) {
                    // Token might be expired, invalidate cache and retry
                    Cache::forget($this->tokenCacheKey);
                    $attempt++;
                    continue;
                }

                throw new \Exception("Failed to fetch active calls. HTTP Status: " . $statusCode);
            } catch (RequestException $e) {
                if ($e->hasResponse() && $e->getResponse()->getStatusCode() === 429) {
                    // Rate limiting - wait and retry
                    $attempt++;
                    if ($attempt <= $retryCount) {
                        sleep(2 * $attempt); // Exponential backoff
                        continue;
                    }
                }

                Log::error("❌ Failed to fetch active calls (attempt {$attempt}): " . $e->getMessage());

                if ($attempt >= $retryCount) {
                    throw $e;
                }

                $attempt++;
                sleep(1);
            } catch (\Exception $e) {
                Log::error("❌ Failed to fetch active calls for provider {$providerExtension}: " . $e->getMessage());

                if ($attempt >= $retryCount) {
                    throw $e;
                }

                $attempt++;
                sleep(1);
            }
        }

        // If we get here, all retries failed
        throw new \Exception("All retry attempts failed for provider {$providerExtension}");
    }

    /**
     * Get all active calls in the system
     */
    public function getAllActiveCalls()
    {
        // Implementation similar to getActiveCallsForProvider with retry mechanism
        // Removed for brevity as it's not used in the current flow
    }

    /**
     * Update call records in bulk - optimized for performance
     */
    public function batchUpdateCallStatuses(array $calls)
    {
        if (empty($calls)) {
            return [
                'status' => 'success',
                'message' => 'No calls to update',
                'count' => 0
            ];
        }

        $callData = [];
        $callIds = [];

        // Prepare data for batch update
        foreach ($calls as $call) {
            $callId = $call['Id'] ?? null;
            $status = $call['Status'] ?? null;

            if (!$callId || !$status) {
                continue;
            }

            $callIds[] = $callId;
            $callData[$callId] = [
                'status' => $status,
                'call_data' => $call
            ];
        }

        if (empty($callIds)) {
            return [
                'status' => 'warning',
                'message' => 'No valid call data found',
                'count' => 0
            ];
        }

        try {
            // Fetch existing records in a single query to avoid N+1 problems
            $existingRecords = AutoDailerReport::whereIn('call_id', $callIds)
                ->select('call_id', 'duration_time', 'duration_routing')
                ->get()
                ->keyBy('call_id');

            // Prepare update data
            $updateReportData = [];
            $updateDialData = [];

            foreach ($callIds as $callId) {
                $call = $callData[$callId];
                $status = $call['status'];
                $callApiData = $call['call_data'];

                $duration = null;
                $routingDuration = null;
                $existingRecord = $existingRecords->get($callId);

                // Calculate durations if we have the necessary data
                if (isset($callApiData['EstablishedAt'], $callApiData['ServerNow'])) {
                    $establishedAt = Carbon::parse($callApiData['EstablishedAt']);
                    $serverNow = Carbon::parse($callApiData['ServerNow']);
                    $currentDuration = $establishedAt->diff($serverNow)->format('%H:%I:%S');

                    if ($existingRecord) {
                        $duration = $existingRecord->duration_time;
                        $routingDuration = $existingRecord->duration_routing;
                    }

                    // Update appropriate duration field based on status
                    if ($status === 'Talking') {
                        $duration = $currentDuration;
                    } elseif ($status === 'Routing') {
                        $routingDuration = $currentDuration;
                    }
                }

                $updateReportData[] = [
                    'call_id' => $callId,
                    'status' => $status,
                    'duration_time' => $duration,
                    'duration_routing' => $routingDuration
                ];

                $updateDialData[] = [
                    'call_id' => $callId,
                    'state' => $status
                ];
            }

            // Use chunking for large datasets to avoid memory issues
            $chunkSize = 100;
            $reportChunks = array_chunk($updateReportData, $chunkSize);
            $dialDataChunks = array_chunk($updateDialData, $chunkSize);

            // Use database transaction to ensure data consistency
            DB::beginTransaction();

            try {
                // Process each chunk
                foreach ($reportChunks as $chunk) {
                    $this->processReportChunk($chunk);
                }

                foreach ($dialDataChunks as $chunk) {
                    $this->processDialDataChunk($chunk);
                }

                DB::commit();

                return [
                    'status' => 'success',
                    'message' => 'Successfully updated call statuses',
                    'count' => count($callIds)
                ];
            } catch (Throwable $e) {
                DB::rollBack();
                Log::error("❌ Batch update transaction failed: " . $e->getMessage());
                throw $e;
            }
        } catch (Throwable $e) {
            Log::error("❌ Batch update preparation failed: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Process a chunk of report updates efficiently using CASE statements
     */
    private function processReportChunk(array $chunk)
    {
        $callIds = array_column($chunk, 'call_id');
        $updates = collect($chunk)->keyBy('call_id');

        if (empty($callIds)) {
            return;
        }

        // Use CASE statements for efficient bulk updates
        return AutoDailerReport::whereIn('call_id', $callIds)
            ->update([
                'status' => DB::raw("CASE call_id " .
                    $updates->map(function ($item, $callId) {
                        return "WHEN '{$callId}' THEN '{$item['status']}'";
                    })->implode(' ') .
                    " ELSE status END"),
                'duration_time' => DB::raw("CASE call_id " .
                    $updates->map(function ($item, $callId) {
                        return "WHEN '{$callId}' THEN " .
                            ($item['duration_time'] ? "'{$item['duration_time']}'" : 'duration_time');
                    })->implode(' ') .
                    " ELSE duration_time END"),
                'duration_routing' => DB::raw("CASE call_id " .
                    $updates->map(function ($item, $callId) {
                        return "WHEN '{$callId}' THEN " .
                            ($item['duration_routing'] ? "'{$item['duration_routing']}'" : 'duration_routing');
                    })->implode(' ') .
                    " ELSE duration_routing END")
            ]);
    }

    /**
     * Process a chunk of dial data updates efficiently using CASE statements
     */
    private function processDialDataChunk(array $chunk)
    {
        $callIds = array_column($chunk, 'call_id');
        $updates = collect($chunk)->keyBy('call_id');

        if (empty($callIds)) {
            return;
        }

        return ADialData::whereIn('call_id', $callIds)
            ->update([
                'state' => DB::raw("CASE call_id " .
                    $updates->map(function ($item, $callId) {
                        return "WHEN '{$callId}' THEN '{$item['state']}'";
                    })->implode(' ') .
                    " ELSE state END")
            ]);
    }

    /**
     * Process multiple providers in parallel for better performance
     */
    public function processProvidersInParallel(array $providers, $timeout = 30)
    {
        $promises = [];
        $results = [];

        // Create promises for each provider
        foreach ($providers as $provider) {
            $promises[$provider->extension] = $this->client->getAsync(
                $this->apiUrl . "/xapi/v1/ActiveCalls?\$filter=" . urlencode("contains(Caller, '{$provider->extension}')"),
                [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $this->getToken(),
                        'Accept' => 'application/json',
                    ],
                    'timeout' => $timeout
                ]
            );
        }

        // Wait for all promises to complete
        $responses = Utils::settle($promises)->wait();

        // Process responses
        foreach ($responses as $providerExt => $response) {
            if ($response['state'] === 'fulfilled') {
                $data = json_decode($response['value']->getBody()->getContents(), true);
                if (!empty($data['value'])) {
                    $results[$providerExt] = $data['value'];
                }
            } else {
                Log::error("❌ Failed to fetch calls for provider {$providerExt}: " . $response['reason']->getMessage());
            }
        }

        return $results;
    }
}
