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

        try {
            $client = new Client([
                'base_uri' => config('services.three_cx.api_url'),
                'headers' => [
                    'Accept' => 'application/json'
                ],
            ]);

            $token = $this->tokenService->getToken();

            // Get active calls with minimal delay
            try {
                $activeResponse = $client->get('/xapi/v1/ActiveCalls', [
                    'headers' => ['Authorization' => "Bearer $token"],
                    'timeout' => 3 // Reduce timeout to speed up checks
                ]);

                $activeCalls = json_decode($activeResponse->getBody(), true);

                // Only check very recent pending calls (last 30 seconds)
                $pendingCalls = AutoDistributerReport::where('status', 'Initiating')
                    ->where('created_at', '>=', now()->subSeconds(60))
                    ->get();

            } catch (RequestException $e) {
                Log::error("ADistMakeCallCommand ❌ Failed to fetch call status: " . $e->getMessage());
                return;
            }

            $activeCallsList = $activeCalls['value'] ?? [];

            // Create quick lookup maps for efficiency
            $busyAgents = [];
            foreach ($activeCallsList as $call) {
                $busyAgents[$call['Caller']] = true;
            }

            foreach ($pendingCalls as $call) {
                $busyAgents[$call->extension] = true;
            }

            // Get only available agents
            $agents = ADistAgent::where('status', 'Available')->get();

            foreach ($agents as $agent) {
                if (isset($busyAgents[$agent->extension])) {
                    continue;
                }

                // Quick check for very recent pending calls
                $hasPendingCall = ADistData::where('state', 'Initiating')
                    ->where('updated_at', '>=', now()->subSeconds(60))
                    ->whereExists(function ($query) use ($agent) {
                        $query->from('a_dist_feeds')
                            ->whereColumn('a_dist_feeds.id', 'a_dist_data.feed_id')
                            ->where('a_dist_feeds.agent_id', $agent->id);
                    })
                    ->exists();

                if ($hasPendingCall) {
                    continue;
                }

                // Use a shorter lock time
                $lockKey = "agent_call_lock_{$agent->id}";
                if (!Cache::add($lockKey, true, now()->addSeconds(10))) {
                    continue;
                }

                try {
                    // Find eligible feeds with minimal query time
                    $feeds = ADistFeed::where('agent_id', $agent->id)
                        ->whereDate('date', today())
                        ->where('allow', true)
                        ->where('is_done', false)  // Only get feeds that aren't done
                        ->get();

                    foreach ($feeds as $feed) {
                        $from = Carbon::parse("{$feed->date} {$feed->from}")->subHours(3);
                        $to = Carbon::parse("{$feed->date} {$feed->to}")->subHours(3);

                        if (!now()->between($from, $to)) {
                            continue;
                        }

                        // Get one new call efficiently
                        $feedData = ADistData::where('feed_id', $feed->id)
                            ->where('state', 'new')
                            ->lockForUpdate()  // Prevent race conditions
                            ->first();

                        if (!$feedData) {
                            continue;
                        }

                        try {
                            // Quick device check
                            $devicesResponse = $client->get("/callcontrol/{$agent->extension}/devices", [
                                'headers' => ['Authorization' => "Bearer $token"],
                                'timeout' => 2
                            ]);

                            $dnDevices = json_decode($devicesResponse->getBody(), true);

                            foreach ($dnDevices as $device) {
                                if ($device['user_agent'] !== '3CX Mobile Client') continue;

                                // Make the call with minimal delay
                                $responseState = $client->post("/callcontrol/{$agent->extension}/devices/{$device['device_id']}/makecall", [
                                    'headers' => ['Authorization' => "Bearer $token"],
                                    'json' => ['destination' => $feedData->mobile],
                                    'timeout' => 2
                                ]);

                                $responseData = json_decode($responseState->getBody(), true);

                                // Update records immediately
                                DB::transaction(function() use ($responseData, $feedData) {
                                    AutoDistributerReport::updateOrCreate([
                                        'call_id' => $responseData['result']['callid'],
                                    ], [
                                        'status' => "Initiating",
                                        'provider' => $responseData['result']['dn'],
                                        'extension' => $responseData['result']['dn'],
                                        'phone_number' => $responseData['result']['party_caller_id'],
                                    ]);

                                    $feedData->update([
                                        'state' => "Initiating",
                                        'call_date' => now(),
                                        'call_id' => $responseData['result']['callid'],
                                    ]);
                                });

                                Log::info("ADistMakeCallCommand 📞 Call initiated for {$feedData->mobile}");
                                break 3; // Exit all loops after successful call
                            }
                        } catch (RequestException $e) {
                            Log::error("ADistMakeCallCommand ❌ Call failed for {$feedData->mobile}: " . $e->getMessage());
                        }
                    }

                    // Quick feed status update
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
            }
        } catch (\Exception $e) {
            Log::error("ADistMakeCallCommand ❌ General error: " . $e->getMessage());
        }
    }
}
