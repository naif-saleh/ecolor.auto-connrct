<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use App\Jobs\ADistUpdateUserStatusJob;
use Carbon\Carbon;

class ADistUpdateUserStatusCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:adist-update-user-status';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Dispatch a job to update user statuses from 3CX API';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        try {
            // Dispatch with low delay to ensure high priority
            ADistUpdateUserStatusJob::dispatch()->delay(now()->addSecond(1));

            return Command::SUCCESS;
        } catch (\Exception $e) {
            Log::error('ADistUpdateUserStatusCommand Error: ❌ Failed to dispatch job: ' . $e->getMessage());
            $this->error('Failed to dispatch job: ' . $e->getMessage());

            return Command::FAILURE;
        }
    }
}
