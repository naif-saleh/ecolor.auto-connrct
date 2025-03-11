<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\ADistAgent;
use App\Models\ADistFeed;
use App\Models\ADistData;
use App\Models\AutoDistributerReport;
use App\Services\TokenService;
use App\Models\General_Setting;
use Illuminate\Support\Facades\Cache;

class ADistMakeCallCommand extends Command
{
    protected $signature = 'app:ADist-make-call-command';
    protected $description = 'Initiate auto-distributor calls';
    protected $tokenService;

    public function __construct(TokenService $tokenService)
    {
        parent::__construct();
        $this->tokenService = $tokenService;
    }

    public function handle()
    {
        Log::info('ADistMakeCallCommand executed at ' . Carbon::now());
        $timezone = config('app.timezone');
        Log::info("Using timezone: {$timezone}");

        $agents = ADistAgent::whereHas('files', function ($query) {
            $query->whereDate('date', today())->where('allow', true);
        })->get();

        foreach ($agents as $agent) {

            try {
                // ✅ Fetch API Token
                $token = $this->tokenService->getToken();
                if (!$token) {
                    Log::error("❌ Failed to retrieve API token.");
                    continue;
                }

                // ✅ Setup API Client
                $client = new Client([
                    'base_uri' => config('services.three_cx.api_url'),
                    'headers' => ['Accept' => 'application/json'],
                ]);

                // ✅ Check if agent is in a call
                try {
                    $participantResponse = $client->get("/callcontrol/{$agent->extension}/participants/", [
                        'headers' => ['Authorization' => "Bearer $token"],
                        'timeout' => 10
                    ]);
                    // $participants = json_decode($participantResponse->getBody(), true);
                } catch (\GuzzleHttp\Exception\RequestException $e) {
                    Log::error("API Request Failed: " . $e->getMessage());
                    continue;
                }

                $isBusy = collect($participants)->contains(fn($p) => isset($p['status']) && in_array($p['status'], ['Connected', 'Dialing', 'Ringing']));
                if ($isBusy) {
                    Log::info("⚠️ Agent {$agent->id} ({$agent->extension}) is currently in a call.");
                    continue;
                }

                if ($agent->status !== "Available") {
                    Log::info("⏳ Agent {$agent->id} ({$agent->extension}) is not available - Status: {$agent->status}");
                    continue;
                }

                // ✅ Lock agent
                $lockKey = "agent_call_lock_{$agent->id}";
                if (!Cache::add($lockKey, true, now()->addSeconds(10))) {
                    Log::info("🚫 Agent {$agent->id} is locked by another process");
                    continue;
                }

                try {

                    // ✅ Fetch call time settings
                    $callTimeStart = General_Setting::get('call_time_start');
                    $callTimeEnd = General_Setting::get('call_time_end');
                    if (!$callTimeStart || !$callTimeEnd) {
                        Log::warning("⚠️ Call time settings missing. Configure allowed call hours.");
                        continue;
                    }

                    $globalTodayStart = Carbon::parse(today()->format('Y-m-d') . " $callTimeStart", $timezone);
                    $globalTodayEnd = Carbon::parse(today()->format('Y-m-d') . " $callTimeEnd", $timezone);
                    $now = now()->timezone($timezone);

                    if (!$now->between($globalTodayStart, $globalTodayEnd)) {
                        Log::info("⏱️ Current time {$now} is outside allowed call hours.");
                        continue;
                    }


                    // ✅ Fetch Feeds
                    $feeds = ADistFeed::where('agent_id', $agent->id)
                        ->where('allow', true)
                        ->where('is_done', false)
                        ->whereDate('date', today())
                        ->get();

                    Log::info("Fetched Feeds for Agent {$agent->id}: " . print_r($feeds->toArray(), true));

                    foreach ($feeds as $feed) {
                        Log::info("Found {$feeds->count()} feeds for agent {$feed->agent->extension}");

                        $from = Carbon::parse("{$feed->date} {$feed->from}", $timezone);
                        $to = Carbon::parse("{$feed->date} {$feed->to}", $timezone);

                        // Handle overnight case: if 'to' is before 'from', assume it's on the next day
                        if ($to->lessThanOrEqualTo($from)) {
                            $to->addDay();
                        }

                        Log::info("Time Window for Feed {$feed->id}: {$from->toDateTimeString()} - {$to->toDateTimeString()}, Current Time: " . now()->timezone($timezone)->toDateTimeString());

                        // Check if current time is within the call window
                        if (!$now->between($from, $to)) {
                            Log::info("🚫 Feed {$feed->id} is NOT within the allowed time range.");
                            continue;
                        }


                        // ✅ Fetch call data
                        $feedData = ADistData::where('feed_id', $feed->id)
                            ->where('state', 'new')
                            ->get();

                        Log::info("📞 Call Data for Feed {$feed->id}: " . print_r($feedData->toArray(), true));

                        if ($feedData->isEmpty()) {
                            Log::info("✅ No available call data for feed {$feed->id}");
                            continue;
                        }

                        // ✅ Fetch agent devices
                        try {
                            $devicesResponse = $client->get("/callcontrol/{$agent->extension}/devices", [
                                'headers' => ['Authorization' => "Bearer $token"],
                                'timeout' => 10
                            ]);
                            $devices = json_decode($devicesResponse->getBody(), true);
                            Log::info("📱 Agent Devices: " . print_r($devices, true));
                        } catch (RequestException $e) {
                            Log::error("❌ Device fetch failed: " . $e->getMessage());
                            continue;
                        }

                        // Find the right device
                        $mobileDevice = null;
                        foreach ($devices as $device) {
                            if ($device['user_agent'] === '3CX Mobile Client') {
                                $mobileDevice = $device;
                                break;
                            }
                        }

                        if (!$mobileDevice) {
                            Log::error("❌ No 3CX Mobile Client device found for agent {$agent->extension}");
                            continue;
                        }

                        // Process each number in the feed data
                        foreach ($feedData as $dataItem) {
                            // Check if agent is still available before each call
                            if ($agent->status !== "Available") {
                                Log::info("⏳ Agent {$agent->id} ({$agent->extension}) is no longer available - Status: {$agent->status}");
                                break; // Stop processing numbers if agent becomes unavailable
                            }

                            Log::info("☎️ Attempting call to {$dataItem->mobile} using Device ID: {$mobileDevice['device_id']}");

                            try {
                                $response = $client->post("/callcontrol/{$agent->extension}/devices/{$mobileDevice['device_id']}/makecall", [
                                    'headers' => ['Authorization' => "Bearer $token"],
                                    'json' => ['destination' => $dataItem->mobile],
                                    'timeout' => 10
                                ]);
                                $callResponse = json_decode($response->getBody(), true);

                                Log::info("📞 Call Initiated Response: " . print_r($callResponse, true));

                                // ✅ Update DB for this specific number
                                DB::transaction(function () use ($callResponse, $dataItem, $agent, $feed) {
                                    AutoDistributerReport::create([
                                        'call_id' => $callResponse['result']['callid'],
                                        'status' => "Initiating",
                                        'extension' => $agent->extension,
                                        'phone_number' => $callResponse['result']['party_caller_id'],
                                        'provider' => $feed->file_name,
                                        'attempt_time' => now(),
                                    ]);
                                    $dataItem->update(['state' => "Initiating", 'call_id' => $callResponse['result']['callid']]);
                                });

                                // Break after successful call to avoid multiple simultaneous calls
                                // If you want to call all numbers in sequence, remove this break
                                break;

                            } catch (RequestException $e) {
                                Log::error("❌ Call to {$dataItem->mobile} failed: " . $e->getMessage());
                                // Update state to indicate call attempt failed
                                $dataItem->update(['state' => "Failed"]);
                            }

                            // Add a delay between call attempts if needed
                            sleep(2);
                        }
                    }
                } finally {
                    Cache::forget($lockKey);
                }
            } catch (\Exception $e) {
                Log::error("❌ General error: " . $e->getMessage());
            }
        }
    }
}
