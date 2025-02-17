<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Models\ADialProvider;
use App\Models\ADialData;
use App\Models\ADialFeed;
use App\Models\General_Setting;
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
        $tokenService = $tokenService;
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

        $providers = ADialProvider::all();

        foreach ($providers as $provider) {
            $files = ADialFeed::where('provider_id', $provider->id)
                ->whereDate('date', today())
                ->where('allow', true)
                ->get();

            $callTimeStart = General_Setting::get('call_time_start');
            $callTimeEnd = General_Setting::get('call_time_end');

            // Check if settings exist - do this once before processing any feeds
            if (!$callTimeStart || !$callTimeEnd) {
                Log::warning("⚠️ Call time settings not configured. Please visit the settings page to set up allowed call hours.");
                return;
            }

            $globalTodayStart = Carbon::parse(date('Y-m-d') . ' ' . $callTimeStart)->timezone($timezone);
            $globalTodayEnd = Carbon::parse(date('Y-m-d') . ' ' . $callTimeEnd)->timezone($timezone);

            // Get current time once
            $now = now()->timezone($timezone);

            // Check if current time is within global allowed call hours
            if (!$now->between($globalTodayStart, $globalTodayEnd)) {
                Log::info("⏱️ ADist - Current time {$now} is outside allowed call hours ({$callTimeStart} - {$callTimeEnd}). Exiting.");
                return;
            }

            foreach ($files as $file) {
                // Parse times using configured timezone
                $from = Carbon::parse("{$file->date} {$file->from}")->timezone($timezone);
                $to = Carbon::parse("{$file->date} {$file->to}")->timezone($timezone);
                $now = now()->timezone($timezone);

                Log::info("ADIAL Processing window for File ID {$file->id}:");
                Log::info("Current time ({$timezone}): " . $now);
                Log::info("Call window: {$from} to {$to}");

                if ($now->between($from, $to)) {
                    Log::info("ADIAL ✅ File ID {$file->id} is within range, processing calls...");



                    ADialData::where('feed_id', $file->id)
                        ->where('state', 'new')
                        ->chunk(50, function ($dataBatch) use ($provider) {
                            // Create a Guzzle client
                            $client = new Client([
                                'base_uri' => config('services.three_cx.api_url'),
                                'timeout' => 30,
                            ]);

                            foreach ($dataBatch as $feedData) {
                                try {
                                    $token = $this->tokenService->getToken();
                                    Log::info("Calling API for extension: " . $provider->extension);

                                    // Make POST request with Guzzle
                                    $response = $client->request('POST', "/callcontrol/{$provider->extension}/makecall", [
                                        'headers' => [
                                            'Authorization' => 'Bearer ' . $token,
                                        ],
                                        'json' => [
                                            'destination' => $feedData->mobile,
                                        ],
                                    ]);

                                    if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
                                        $responseData = json_decode($response->getBody(), true);
                                        Log::info("dadad call id " . $responseData['result']['callid'] . ' mobile ' . $feedData->mobile);

                                        AutoDailerReport::updateOrCreate(
                                            ['call_id' => $responseData['result']['callid']],
                                            [
                                                'status' => $responseData['result']['status'],
                                                'provider' => $responseData['result']['dn'],
                                                'extension' => $provider->name,
                                                'phone_number' => $feedData->mobile,
                                            ]
                                        );

                                        $feedData->update([
                                            'state' => $responseData['result']['status'],
                                            'call_date' => now(),
                                            'call_id' => $responseData['result']['callid'],
                                        ]);

                                        Log::info("📞✅ Call successful for: " . $feedData->mobile);
                                    } else {
                                        Log::error("❌ Failed call: " . $feedData->mobile);
                                    }

                                    $providers = ADialProvider::all();
                                    foreach ($providers as $provider) {
                                        $ext_from = $provider->extension;

                                        try {
                                            // Make GET request with Guzzle
                                            $response = $client->request('GET', "/callcontrol/{$ext_from}/participants", [
                                                'headers' => [
                                                    'Authorization' => 'Bearer ' . $token,
                                                ],
                                            ]);

                                            if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
                                                $participants = json_decode($response->getBody(), true);

                                                if (empty($participants)) {
                                                    Log::warning("⚠️ No participants found for extension {$ext_from}");
                                                    continue;
                                                }

                                                Log::info("✅ Auto Dialer Participants Response: " . print_r($participants, true));

                                                foreach ($participants as $participant_data) {
                                                    try {
                                                        Log::info("✅ Auto Dialer Participants Response: " . print_r($participants, true));
                                                        $filter = "contains(Caller, '{$participant_data['dn']}')";
                                                        $url = "/xapi/v1/ActiveCalls?\$filter=" . urlencode($filter);

                                                        // Make GET request for active calls with Guzzle
                                                        $activeCallsResponse = $client->request('GET', $url, [
                                                            'headers' => [
                                                                'Authorization' => 'Bearer ' . $token,
                                                            ],
                                                        ]);

                                                        if ($activeCallsResponse->getStatusCode() >= 200 && $activeCallsResponse->getStatusCode() < 300) {
                                                            $activeCalls = json_decode($activeCallsResponse->getBody(), true);
                                                            Log::info("✅ Dial:Active Calls Response: " . print_r($activeCalls, true));

                                                            foreach ($activeCalls['value'] as $call) {
                                                                $status = $call['Status'];
                                                                $callId = $call['Id'];

                                                                // Initialize duration variables
                                                                $durationTime = null;
                                                                $durationRouting = null;

                                                                // Then update only the appropriate duration based on current status
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

                                                                // Transaction to update database
                                                                DB::beginTransaction();
                                                                try {
                                                                    AutoDailerReport::where('call_id', $callId)
                                                                        ->update([
                                                                            'status' => $status,
                                                                            'duration_time' => $durationTime,
                                                                            'duration_routing' => $durationRouting
                                                                        ]);

                                                                    ADialData::where('call_id', $callId)
                                                                        ->update(['state' => $status]);

                                                                    Log::info("ADilaParticipantsCommand ✅ Mobile status: {$status}, Mobile: " . $call['Callee']);

                                                                    DB::commit();
                                                                } catch (\Exception $e) {
                                                                    DB::rollBack();
                                                                    Log::error("ADilaParticipantsCommand ❌ Transaction Failed for call ID {$callId}: " . $e->getMessage());
                                                                }
                                                            }
                                                        } else {
                                                            Log::error("❌ Failed to fetch active calls. Response: " . $activeCallsResponse->getBody());
                                                        }
                                                    } catch (RequestException $e) {
                                                        Log::error("❌ Failed to process participant data for call ID " . ($participant_data['callid'] ?? 'N/A') . ": " . $e->getMessage());
                                                    }
                                                }
                                            } else {
                                                Log::error("Failed to fetch participants. HTTP Status: {$response->getStatusCode()}");
                                            }
                                        } catch (RequestException $e) {
                                            Log::error("❌ Failed fetching participants for provider {$ext_from}: " . $e->getMessage());
                                        }
                                    }
                                } catch (RequestException $e) {
                                    Log::error("❌ Guzzle Exception: " . $e->getMessage());
                                } catch (\Exception $e) {
                                    Log::error("❌ General Exception: " . $e->getMessage());
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

        Log::info('📞✅ ADialMakeCallCommand execution completed at ' . now());
    }
}
