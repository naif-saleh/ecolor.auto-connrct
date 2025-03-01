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
        if ($cachedToken) {
            Log::info('tokenServices: Using cached token One:');
            return $cachedToken;
        }

        return $this->generateToken();
    }

    /**
     * Generate a new token and cache it.
     */
    protected function generateToken()
    {
        Log::info('tokenServices: generate new Token');
        try {
            $response = Http::asForm()->post($this->authUrl, [
                'grant_type' => 'client_credentials',
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
            ]);

            if ($response->successful() && isset($response['access_token'])) {
                $token = $response['access_token'];
                $expiresIn = $response['expires_in'] ?? 60; // Default to 1 hour if not provided

                // Cache the token with its expiration time
                Cache::put('three_cx_token', $token, now()->addSeconds($expiresIn)); // Cache with a buffer

                Log::info('tokenServices: Token generated and cached successfully.');
                return $token;
            } else {
                Log::error('tokenServices: Failed to generate token: ' . $response->body());
            }
        } catch (\Exception $e) {
            Log::error('tokenServices: Error generating token: ' . $e->getMessage());
        }

        return null; // Return null if token generation fails
    }



    
}
