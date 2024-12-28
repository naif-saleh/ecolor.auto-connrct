<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use App\Models\AutoDailerProviderFeed;
use Illuminate\Support\Facades\Log;

class ProcessMakeCall implements ShouldQueue
{
    use Queueable;
    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::warning("ProcessMakeCall Started");
        $providers = AutoDailerProviderFeed::all();
        dd(  $providers );


 
    }
}
