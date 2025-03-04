<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Models\ADialProvider;
use App\Models\ADialData;
use App\Models\ADialFeed;
use App\Services\TokenService;
use App\Services\ThreeCxService;
use App\Jobs\UpdateCallStatusJob;


class ADialParticipantsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:ADial-participants-command';


    protected $threeCxService;

    public function __construct(ThreeCxService $threeCxService)
    {
        parent::__construct();
        $this->threeCxService = $threeCxService;
    }

    public function handle()
    {
        $startTime = Carbon::now();
        Log::info('✅ ADialParticipantsCommand started at ' . $startTime);

        $timezone = config('app.timezone');
        $now = now()->timezone($timezone);
        $providers = ADialProvider::whereHas('files', function ($query) {
            $query->whereDate('date', today())->where('allow', true);
        })->get();


        Log::info("ADialParticipantsCommand: Total providers found: " . $providers->count());

        foreach ($providers as $provider) {
            Log::info("ADialParticipantsCommand Processing provider: {$provider->extension}");
            $this->processProviderStatus($provider, $now, $timezone);
        }

        $endTime = Carbon::now();
        $executionTime = $startTime->diffInMilliseconds($endTime);
        Log::info("ADialParticipantsCommand ✅ ADialParticipantsCommand execution completed in {$executionTime} ms.");
    }

    protected function processProviderStatus($provider, $now, $timezone)
    {
        $providerStartTime = Carbon::now();

        $files = ADialFeed::where('provider_id', $provider->id)
            ->whereDate('date', today())
            ->where('allow', true)
            ->get();

        // Skip providers with no active feed files
        if ($files->isEmpty()) {
            Log::info("ADialParticipantsCommand ⚠️ No active feed files for provider: {$provider->extension}");
            return;
        }

        // Check if any file is in the active time window or has active calls
        $shouldCheck = false;

        // First check if any file is in the call window
        foreach ($files as $file) {
            $from = Carbon::parse("{$file->date} {$file->from}")->timezone($timezone);
            $to = Carbon::parse("{$file->date} {$file->to}")->timezone($timezone);

            if ($now->between($from, $to)) {
                $shouldCheck = true;
                break;
            }
        }

        // If no files in window, check if provider has any active calls from today
        if (!$shouldCheck) {
            $hasActiveCalls = ADialData::whereHas('file', function ($query) use ($provider) {
                $query->where('feed_id', $provider->id);
            })
                ->whereDate('call_date', today())
                ->whereIn('state', ['Routing', 'Talking', 'Ringing'])
                ->exists();

            if ($hasActiveCalls) {
                $shouldCheck = true;
            }
        }

        if (!$shouldCheck) {
            Log::info("ADialParticipantsCommand ⚠️ Skipping provider no current activity {$provider->extension}, .");

            return;
        }

        // Fetch active calls for this provider
        try {
            $activeCalls = $this->threeCxService->getActiveCallsForProvider($provider->extension);

            if (empty($activeCalls['value'])) {
                Log::info("ADialParticipantsCommand ⚠️ No active calls found for provider {$provider->extension}");
                return;
            }

            Log::info("ADialParticipantsCommand Found active call(s) for provider" . count($activeCalls['value']) . " | {$provider->extension}");

            // Process each active call
            foreach ($activeCalls['value'] as $call) {
                $this->updateCallStatus($call);
            }
        } catch (\Exception $e) {
            Log::error("ADialParticipantsCommand❌ Failed to process active calls for provider {$provider->extension}: " . $e->getMessage());
            return;
        }

        $providerEndTime = Carbon::now();
        $providerExecutionTime = $providerStartTime->diffInMilliseconds($providerEndTime);
        Log::info("ADialParticipantsCommand⏳ Execution time for provider {$provider->extension}: {$providerExecutionTime} ms");
    }


// protected function updateCallStatus($call, $provider = null, $extension = null, $phoneNumber = null)
// {
//     $callId = $call['Id'] ?? null;

//     if (!$callId) {
//         Log::warning("ADialParticipantsCommand⚠️ Missing Call ID in response");
//         return;
//     }

//     // Dispatch job to queue with the correct argument structure
//     UpdateCallStatusJob::dispatch([$call]);

//     Log::info("ADialParticipantsCommand📤 Queued update for Call ID: {$callId}");
// }


    protected function updateCallStatus($call)
    {
        $callId = $call['Id'] ?? null;
        $callStatus = $call['Status'] ?? null;

        if (!$callId) {
            Log::warning("ADialParticipantsCommand ⚠️ Missing Call ID in response");
            return;
        }

        $this->threeCxService->updateCallRecord($callId, $callStatus, $call);
        // Dispatch job to queue
        // UpdateCallStatusJob::dispatch($call, $provider, $extension, $phoneNumber, $this->threeCxService);


        // Log::info("ADialParticipantsCommand 📤 Queued update for Call ID: {$callId}");
    }




}
