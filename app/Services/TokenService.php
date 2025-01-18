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
     * Get the token, either from cache or by generating a new one.
     */
    public function getToken()
{
    $cachedToken = Cache::get('three_cx_token');

    if ($cachedToken && !$this->isTokenExpired()) {
        Log::info('Using cached token.');
        return $cachedToken;
    }

    // Prevent multiple simultaneous token refreshes
    return Cache::lock('three_cx_token_lock', 10)->get(function () {
        return $this->generateToken();
    });
}

    // public function getToken()
    // {
    //     $cachedToken = Cache::get('three_cx_token');

    //     if ($cachedToken && !$this->isTokenExpired()) {
    //         Log::info('Using cached token.');
    //         return $cachedToken;
    //     }

    //     Log::info('Cached token expired or missing, generating a new one.');
    //     return $this->generateToken();
    // }

    private function isTokenExpired()
    {
        return Cache::has('three_cx_token_expires') ? now()->greaterThan(Cache::get('three_cx_token_expires')) : true;
    }


    /**
     * Generate a new token and cache it.
     */
    protected function generateToken()
    {
        try {
            $response = Http::asForm()->post($this->authUrl, [
                'grant_type' => 'client_credentials',
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
            ]);

            if ($response->successful() && isset($response['access_token'])) {
                $token = $response['access_token'];
                $expiresIn = $response['expires_in'] ?? 3600; // Default to 1 hour if not provided

                // Cache the token with its expiration time
                Cache::put('three_cx_token', $token, now()->addSeconds($expiresIn - 60)); // Cache with a buffer

                Log::info('Token generated and cached successfully.');
                return $token;
            } else {
                Log::error('Failed to generate token: ' . $response->body());
            }
        } catch (\Exception $e) {
            Log::error('Error generating token: ' . $e->getMessage());
        }

        return null; // Return null if token generation fails
    }
}
