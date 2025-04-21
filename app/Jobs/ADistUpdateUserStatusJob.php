<?php

namespace App\Jobs;

use App\Models\ADistAgent;
use App\Services\ThreeCxService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ADistUpdateUserStatusJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 30; // Shorter timeout for faster processing

    /**
     * Create a new job instance.
     */
    public function __construct() {}

    /**
     * Execute the job.
     */
    public function handle(ThreeCxService $threeCxService): void
    {
        // Use a lock to prevent multiple overlapping executions
        $lockKey = 'adist_update_user_status_running';

        if (Cache::has($lockKey)) {
            Log::info('ADistUpdateUserStatusJob: Another instance is already running');

            return;
        }

        // Lock for 30 seconds max
        Cache::put($lockKey, true, 30);

        try {
            $startTime = microtime(true);

            // Use cached data if it's recent enough (within 10 seconds)
            $cacheKey = 'three_cx_users_data';
            $users = Cache::remember($cacheKey, 10, function () use ($threeCxService) {
                try {
                    $result = $threeCxService->getUsersFromThreeCxApi();
                    if (isset($result['value']) && is_array($result['value'])) {
                        return $result;
                    }

                    return null;
                } catch (\Exception $e) {
                    Log::error('Failed to fetch users: '.$e->getMessage());

                    return null;
                }
            });

            if ($users && isset($users['value']) && is_array($users['value'])) {
                // Use a persistent database connection to avoid reconnection overhead
                $connection = DB::connection();
                $pdo = $connection->getPdo();

                // Check if connection is still alive
                if (! $pdo || ! $this->isConnectionAlive($pdo)) {
                    $connection->reconnect();
                    $pdo = $connection->getPdo();
                }

                // Proceed without wrapping in a transaction
                foreach ($users['value'] as $user) {
                    ADistAgent::updateOrCreate(
                        ['three_cx_user_id' => $user['Id']],
                        [
                            'three_cx_user_id' => $user['Id'],
                            'status' => $user['CurrentProfileName'],
                            'displayName' => $user['DisplayName'],
                            'email' => $user['EmailAddress'],
                            'QueueStatus' => $user['QueueStatus'],
                            'extension' => $user['Number'],
                            'firstName' => $user['FirstName'],
                            'lastName' => $user['LastName'],
                            'updated_at' => now(),
                        ]
                    );
                }

                $executionTime = round(microtime(true) - $startTime, 3);
                Log::info('ADistUpdateUserStatusJob: ✅ Updated '.count($users['value'])." users in {$executionTime}s");
            }
        } catch (\Exception $e) {
            Log::error('ADistUpdateUserStatusJob Error: '.$e->getMessage());
            throw $e;
        } finally {
            Cache::forget($lockKey);
        }
    }

    /**
     * Check if database connection is still alive
     */
    private function isConnectionAlive($pdo): bool
    {
        try {
            $pdo->query('SELECT 1');

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('ADistUpdateUserStatusJob failed: '.$exception->getMessage());
        Cache::forget('adist_update_user_status_running');
    }
}
