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

        $agents = ADistAgent::all();
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
                    $participants = json_decode($participantResponse->getBody(), true);
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
                        ->whereDate('date', today())
                        ->where('allow', true)
                        ->where('is_done', false)
                        ->get();

                    foreach ($feeds as $feed) {
                        $from = Carbon::parse("{$feed->date} {$feed->from}", $timezone);
                        $to = Carbon::parse("{$feed->date} {$feed->to}", $timezone);

                        if (!$now->between($from, $to)) continue;

                        // ✅ Fetch call data
                        $feedData = ADistData::where('feed_id', $feed->id)
                            ->where('state', 'new')
                            ->lockForUpdate()
                            ->first();
                        if (!$feedData) continue;

                        // ✅ Fetch agent devices
                        try {
                            $devicesResponse = $client->get("/callcontrol/{$agent->extension}/devices", [
                                'headers' => ['Authorization' => "Bearer $token"],
                                'timeout' => 2
                            ]);
                            $devices = json_decode($devicesResponse->getBody(), true);
                        } catch (RequestException $e) {
                            Log::error("❌ Device fetch failed: " . $e->getMessage());
                            continue;
                        }

                        foreach ($devices as $device) {
                            if ($device['user_agent'] !== '3CX Mobile Client') continue;

                            // ✅ Make Call
                            try {
                                $response = $client->post("/callcontrol/{$agent->extension}/devices/{$device['device_id']}/makecall", [
                                    'headers' => ['Authorization' => "Bearer $token"],
                                    'json' => ['destination' => $feedData->mobile],
                                    'timeout' => 2
                                ]);
                                $callResponse = json_decode($response->getBody(), true);

                                // ✅ Update DB
                                DB::transaction(function () use ($callResponse, $feedData) {
                                    AutoDistributerReport::create([
                                        'call_id' => $callResponse['result']['callid'],
                                        'status' => "Initiating",
                                        'phone_number' => $callResponse['result']['party_caller_id'],
                                        'attempt_time' => now(),
                                    ]);
                                    $feedData->update(['state' => "Initiating", 'call_id' => $callResponse['result']['callid']]);
                                });
                            } catch (RequestException $e) {
                                Log::error("❌ Call failed: " . $e->getMessage());
                            }
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
