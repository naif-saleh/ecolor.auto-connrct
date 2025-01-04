<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use Illuminate\Support\Facades\Log;

class ThreeCXTokenService
{
    public function fetchToken()
    {
        $apiUrl = config('services.three_cx.api_url') . '/connect/token';
        $clientId = config('services.three_cx.client_id');
        $clientSecret = config('services.three_cx.client_secret');

        $client = new Client();

        try {
            $request = new Request('POST', $apiUrl, [
                'Content-Type' => 'application/x-www-form-urlencoded',
            ], http_build_query([
                'grant_type' => 'client_credentials',
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
            ]));

            // Log the equivalent curl command for debugging
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
            $responseBody = json_decode($response->getBody(), true);

            if (isset($responseBody['access_token'])) {
                return $responseBody['access_token'];
            } else {
                Log::error('Token response did not contain an access token.', $responseBody);
            }
        } catch (\Exception $e) {
            Log::error('Error fetching ThreeCX token: ' . $e->getMessage());
        }

        return null; // Return null if the token could not be fetched
    }
}
