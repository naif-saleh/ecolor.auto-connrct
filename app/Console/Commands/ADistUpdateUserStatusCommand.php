<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\ADistAgent;
use App\Models\AutoDistributerFeedFile;
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
        parent::__construct(); // This is required
        $this->tokenService = $tokenService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        Log::info('ADistUpdateUserStatusCommand executed at ' . Carbon::now());

        try {
            $token = $this->tokenService->getToken();
            // Fetch user data from 3CX API
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token,
            ])->get(config('services.three_cx.api_url') . "/xapi/v1/Users");

            if ($response->successful()) {
                $users = $response->json();

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

                        Log::info("Auto Distributor: ✅ Updated user data for extension ");
                    }

                    $this->info('All user data updated successfully.');
                }
            } else {
                Log::error('Auto Distributor Error: ❌ Failed to fetch users from 3CX API. Response: ' . $response->body());
                $this->error('Failed to fetch users from 3CX API. Check logs for details.');
            }
        } catch (\Exception $e) {
            Log::error('Auto Distributor Error: ❌ An error occurred while updating user data: ' . $e->getMessage());
            $this->error('An error occurred. Check logs for details.');
        }
    }
}
