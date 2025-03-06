<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\ADistAgent;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Cache;
use App\Services\TokenService;
use Carbon\Carbon;


class ADistUpdateUserStatusCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:ADist-update-user-status-command';

    protected $tokenService;
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    public function __construct(TokenService $tokenService)
    {
        parent::__construct();
        $this->tokenService = $tokenService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        Log::info('UpdateUserStatusCommand executed at ' . Carbon::now());

        try {
            $token = $this->tokenService->getToken();
            Log::info('Update Token: ' . $token);
            // Create Guzzle client
            $client = new Client();

            // Fetch user data from 3CX API using Guzzle
            $response = $client->request('GET', config('services.three_cx.api_url') . "/xapi/v1/Users", [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Accept' => 'application/json',
                ],
                'timeout' => 10,
            ]);

            if ($response->getStatusCode() == 200) {
                $users = json_decode($response->getBody()->getContents(), true);

                if (isset($users['value']) && is_array($users['value'])) {
                    Log::info("
                    \t-----------------------------------------------------------------------
                    \t\t\t********** Updating Users **********\n
                    \t-----------------------------------------------------------------------
                    \t| ✅ Successfully fetched users from 3CX API.           |
                    \t-----------------------------------------------------------------------
                    ");

                    foreach ($users['value'] as $user) {
                        ADistAgent::updateOrCreate(
                            ['three_cx_user_id' => $user['Id']], // Search criteria
                            [
                                'status' => $user['CurrentProfileName'],
                                'displayName' => $user['DisplayName'],
                                'email' => $user['EmailAddress'],
                                'QueueStatus' => $user['QueueStatus'],
                                'extension' => $user['Number'],
                                'firstName' => $user['FirstName'],
                                'lastName' => $user['LastName'],
                            ]
                        );

                        //Log::info("UpdateUserStatusCommand: ✅ Updated user data for extension " . $user['Number']);
                    }

                    $this->info('✅ All user data updated successfully.');
                }
            } else {
                Log::error('UpdateUserStatusCommand Error: ❌ Failed to fetch users from 3CX API. Response status: ' . $response->getStatusCode());
                $this->error('Failed to fetch users from 3CX API. Check logs for details.');
            }
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            Log::error('UpdateUserStatusCommand Error: ❌ Guzzle request failed: ' . $e->getMessage());
            if ($e->hasResponse()) {
                Log::error('Response: ' . $e->getResponse()->getBody()->getContents());
            }
            $this->error('An error occurred with the API request. Check logs for details.');
        } catch (\Exception $e) {
            Log::error('UpdateUserStatusCommand Error: ❌ An error occurred while updating user data: ' . $e->getMessage());
            $this->error('An error occurred. Check logs for details.');
        }
    }
}
