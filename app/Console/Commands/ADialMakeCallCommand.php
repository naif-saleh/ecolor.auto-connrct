<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Models\ADialProvider;
use App\Models\ADialData;
use App\Models\ADialFeed;
use App\Models\General_Setting;
use App\Models\CountCalls;
use App\Services\ThreeCxService;
use App\Services\TokenService;
use Illuminate\Support\Facades\DB;
use App\Models\AutoDailerReport;
use DateTime;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;


class ADialMakeCallCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:ADial-make-call-command';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command to make auto dialer calls';
    protected $threeCxService;

    public function __construct(ThreeCxService $threeCxService)
    {
        parent::__construct();
        $this->threeCxService = $threeCxService;
    }


    // protected $tokenService;

    // public function __construct(TokenService $tokenService)
    // {
    //     parent::__construct();
    //     $this->tokenService = $tokenService;
    // }

    /**
     * Execute the console command.
     */
    // public function handle()
    // {
    //     Log::info('ADialMakeCallCommand started at ' . Carbon::now());

    //     $timezone = config('app.timezone');
    //     $callTimeStart = General_Setting::get('call_time_start');
    //     $callTimeEnd = General_Setting::get('call_time_end');

    //     if (!$callTimeStart || !$callTimeEnd) {
    //         Log::warning("⚠️ Call time settings not configured.");
    //         return;
    //     }

    //     $now = now()->timezone($timezone);
    //     $globalStart = Carbon::parse(date('Y-m-d') . ' ' . $callTimeStart)->timezone($timezone);
    //     $globalEnd = Carbon::parse(date('Y-m-d') . ' ' . $callTimeEnd)->timezone($timezone);

    //     if (!$now->between($globalStart, $globalEnd)) {
    //         Log::info('📞❌ Calls are not allowed at this time.');
    //         return;
    //     }

    //     Log::info("Allowed call window: {$globalStart} to {$globalEnd}");

    //     $providers = ADialProvider::all();
    //     Log::info("Found {$providers->count()} providers to process");

    //     foreach ($providers as $provider) {
    //         $files = ADialFeed::where('provider_id', $provider->id)
    //             ->whereDate('date', today())
    //             ->where('allow', true)
    //             ->get();

    //         Log::info("Found {$files->count()} feeds for provider {$provider->name}");

    //         foreach ($files as $file) {
    //             $from = Carbon::parse("{$file->date} {$file->from}")->timezone($timezone);
    //             $to = Carbon::parse("{$file->date} {$file->to}")->timezone($timezone);

    //             if (!$now->between($from, $to)) {
    //                 Log::info("❌ Skipping File ID {$file->id}, not in call window.");
    //                 continue;
    //             }

    //             $client = new Client();
    //             $callLimit = CountCalls::get('number_calls');

    //             try {
    //                 $token = $this->tokenService->getToken();
    //                 $activeCallsResponse = $client->get(config('services.three_cx.api_url') . "/xapi/v1/ActiveCalls", [
    //                     'headers' => ['Authorization' => 'Bearer ' . $token, 'Accept' => 'application/json'],
    //                     'timeout' => 10,
    //                 ]);

    //                 $activeCalls = json_decode($activeCallsResponse->getBody()->getContents(), true);
    //                 $currentCalls = count($activeCalls['value'] ?? []);
    //             } catch (\Throwable $e) {
    //                 Log::error("❌ Error fetching active calls: " . $e->getMessage());
    //                 continue;
    //             }

    //             $callsToMake = max(0, $callLimit - $currentCalls);
    //             if ($callsToMake <= 0) {
    //                 Log::info("📞 Call limit reached, skipping further calls.");
    //                 continue;
    //             }

    //             $feedData = ADialData::where('feed_id', $file->id)->where('state', 'new')->take($callsToMake)->get();
    //             foreach ($feedData as $data) {
    //                 if (!$now->between($from, $to)) {
    //                     Log::info("❌ Call window expired during execution.");
    //                     break;
    //                 }

    //                 for ($i = 0; $i < 3; $i++) {
    //                     try {
    //                         $response = $client->post(config('services.three_cx.api_url') . "/callcontrol/{$provider->extension}/makecall", [
    //                             'headers' => ['Authorization' => 'Bearer ' . $token, 'Accept' => 'application/json', 'Content-Type' => 'application/json'],
    //                             'json' => ['destination' => $data->mobile],
    //                             'timeout' => 10,
    //                         ]);

    //                         $responseData = json_decode($response->getBody()->getContents(), true);
    //                         if (!isset($responseData['result']['callid'])) {
    //                             Log::info("Missing call ID in response");
    //                         }

    //                         Log::info("✅ Call successful. Call ID: " . $responseData['result']['callid']);
    //                         AutoDailerReport::updateOrCreate(
    //                             ['call_id' => $responseData['result']['callid']],
    //                             ['status' => $responseData['result']['status'], 'provider' => $provider->name, 'extension' => $provider->extension, 'phone_number' => $data->mobile]
    //                         );

    //                         $data->update(['state' => $responseData['result']['status'], 'call_date' => now(), 'call_id' => $responseData['result']['callid']]);
    //                         Log::info("✅ Mobile Number Called and Saved Successfully: {$data->mobile}");
    //                         usleep(300000);
    //                         break;
    //                     } catch (RequestException $e) {
    //                         Log::error("❌ ADial MakeCall: Guzzle Request Failed: " . $e->getMessage());
    //                         $data->update(['state' => 'error', 'call_date' => now()]);
    //                     }
    //                 }
    //             }

    //             if (!ADialData::where('feed_id', $file->id)->where('state', 'new')->exists()) {
    //                 $file->update(['is_done' => true]);
    //                 Log::info("✅ All numbers called for File ID: {$file->id}");
    //             }
    //         }
    //     }

    //     Log::info('📞✅ ADialMakeCallCommand execution completed.');
    // }



    public function handle()
    {
        Log::info('ADialMakeCallCommand started at ' . Carbon::now());

        // Check call time constraints
        $timezone = config('app.timezone');
        $callTimeStart = General_Setting::get('call_time_start');
        $callTimeEnd = General_Setting::get('call_time_end');

        if (!$callTimeStart || !$callTimeEnd) {
            Log::warning("⚠️ Call time settings not configured.");
            return;
        }

        $now = now()->timezone($timezone);
        $globalStart = Carbon::parse(date('Y-m-d') . ' ' . $callTimeStart)->timezone($timezone);
        $globalEnd = Carbon::parse(date('Y-m-d') . ' ' . $callTimeEnd)->timezone($timezone);

        if (!$now->between($globalStart, $globalEnd)) {
            Log::info('📞❌ Calls are not allowed at this time.');
            return;
        }

        Log::info("Allowed call window: {$globalStart} to {$globalEnd}");

        // Process each provider
        $providers = ADialProvider::all();
        Log::info("Found {$providers->count()} providers to process");

        foreach ($providers as $provider) {
            $this->processProvider($provider, $now, $timezone);
        }

        Log::info('📞✅ ADialMakeCallCommand execution completed.');
    }

    protected function processProvider($provider, $now, $timezone)
    {
        $files = ADialFeed::where('provider_id', $provider->id)
            ->whereDate('date', today())
            ->where('allow', true)
            ->get();

        Log::info("Found {$files->count()} feeds for provider {$provider->name}");

        foreach ($files as $file) {
            $this->processFile($file, $provider, $now, $timezone);
        }
    }

    protected function processFile($file, $provider, $now, $timezone)
    {
        // Check time window for this file
        $from = Carbon::parse("{$file->date} {$file->from}")->timezone($timezone);
        $to = Carbon::parse("{$file->date} {$file->to}")->timezone($timezone);

        if (!$now->between($from, $to)) {
            Log::info("❌ Skipping File ID {$file->id}, not in call window.");
            return;
        }

        // Check current call limits
        $callLimit = CountCalls::get('number_calls');

        try {
            $activeCalls = $this->threeCxService->getAllActiveCalls();
            $currentCalls = count($activeCalls['value'] ?? []);

            Log::info("Current active calls: {$currentCalls}, Limit: {$callLimit}");
        } catch (\Throwable $e) {
            Log::error("❌ Error fetching active calls: " . $e->getMessage());
            return;
        }

        $callsToMake = max(0, $callLimit - $currentCalls);
        if ($callsToMake <= 0) {
            Log::info("📞 Call limit reached, skipping further calls.");
            return;
        }

        // Make calls
        $feedData = ADialData::where('feed_id', $file->id)
            ->where('state', 'new')
            ->take($callsToMake)
            ->get();

        Log::info("Making {$feedData->count()} calls for feed ID {$file->id}");

        foreach ($feedData as $data) {
            if (!$now->between($from, $to)) {
                Log::info("❌ Call window expired during execution.");
                break;
            }

            $this->makeCallWithRetries($data, $provider);
        }

        // Mark file as done if all calls processed
        if (!ADialData::where('feed_id', $file->id)->where('state', 'new')->exists()) {
            $file->update(['is_done' => true]);
            Log::info("✅ All numbers called for File ID: {$file->id}");
        }
    }

    protected function makeCallWithRetries($data, $provider, $maxRetries = 3)
    {
        for ($i = 0; $i < $maxRetries; $i++) {
            try {
                // Make the call
                $responseData = $this->threeCxService->makeCall(
                    $provider->extension,
                    $data->mobile
                );

                $callId = $responseData['result']['callid'];
                $status = $responseData['result']['status'];

                // Update both tables atomically
                try {
                    // Update call record
                    $this->threeCxService->updateCallRecord(
                        $callId,
                        $status,
                        $provider->name,
                        $provider->extension,
                        $data->mobile
                    );

                    // Update dial data
                    $data->update([
                        'state' => $status,
                        'call_date' => now(),
                        'call_id' => $callId
                    ]);

                    Log::info("✅ Mobile Number Called and Saved Successfully: {$data->mobile}, Call ID: {$callId}");
                } catch (\Exception $e) {
                    Log::error("❌ Failed to update database after call: " . $e->getMessage());
                    throw $e;
                }

                // Add a small delay between calls
                usleep(300000); // 300ms
                break; // Successfully made call, exit retry loop

            } catch (\Exception $e) {
                Log::error("❌ ADial MakeCall: Call Failed (Attempt " . ($i + 1) . "): " . $e->getMessage());

                if ($i == $maxRetries - 1) {
                    // Last retry failed, mark as error
                    $data->update([
                        'state' => 'error',
                        'call_date' => now()
                    ]);

                    Log::error("❌ Failed to make call after {$maxRetries} attempts: {$data->mobile}");
                }
            }
        }
    }




}
