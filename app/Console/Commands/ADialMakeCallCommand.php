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
                    $feed_data = ADialData::where('feed_id', $file->id)->where('state', 'new');
                    foreach ($feed_data as $data) {
                        $client = new Client();
                        try {
                            $token = $this->tokenService->getToken();
                            Log::info("Calling API for extension: " . $provider->extension);

                            $response = $client->post(config('services.three_cx.api_url') . "/callcontrol/{$provider->extension}/makecall", [
                                'headers' => [
                                    'Authorization' => 'Bearer ' . $token,
                                    'Accept' => 'application/json',
                                    'Content-Type' => 'application/json',
                                ],
                                'json' => [
                                    'destination' => $data->mobile,
                                ],
                                'timeout' => 10,
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
                            } else {
                                Log::warning("⚠️ Call response received, but missing call ID. Response: " . json_encode($responseData));
                            }

                        } catch (RequestException $e) {
                            Log::error("❌ Guzzle Request Failed: " . $e->getMessage());
                            if ($e->hasResponse()) {
                                Log::error("Response: " . $e->getResponse()->getBody()->getContents());
                            }
                        }
                    }


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
