<?php

namespace App\Services;

use App\Models\ADialData;
use App\Models\AutoDailerReport;
use Carbon\Carbon;
use GuzzleHttp\Client;
use Illuminate\Http\Client\RequestException;
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
            Log::error('❌ Failed to retrieve token: ' . $e->getMessage());
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
                $token = $this->getToken();
                $filter = "contains(Caller, '{$providerExtension}')";
                $url = $this->apiUrl . '/xapi/v1/ActiveCalls?$filter=' . urlencode($filter);

                $response = $this->client->get($url, [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $token,
                        'Accept' => 'application/json',
                    ],
                    'timeout' => 15, // Reduced timeout to avoid blocking
                ]);

                if ($response->getStatusCode() === 200) {
                    return json_decode($response->getBody()->getContents(), true);
                }

                throw new \Exception('Failed to fetch active calls. HTTP Status: ' . $response->getStatusCode());
            } catch (\Exception $e) {
                if ($retries < $maxRetries && strpos($e->getMessage(), '401') !== false) {
                    Log::warning("🔄 401 Unauthorized detected, refreshing token...");

                    // Refresh token only once
                    $this->tokenService->refreshToken();
                    $retries++;

                    continue;
                }

                Log::error("❌ Error fetching active calls for provider {$providerExtension}: " . $e->getMessage());
                return [];
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
                $url = $this->apiUrl . '/xapi/v1/ActiveCalls';

                $response = $this->client->get($url, [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $token,
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

                Log::error('❌ Failed to fetch active calls: ' . $e->getMessage());
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
            $url = $this->apiUrl . "/callcontrol/{$providerExtension}/makecall";

            $response = $this->client->post($url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
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
            Log::error('❌ Make call failed: ' . $e->getMessage());
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

        return DB::transaction(function () use ($callId, $status, $duration_time, $duration_routing, $call, $currentDuration) {
            $report = AutoDailerReport::where('call_id', $callId)->update([
                'status' => $status,
                'duration_time' => $duration_time,
                'duration_routing' => $duration_routing,
            ]);

            ADialData::where('call_id', $callId)->update(['state' => $status]);

            Log::info("ADialParticipantsCommand ☎️✅ Call status updated for call_id: {$callId}, " .
                'Status: ' . ($call['Status'] ?? 'N/A') .
                ', Duration: ' . ($currentDuration ?? 'N/A'));

            return $report;
        });
    }



    public function getParticipants($extension, $token)
    {
        try {
            $url = $this->apiUrl . "/callcontrol/{$extension}/participants";
            $response = $this->client->get($url, [
                'headers' => ['Authorization' => "Bearer $token"],
                'timeout' => 10
            ]);
            return json_decode($response->getBody(), true);
        } catch (RequestException $e) {
            Log::error("❌ Error fetching participants for {$extension}: " . $e->getMessage());
            return null;
        }
    }

    public function getDevices($extension, $token)
    {
        try {
            $url = $this->apiUrl . "/callcontrol/{$extension}/devices";
            $response = $this->client->get($url, [
                'headers' => ['Authorization' => "Bearer $token"],
                'timeout' => 10
            ]);
            $devices = json_decode($response->getBody(), true);
            Log::info("Devices for {$extension}: " . print_r($devices, true));
            return $devices;
        } catch (RequestException $e) {
            Log::error("❌ Error fetching devices for {$extension}: " . $e->getMessage());
            return null;
        }
    }


    public function makeCallAdist($extension, $deviceId, $destination, $token)
    {
        try {
            $url = $this->apiUrl . "/callcontrol/{$extension}/devices/{$deviceId}/makecall";
            $response = $this->client->post($url, [
                'headers' => ['Authorization' => "Bearer $token"],
                'json' => ['destination' => $destination],
                'timeout' => 10
            ]);
            Log::info("API Response for making call: " . $response->getBody());
            return json_decode($response->getBody(), true);
        } catch (RequestException $e) {
            Log::error("❌ Error making call for {$extension}: " . $e->getMessage());
            return null;
        }
    }
}
