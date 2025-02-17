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
use DateTime;
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
                $token = $this->tokenService->getToken();
                $client = new Client([
                    'base_uri' => config('services.three_cx.api_url'),
                    'headers' => ['Accept' => 'application/json'],
                ]);

                // ✅ Check active calls for agent
                try {
                    $participantResponse = $client->get("/callcontrol/{$agent->extension}/participants/", [
                        'headers' => ['Authorization' => "Bearer $token"],
                        'timeout' => 10
                    ]);
                } catch (\GuzzleHttp\Exception\RequestException $e) {
                    Log::error("API Request Failed: " . $e->getMessage());
                    return response()->json(['error' => 'Unable to fetch participants'], 500);
                }

                $participants = json_decode($participantResponse->getBody(), true);
                Log::info("📞 3CX API Response: " . print_r($participants, true));

                // ✅ Check if agent is currently in a call
                $isBusy = false;
                foreach ($participants as $p) {
                    if (isset($p['status']) && in_array($p['status'], ['Connected', 'Dialing', 'Ringing'])) {
                        $isBusy = true;
                        break;
                    }
                }

                if ($isBusy) {
                    Log::info("⚠️ Agent {$agent->id} ({$agent->extension}) is currently in a call.");
                    continue;
                }

                if ($agent->status !== "Available") {
                    Log::info("⏳ Agent {$agent->id} ({$agent->extension}) is not available - Status: {$agent->status}");
                    continue;
                }

                // ✅ Lock agent to prevent multiple processes
                $lockKey = "agent_call_lock_{$agent->id}";
                if (!Cache::add($lockKey, true, now()->addSeconds(10))) {
                    Log::info("🚫 Agent {$agent->id} ({$agent->extension}) is locked by another process");
                    continue;
                }

                try {
                    // ✅ Get eligible feeds
                    $feeds = ADistFeed::where('agent_id', $agent->id)
                        ->whereDate('date', today())
                        ->where('allow', true)
                        ->where('is_done', false)
                        ->get();

                    foreach ($feeds as $feed) {
                        $from = Carbon::parse("{$feed->date} {$feed->from}")->timezone($timezone);
                        $to = Carbon::parse("{$feed->date} {$feed->to}")->timezone($timezone);
                        $now = now()->timezone($timezone);
                        Log::info("ADIAL Processing window for File ID {$feed->id}:");
                        Log::info("Current time ({$timezone}): " . $now);
                        Log::info("Call window: {$from} to {$to}");

                        if ($now()->between($from, $to)) {
                            // ✅ Get one new call
                            $feedData = ADistData::where('feed_id', $feed->id)
                                ->where('state', 'new')
                                ->lockForUpdate()
                                ->first();

                            if (!$feedData) {
                                Log::info("✅ No available call data for feed {$feed->id}");
                                continue;
                            }

                            try {
                                // ✅ Get agent devices
                                $devicesResponse = $client->get("/callcontrol/{$agent->extension}/devices", [
                                    'headers' => ['Authorization' => "Bearer $token"],
                                    'timeout' => 2
                                ]);

                                $dnDevices = json_decode($devicesResponse->getBody(), true);

                                foreach ($dnDevices as $device) {
                                    if ($device['user_agent'] !== '3CX Mobile Client') continue;

                                    // ✅ Make the call
                                    $responseState = $client->post("/callcontrol/{$agent->extension}/devices/{$device['device_id']}/makecall", [
                                        'headers' => ['Authorization' => "Bearer $token"],
                                        'json' => ['destination' => $feedData->mobile],
                                        'timeout' => 2
                                    ]);

                                    $responseData = json_decode($responseState->getBody(), true);

                                    // ✅ Update records in a transaction
                                    DB::transaction(function () use ($responseData, $feedData) {
                                        AutoDistributerReport::create([
                                            'call_id' => $responseData['result']['callid'],
                                            'status' => "Initiating",
                                            'provider' => $feedData->file->file_name,
                                            'extension' => $responseData['result']['dn'],
                                            'phone_number' => $responseData['result']['party_caller_id'],
                                            'attempt_time' => now(),
                                        ]);

                                        $feedData->update([
                                            'state' => "Initiating",
                                            'call_date' => now(),
                                            'call_id' => $responseData['result']['callid'],
                                        ]);
                                    });
                                }
                            } catch (RequestException $e) {
                                Log::error("❌ Call failed for {$feedData->mobile}: " . $e->getMessage());
                            }
                        }
                    }

                    // ✅ Mark feeds as done if no more new calls
                    ADistFeed::where('agent_id', $agent->id)
                        ->whereDate('date', today())
                        ->whereNotExists(function ($query) {
                            $query->from('a_dist_data')
                                ->whereColumn('feed_id', 'a_dist_feeds.id')
                                ->where('state', 'new');
                        })
                        ->update(['is_done' => true]);
                } finally {
                    Cache::forget($lockKey);
                }
            } catch (\Exception $e) {
                Log::error("❌ General error: " . $e->getMessage());
            }
        }
    }
}
