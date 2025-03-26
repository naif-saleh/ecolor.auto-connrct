<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TokenService
{
    protected $authUrl;
    protected $clientId;
    protected $clientSecret;

    public function __construct()
    {
        $this->authUrl = config('services.three_cx.api_url') . '/connect/token';
        $this->clientId = config('services.three_cx.client_id');
        $this->clientSecret = config('services.three_cx.client_secret');
    }

    /**
     * Get the cached token or generate a new one if expired.
     */
    public function getToken()
    {
        return Cache::remember('three_cx_token', now()->addMinutes(55), function () {
            return $this->generateToken();
        });
    }

    /**
     * Generate a new token and cache it.
     */
    protected function generateToken()
    {
        Log::info('🔑 Generating new token for 3CX API...');

        try {
            $response = Http::asForm()->post($this->authUrl, [
                'grant_type' => 'client_credentials',
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
            ]);

            if ($response->successful() && isset($response['access_token'])) {
                $token = $response['access_token'];
                $expiresIn = max(($response['expires_in'] ?? 3600) - 60, 300); // Ensure at least 5 min cache

                Cache::put('three_cx_token', $token, now()->addSeconds($expiresIn));
                Log::info('✅ Token generated and cached successfully.');

                return $token;
            }

            Log::error('❌ Failed to generate token: ' . $response->body());
        } catch (\Exception $e) {
            Log::error('🚨 Token generation error: ' . $e->getMessage());
        }

        return null; 
    }

    /**
     * Forcefully refresh the token and update the cache.
     */
    public function refreshToken()
    {
        Log::warning('🔄 Forcing token refresh due to 401 Unauthorized.');

        return Cache::lock('three_cx_token_lock', 5)->block(5, function () {
            return $this->generateToken();
        });
    }
}
