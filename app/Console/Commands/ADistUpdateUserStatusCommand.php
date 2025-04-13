<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
 use Illuminate\Support\Facades\Log;
use App\Models\ADistAgent;
use App\Services\ThreeCxService;
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


    protected $threeCxService;

    public function __construct(ThreeCxService $threeCxService)
    {
        parent::__construct();
        $this->threeCxService = $threeCxService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        Log::info('UpdateUserStatusCommand executed at ' . Carbon::now());

        try {
            // Get users via token service (includes retry + refresh logic)
            $users = $this->threeCxService->getUsersFromThreeCxApi();

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
                        ['three_cx_user_id' => $user['id']],
                        [
                            'status' => $user['status'] ?? null,
                            'displayName' => $user['displayName'] ?? null,
                            'email' => $user['email'] ?? null,
                            'QueueStatus' => $user['QueueStatus'] ?? null,
                            'extension' => $user['extension'] ?? null,
                            'firstName' => $user['firstName'] ?? null,
                            'lastName' => $user['lastName'] ?? null,
                            'updated_at' => Carbon::now(),
                        ]
                    );
                }


                Log::info('✅ All user data updated successfully.');
            } else {
                Log::error('UpdateUserStatusCommand Error: ❌ No users returned from 3CX API.');
                $this->error('No users returned from 3CX API.');
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
