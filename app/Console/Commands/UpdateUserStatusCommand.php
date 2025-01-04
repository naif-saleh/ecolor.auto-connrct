<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\AutoDistributererExtension;
use App\Models\AutoDistributerFeedFile;
use Illuminate\Support\Facades\Cache;

use App\Services\ThreeCXTokenService;


class UpdateUserStatusCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:update-user-status-command';
    protected $threeCXTokenService;

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    public function __construct(ThreeCXTokenService $threeCXTokenService)
    {
        $this->threeCXTokenService = $threeCXTokenService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
       // $token = Cache::get('three_cx_token'); // Assuming you store the token in cache

        $token = $this->threeCXTokenService->fetchToken();
        if (!$token) {
            Log::error('ADist: 3CX token not found in cache.');
            $this->error('3CX token not found. Ensure it is cached before running the command.');
            return;
        }

        try {
            // Fetch user data from 3CX API
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token,
            ])->get("https://ecolor.3cx.agency/xapi/v1/Users");

            if ($response->successful()) {
                $users = $response->json();

                if (isset($users['value']) && is_array($users['value'])) {
                    Log::info('ADist: Successfully fetched users from 3CX API.');

                    // Iterate through users and update userStatus
                    foreach ($users['value'] as $user) {
                        $extension = AutoDistributererExtension::where('three_cx_user_id', $user['Id'])->first();

                        if ($extension) {
                            $currentProfileName = $user['CurrentProfileName'] ?? null;
                            if ($currentProfileName) {
                                $extension->update(['userStatus' => $currentProfileName]);
                                Log::info("ADist: Updated userStatus for extension ID {$extension->id} to {$currentProfileName}.");
                            } else {
                                Log::warning("ADist: Missing CurrentProfileName for 3CX user ID {$user['Id']}.");
                            }
                        } else {
                            Log::warning("ADist: No extension found for 3CX user ID {$user['Id']}.");
                        }
                    }

                    $this->info('User statuses updated successfully.');
                } else {
                    Log::warning('ADist: API response does not contain the expected "value" key or it is not an array.');
                    $this->error('Unexpected API response structure.');
                }
            } else {
                Log::error('ADist: Failed to fetch users from 3CX API. Response: ' . $response->body());
                $this->error('Failed to fetch users from 3CX API. Check logs for details.');
            }
        } catch (\Exception $e) {
            Log::error('ADist: An error occurred while updating user statuses: ' . $e->getMessage());
            $this->error('An error occurred. Check logs for details.');
        }











        try {
            // Fetch user data from 3CX API
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token,
            ])->get("https://ecolor.3cx.agency/xapi/v1/Users");

            if ($response->successful()) {
                $users = $response->json();

                if (isset($users['value']) && is_array($users['value'])) {
                    Log::info('ADist: Successfully fetched users from 3CX API.');

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
                        $extensions = AutoDistributerFeedFile::whereIn('three_cx_user_id', array_keys($updates))->get();

                        foreach ($extensions as $extension) {
                            if (isset($updates[$extension->three_cx_user_id])) {
                                $extension->update([
                                    'userStatus' => $updates[$extension->three_cx_user_id],
                                ]);
                                Log::info("ADist: Updated userStatus for extension ID {$extension->id} to {$updates[$extension->three_cx_user_id]}.");
                            }
                        }

                        $this->info('User statuses updated successfully for all files.');
                    } else {
                        Log::info('ADist: No updates to process, all CurrentProfileName values were missing.');
                    }
                } else {
                    Log::warning('ADist: API response does not contain the expected "value" key or it is not an array.');
                    $this->error('Unexpected API response structure.');
                }
            } else {
                Log::error('ADist: Failed to fetch users from 3CX API. Response: ' . $response->body());
                $this->error('Failed to fetch users from 3CX API. Check logs for details.');
            }
        } catch (\Exception $e) {
            Log::error('ADist: An error occurred while updating user statuses: ' . $e->getMessage());
            $this->error('An error occurred. Check logs for details.');
        }
    }
}
