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
        $providers = ADialProvider::all();

        Log::info("ADialParticipantsCommand: Total providers found: " . $providers->count());

        foreach ($providers as $provider) {
            Log::info("ADialParticipantsCommandProcessing provider: {$provider->extension}");
            $this->processProviderStatus($provider, $now, $timezone);
        }

        $endTime = Carbon::now();
        $executionTime = $startTime->diffInMilliseconds($endTime);
        Log::info("ADialParticipantsCommand✅ ADialParticipantsCommand execution completed in {$executionTime} ms.");
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
            Log::info("ADialParticipantsCommandNo active feed files for provider: {$provider->extension}");
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
            $hasActiveCalls = ADialData::whereHas('feed', function ($query) use ($provider) {
                $query->where('provider_id', $provider->id);
            })
                ->whereDate('call_date', today())
                ->whereIn('state', ['Routing', 'Talking', 'Ringing'])
                ->exists();

            if ($hasActiveCalls) {
                $shouldCheck = true;
            }
        }

        if (!$shouldCheck) {
            Log::info("ADialParticipantsCommand❌ Skipping provider {$provider->extension}, no current activity.");
            return;
        }

        // Fetch active calls for this provider
        try {
            $activeCalls = $this->threeCxService->getActiveCallsForProvider($provider->extension);

            if (empty($activeCalls['value'])) {
                Log::info("ADialParticipantsCommandNo active calls found for provider {$provider->extension}");
                return;
            }

            Log::info("ADialParticipantsCommandFound " . count($activeCalls['value']) . " active call(s) for provider {$provider->extension}");

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

    protected function updateCallStatus($call)
    {
        $callStartTime = Carbon::now();

        $callId = $call['Id'] ?? null;
        $status = $call['Status'] ?? 'Unknown';

        if (!$callId) {
            Log::warning("ADialParticipantsCommand⚠️ Missing Call ID in response");
            return;
        }

        try {
            // Update call record with full call data for duration calculation
            $this->threeCxService->updateCallRecord($callId, $status, $call);

            Log::info("ADialParticipantsCommand✅ Updated Call: {$callId}, Status: {$status}");
        } catch (\Exception $e) {
            Log::error("ADialParticipantsCommand❌ Failed to update database for Call ID {$callId}: " . $e->getMessage());
        }

        $callEndTime = Carbon::now();
        $callExecutionTime = $callStartTime->diffInMilliseconds($callEndTime);
        Log::info("ADialParticipantsCommand⏳ Execution time for call {$callId}: {$callExecutionTime} ms");
    }




    // protected $tokenService;
    // /**
    //  * The console command description.
    //  *
    //  * @var string
    //  */
    // protected $description = 'Fetch and process participants data from 3CX API';
    // public function __construct(TokenService $tokenService)
    // {
    //     parent::__construct(); // This is required
    //     $this->tokenService = $tokenService;
    // }


    /**
     * Execute the console command.
     */
    // public function handle()
    // {
    //     $startTime = Carbon::now();
    //     Log::info('✅ ADialParticipantsCommand started at ' . Carbon::now());

    //     $timezone = config('app.timezone');
    //     $now = now()->timezone($timezone);
    //     $providers = ADialProvider::all();

    //     Log::info("Total providers found: " . $providers->count());

    //     $client = new Client();

    //     try {
    //         $token = $this->tokenService->getToken();
    //     } catch (\Exception $e) {
    //         Log::error("❌ Failed to retrieve token: " . $e->getMessage());
    //         return;
    //     }

    //     foreach ($providers as $provider) {
    //         Log::info("Processing provider: {$provider->extension}");
    //         $providerStartTime = Carbon::now();

    //         $files = ADialFeed::where('provider_id', $provider->id)
    //             ->whereDate('date', today())
    //             ->where('allow', true)
    //             ->get();

    //         foreach ($files as $file) {
    //             $from = Carbon::parse("{$file->date} {$file->from}")->timezone($timezone);
    //             $to = Carbon::parse("{$file->date} {$file->to}")->timezone($timezone);

    //             if (!$now->between($from, $to)) {
    //                 Log::info("❌ Skipping File ID {$file->id}, not in call window.");
    //                 continue;
    //             }

    //             // ✅ Fetch Active Calls (Only Per Provider)
    //             try {
    //                 $filter = "contains(Caller, '{$provider->extension}')";
    //                 $url = config('services.three_cx.api_url') . "/xapi/v1/ActiveCalls?\$filter=" . urlencode($filter);

    //                 $activeCallsResponse = $client->get($url, [
    //                     'headers' => [
    //                         'Authorization' => 'Bearer ' . $token,
    //                         'Accept' => 'application/json',
    //                     ],
    //                     'timeout' => 30,
    //                 ]);

    //                 if ($activeCallsResponse->getStatusCode() !== 200) {
    //                     Log::error("❌ Failed to fetch active calls. HTTP Status: " . $activeCallsResponse->getStatusCode());
    //                     continue;
    //                 }

    //                 $activeCalls = json_decode($activeCallsResponse->getBody()->getContents(), true);

    //                 if (empty($activeCalls['value'])) {
    //                     Log::warning("⚠️ No active calls found for provider {$provider->extension}");
    //                     continue;
    //                 }

    //                 foreach ($activeCalls['value'] as $call) {
    //                     $callStartTime = Carbon::now();

    //                     $callId = $call['Id'] ?? null;
    //                     $status = $call['Status'] ?? 'Unknown';

    //                     if (!$callId) {
    //                         Log::warning("⚠️ Missing Call ID in response");
    //                         continue;
    //                     }

    //                     try {
    //                         // Fetch existing call record
    //                         $existingRecord = AutoDailerReport::where('call_id', $callId)->first(['duration_time', 'duration_routing']);

    //                         $durationTime = $existingRecord->duration_time ?? null;
    //                         $durationRouting = $existingRecord->duration_routing ?? null;

    //                         if (isset($call['EstablishedAt'], $call['ServerNow'])) {
    //                             $establishedAt = Carbon::parse($call['EstablishedAt']);
    //                             $serverNow = Carbon::parse($call['ServerNow']);
    //                             $currentDuration = $establishedAt->diff($serverNow)->format('%H:%I:%S');

    //                             if ($status === 'Talking') {
    //                                 $durationTime = $currentDuration;
    //                             } elseif ($status === 'Routing') {
    //                                 $durationRouting = $currentDuration;
    //                             }
    //                         }

    //                         // Update database
    //                         AutoDailerReport::where('call_id', $callId)
    //                             ->update([
    //                                 'status' => $status,
    //                                 'duration_time' => $durationTime,
    //                                 'duration_routing' => $durationRouting,
    //                             ]);

    //                         ADialData::where('call_id', $callId)
    //                             ->update(['state' => $status]);

    //                         Log::info("✅ Updated Call: {$callId}, Status: {$status}, Talking Duration: {$durationTime}, Routing Duration: {$durationRouting}");
    //                     } catch (\Exception $e) {
    //                         Log::error("❌ Failed to update database for Call ID {$callId}: " . $e->getMessage());
    //                     }

    //                     $callEndTime = Carbon::now();
    //                     $callExecutionTime = $callStartTime->diffInMilliseconds($callEndTime);
    //                     Log::info("⏳ Execution time for call {$callId}: {$callExecutionTime} ms");
    //                 }
    //             } catch (\Exception $e) {
    //                 Log::error("❌ Failed to fetch active calls for provider {$provider->extension}: " . $e->getMessage());
    //                 continue;
    //             }
    //         }

    //         $providerEndTime = Carbon::now();
    //         $providerExecutionTime = $providerStartTime->diffInMilliseconds($providerEndTime);
    //         Log::info("⏳ Execution time for provider {$provider->extension}: {$providerExecutionTime} ms");
    //     }

    //     Log::info("✅ ADialParticipantsCommand execution completed.");
    // }



}
