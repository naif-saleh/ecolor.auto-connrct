<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Http;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
        $this->fetchAndCacheThreeCxToken();
    }
    protected function fetchAndCacheThreeCxToken()
    {
        $apiUrl = config('services.three_cx.api_url') . '/connect/token';
    $clientId = config('services.three_cx.client_id');
        $clientSecret = config('services.three_cx.client_secret');

        $cachedToken = Cache::get('three_cx_token');
        if ($cachedToken) {
            Log::info('Using cached ThreeCX token.');
            return $cachedToken; // Use the cached token
        }

        $client = new Client();

        try {
           

            $request = new Request('POST', 'https://ecolor.3cx.agency/connect/token', [
                'Content-Type' => 'application/x-www-form-urlencoded',
            ], http_build_query([
                'grant_type' => 'client_credentials',
                'client_id' => 'testapi',
                'client_secret' => '95ULDtdTRRJhJBZCp94K6Gd1BKRuaP1k',
            ]));
        
            // Log the equivalent curl command
            $headers = collect($request->getHeaders())->map(function ($value, $key) {
                return "-H \"$key: " . implode(", ", $value) . "\"";
            })->implode(' ');
        
            $body = $request->getBody()->getContents();
        
            $curlCommand = sprintf(
                "curl -X %s %s %s -d '%s'",
                $request->getMethod(),
                $request->getUri(),
                $headers,
                $body
            );
        
            Log::info('Equivalent curl command: ' . $curlCommand);
        
            $response = $client->send($request);
        
           // dd(json_decode($response->getBody(), true));
            $responseBody = json_decode($response->getBody(), true);
            if (isset($responseBody['access_token'])) {
                $token = $responseBody['access_token'];
                $expiresIn = $responseBody['expires_in']; // Typically in seconds

                // Cache the token with expiration time
                Cache::put('three_cx_token', $token, now()->addSeconds($expiresIn));

                Log::info('ThreeCX token cached successfully.');
                return $token;
            } else {
                Log::error('Token response did not contain an access token.', $responseBody);
            }
        } catch (\Exception $e) {
            Log::error('Error fetching ThreeCX token: ' . $e->getMessage());
        }
    }
}
