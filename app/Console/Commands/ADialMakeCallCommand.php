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
use App\Jobs\MakeCallJob;
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

    protected $tokenService;

    public function __construct(TokenService $tokenService)
    {
        parent::__construct();
        $this->tokenService = $tokenService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        Log::info('ADialMakeCallCommand executed at ' . Carbon::now());

        // Get timezone from configuration
        $timezone = config('app.timezone');
        Log::info("Using timezone: {$timezone}");

        // Get call time settings once before processing
        $callTimeStart = General_Setting::get('call_time_start');
        $callTimeEnd = General_Setting::get('call_time_end');

        // Check if settings exist
        if (!$callTimeStart || !$callTimeEnd) {
            Log::warning("⚠️ Call time settings not configured. Please visit the settings page to set up allowed call hours.");
            return;
        }

        // Get current time once in the configured timezone
        $now = now()->timezone($timezone);
        $globalTodayStart = Carbon::parse(date('Y-m-d') . ' ' . $callTimeStart)->timezone($timezone);
        $globalTodayEnd = Carbon::parse(date('Y-m-d') . ' ' . $callTimeEnd)->timezone($timezone);
        $shouldStopProcessing = false;
        // Check if current time is within global allowed call hours
        if ($now->between($globalTodayStart, $globalTodayEnd)) {
            Log::info("GlobbalTodayStart: " . $globalTodayStart . " globalTodayEnd: ".$globalTodayEnd);
            $providers = ADialProvider::all();
            Log::info("Found " . $providers->count() . " providers to process");

            foreach ($providers as $provider) {
                $files = ADialFeed::where('provider_id', $provider->id)
                    ->whereDate('date', today())
                    ->where('allow', true)
                    ->get();

                Log::info("Found " . $files->count() . " feeds for provider " . $provider->name);

                foreach ($files as $file) {
                    // Parse times using configured timezone
                    $from = Carbon::parse("{$file->date} {$file->from}")->timezone($timezone);
                    $to = Carbon::parse("{$file->date} {$file->to}")->timezone($timezone);

                    Log::info("ADIAL Processing window for File ID {$file->id}:");
                    Log::info("Current time ({$timezone}): " . $now);
                    Log::info("Call window: {$from} to {$to}");

                    if ($now->between($from, $to)) {

                        Log::info("ADIAL ✅ File ID {$file->id} is within range, processing calls...");

                        $client = new Client();
                        $callCount = CountCalls::get('number_calls');
                        ADialData::where('feed_id', $file->id)
                            ->where('state', 'new')
                            ->chunk($callCount, function ($feed_data) use ($provider, $client, &$shouldStopProcessing) {
                                if ($shouldStopProcessing) {
                                    Log::info("❌ Stopping processing due to time condition update.");
                                    return false;
                                }
                                foreach ($feed_data as $data) {
                                    try {
                                        Log::info("ADIAL EXT: " . $provider->extension . " mobile: " . $data->mobile);
                                        $token = $this->tokenService->getToken();
                                        Log::info("ADial Calling API for extension: " . $provider->extension . "Mobile: ".$data->mobile);
                                        //Log::info("ADial GlobbalTodayStart: " . $globalTodayStart . " globalTodayEnd: ".$globalTodayEnd);
                                        $response = $client->post(config('services.three_cx.api_url') . "/callcontrol/{$provider->extension}/makecall", [
                                            'headers' => [
                                                'Authorization' => 'Bearer ' . $token,
                                                'Accept' => 'application/json',
                                                'Content-Type' => 'application/json',
                                            ],
                                            'json' => [
                                                'destination' => $data->mobile,
                                            ],
                                            'timeout' => 20,
                                        ]);
                                        $responseData = json_decode($response->getBody()->getContents(), true);

                                        if (isset($responseData['result']['callid'])) {
                                            Log::info("✅ Call successful. Call ID: " . $responseData['result']['callid']);
                                            AutoDailerReport::updateOrCreate(
                                                ['call_id' => $responseData['result']['callid']],
                                                [
                                                    'status' => $responseData['result']['status'],
                                                    'provider' => $provider->name,
                                                    'extension' => $provider->extension,
                                                    'phone_number' => $data->mobile,
                                                ]
                                            );
                                            $data->update([
                                                'state' => $responseData['result']['status'],
                                                'call_date' => now(),
                                                'call_id' => $responseData['result']['callid'],
                                            ]);

                                            Log::info("📞✅ Call successful for: " . $data->mobile);
                                            sleep(2);
                                        } else {
                                            Log::warning("⚠️ Call response received, but missing call ID. Response: " . json_encode($responseData));
                                            $data->update([
                                                'state' => 'failed',
                                                'call_date' => now(),
                                            ]);
                                        }
                                    } catch (RequestException $e) {
                                        Log::error("❌ Guzzle Request Failed: " . $e->getMessage());
                                        if ($e->hasResponse()) {
                                            Log::error("Response: " . $e->getResponse()->getBody()->getContents());
                                        }
                                        $data->update([
                                            'state' => 'error',
                                            'call_date' => now(),
                                        ]);
                                    }



                                    // ADial Partisipant
                                    try {
                                        $token = $this->tokenService->getToken();

                                        $responseState = $client->get(config('services.three_cx.api_url') . "/callcontrol/{$provider->extension}/participants", [
                                            'headers' => [
                                                'Authorization' => 'Bearer ' . $token,
                                                'Accept' => 'application/json',
                                            ],
                                            'timeout' => 20,
                                        ]);

                                        if ($responseState->getStatusCode() !== 200) {
                                            Log::error("❌ Failed to fetch participants even after token refresh. HTTP Status: {$responseState->getStatusCode()}");
                                            return;
                                        }

                                        $participants = json_decode($responseState->getBody()->getContents(), true);

                                        if (empty($participants)) {
                                            Log::warning("⚠️ No participants found for extension {$provider->extension}");
                                            return;
                                        }

                                        Log::info("✅ Auto Dialer Participants Response: " . print_r($participants, true));

                                        foreach ($participants as $participant_data) {
                                            try {
                                                Log::info("✅ Processing participant: " . json_encode($participant_data));

                                                $filter = "contains(Caller, '{$participant_data['dn']}')";
                                                $url = config('services.three_cx.api_url') . "/xapi/v1/ActiveCalls?\$filter=" . urlencode($filter);

                                                $activeCallsResponse = $client->get($url, [
                                                    'headers' => [
                                                        'Authorization' => 'Bearer ' . $token,
                                                        'Accept' => 'application/json',
                                                    ],
                                                    'timeout' => 10,
                                                ]);

                                                if ($activeCallsResponse->getStatusCode() === 200) {
                                                    $activeCalls = json_decode($activeCallsResponse->getBody()->getContents(), true);
                                                    Log::info("✅ Active Calls Response: " . print_r($activeCalls, true));

                                                    foreach ($activeCalls['value'] as $call) {
                                                        $status = $call['Status'];
                                                        $callId = $call['Id'];

                                                        // Parse call duration
                                                        $durationTime = null;
                                                        $durationRouting = null;

                                                        if (isset($call['EstablishedAt']) && isset($call['ServerNow'])) {
                                                            $establishedAt = new DateTime($call['EstablishedAt']);
                                                            $serverNow = new DateTime($call['ServerNow']);
                                                            $duration = $establishedAt->diff($serverNow)->format('%H:%I:%S');

                                                            if ($status === 'Talking') {
                                                                $durationTime = $duration;
                                                            } elseif ($status === 'Routing') {
                                                                $durationRouting = $duration;
                                                            }
                                                        }

                                                        // Database Transaction
                                                        DB::beginTransaction();
                                                        try {
                                                            AutoDailerReport::where('call_id', $callId)
                                                                ->update([
                                                                    'status' => $status,
                                                                    'duration_time' => $durationTime,
                                                                    'duration_routing' => $durationRouting,
                                                                ]);

                                                            ADialData::where('call_id', $callId)
                                                                ->update(['state' => $status]);

                                                            Log::info("✅ Call Updated: Status: {$status}, Mobile: " . $call['Callee']);

                                                            DB::commit();
                                                        } catch (\Exception $e) {
                                                            DB::rollBack();
                                                            Log::error("❌ Transaction Failed for Call ID {$callId}: " . $e->getMessage());
                                                        }
                                                    }
                                                } else {
                                                    Log::error("❌ Failed to fetch active calls. HTTP Status: " . $activeCallsResponse->getStatusCode());
                                                }
                                            } catch (\Exception $e) {
                                                Log::error("❌ Failed to process participant data: " . $e->getMessage());
                                            }
                                        }
                                    } catch (\Exception $e) {
                                        Log::error("❌ Failed fetching participants for provider {$provider->extension}: " . $e->getMessage());
                                    }
                                }
                            });






                        if (!ADialData::where('feed_id', $file->id)->where('state', 'new')->exists()) {
                            $file->update(['is_done' => true]);
                            Log::info("ADIAL ✅✅✅ All numbers called for File ID: {$file->id}");
                        }
                    } else {
                        Log::info("ADIAL ❌ File ID {$file->id} is NOT within range.");
                        Log::info("Current time: " . $now->format('Y-m-d H:i:s'));
                        Log::info("Window: {$from->format('Y-m-d H:i:s')} - {$to->format('Y-m-d H:i:s')}");
                    }
                }
            }
        } else {
            Log::info("⏱️ ADist - Current time {$now} is outside allowed call hours ({$callTimeStart} - {$callTimeEnd}). Exiting.");
        }
        $shouldStopProcessing = true;
        Log::info('📞✅ ADialMakeCallCommand execution completed at ' . now());
    }
}
