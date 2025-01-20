<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\AutoDistributorUploadedData;
use App\Models\AutoDistributerFeedFile;
use Illuminate\Support\Facades\Cache;
use App\Services\TokenService;


class UpdateUserStatusCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:update-user-status-command';

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
        // $token = Cache::get('three_cx_token'); // Assuming you store the token in cache




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
                    \t\t\t********** Auto Distributor **********\n
                    \t-----------------------------------------------------------------------
                    \t| ✅ Successfully fetched users from 3CX API.           |
                    \t-----------------------------------------------------------------------
                ");
                    // Iterate through all users and update corresponding entries in the database
                    foreach ($users['value'] as $user) {
                        $extension = AutoDistributorUploadedData::where('three_cx_user_id', $user['Id'])->get();

                        if ($extension) {
                            // Update multiple fields
                            $updatedFields = [
                                'userStatus' => $user['CurrentProfileName'] ?? $extension->userStatus,
                                'displayName' => $user['DisplayName'] ?? $extension->displayName,
                                'email' => $user['EmailAddress'] ?? $extension->email,
                                'queueStatus' => $user['QueueStatus'] ?? $extension->queueStatus,
                                // Add more fields as needed
                            ];

                            $extension->update($updatedFields);

                            Log::info("Auto Distributor: ✅ Updated user data for extension ID {$extension->id}.", $updatedFields);
                        } else {

                            Log::warning("
                                            \t-----------------------------------------------------------------------
                                            \t\t********** Auto Distributor Warning **********\n
                                            \t-----------------------------------------------------------------------
                                            \t ⚠️  No extension found for 3CX user ID {$user['Id']}.
                                            \t-----------------------------------------------------------------------
                                    ");
                        }
                    }

                    $this->info('All user data updated successfully.');
                } else {
                    Log::warning("
                    \t-----------------------------------------------------------------------
                    \t\t********** Auto Distributor Warning **********\n
                    \t-----------------------------------------------------------------------
                    \t ⚠️  API response does not contain the expected value key or it is not an array.
                    \t-----------------------------------------------------------------------
            ");
                    $this->error('Unexpected API response structure.');
                }
            } else {
                Log::error('Auto Distributor Error: ❌ Failed to fetch users from 3CX API. Response: ' . $response->body());
                $this->error('Failed to fetch users from 3CX API. Check logs for details.');
            }
        } catch (\Exception $e) {
            Log::error('Auto Distributor Error: ❌ An error occurred while updating user data: ' . $e->getMessage());
            $this->error('An error occurred. Check logs for details.');
        }

        try {
            // Fetch user data from 3CX API
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token,
            ])->get(config('services.three_cx.api_url') . "/xapi/v1/Users");

            if ($response->successful()) {
                $users = $response->json();

                if (isset($users['value']) && is_array($users['value'])) {
                    Log::info("
                    \t-----------------------------------------------------------------------
                    \t\t\t********** Auto Distributor **********\n
                    \t-----------------------------------------------------------------------
                    \t| ✅ Successfully fetched users from 3CX API.           |
                    \t-----------------------------------------------------------------------
                ");

                    $updates = [];
                    foreach ($users['value'] as $user) {
                        $currentProfileName = $user['CurrentProfileName'] ?? null;

                        if ($currentProfileName) {
                            $updates[$user['Id']] = $currentProfileName;
                        } else {
                            Log::warning("ADist: Missing CurrentProfileName for 3CX user ID {$user['Id']}.");
                        }
                    }

                    if (!empty($updates)) {
                        // Retrieve all extensions and batch update
                        $extensions = AutoDistributorUploadedData::whereIn('three_cx_user_id', array_keys($updates))->get();

                        foreach ($extensions as $extension) {
                            if (isset($updates[$extension->three_cx_user_id])) {
                                $extension->update([
                                    'userStatus' => $updates[$extension->three_cx_user_id],
                                ]);
                                Log::info("Auto Distributor: ✅ Updated userStatus for extension ID {$extension->id} to {$updates[$extension->three_cx_user_id]}.");
                            }
                        }

                        $this->info('User statuses updated successfully for all files.');
                    } else {
                         Log::warning("
                                            \t-----------------------------------------------------------------------
                                            \t\t********** Auto Distributor Warning **********\n
                                            \t-----------------------------------------------------------------------
                                            \t\t\t ⚠️  No updates to process, all CurrentProfileName values were missing.
                                            \t-----------------------------------------------------------------------
                                    ");
                    }
                } else {
                    Log::warning("
                    \t-----------------------------------------------------------------------
                    \t\t********** Auto Distributor Warning **********\n
                    \t-----------------------------------------------------------------------
                    \t ⚠️  API response does not contain the expected value key or it is not an array.
                    \t-----------------------------------------------------------------------
            ");
                    $this->error('Unexpected API response structure.');
                }
            } else {
                Log::error('Auto Distributor Error: ❌ Failed to fetch users from 3CX API. Response: ' . $response->body());
                $this->error('Failed to fetch users from 3CX API. Check logs for details.');
            }
        } catch (\Exception $e) {
            Log::error('Auto Distributor Error: ❌ An error occurred while updating user statuses: ' . $e->getMessage());
            $this->error('An error occurred. Check logs for details.');
        }
    }
}
