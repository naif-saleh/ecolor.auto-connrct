<?php

namespace App\Console\Commands;

use App\Models\ADistAgent;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Models\ADistData;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use App\Models\AutoDistributerReport;
use DateTime;
use App\Services\TokenService;

class ADistParticipantsCommand extends Command
{
    protected $signature = 'app:ADist-participants-command';
    protected $description = 'Fetch and process participants data from 3CX API';
    protected $tokenService;

    public function __construct(TokenService $tokenService)
    {
        parent::__construct();
        $this->tokenService = $tokenService;
    }

    public function handle()
    {
        Log::info('ADistParticipantsCommand executed at ' . Carbon::now());


        // $agents = ADistAgent::all();
        // foreach ($agents as $agent) {
            try {
                $token = $this->tokenService->getToken();

                // $ext = $agent->extension;
                // $filter = "contains(Caller, '{$ext}')";
                // $url = config('services.three_cx.api_url') . "/xapi/v1/ActiveCalls?\$filter=" . urlencode($filter);

                // $activeCallsResponse = Http::withHeaders(['Authorization' => "Bearer $token"])->get($url);

                $activeCallsResponse = Http::withHeaders(['Authorization' => 'Bearer ' . $token])
                    ->get(config('services.three_cx.api_url') . "/xapi/v1/ActiveCalls");

                if (!$activeCallsResponse->successful()) {
                    Log::error("ADistParticipantsCommand ❌ Failed to fetch active calls.");
                    return;
                }

                $activeCalls = $activeCallsResponse->json();

                if (empty($activeCalls['value'])) {
                    Log::info("ADistParticipantsCommand ℹ️ No active calls at the moment.");
                    return;
                }

                Log::info("ADistParticipantsCommand Active Calls Retrieved: " . print_r($activeCalls, true));


                // Log::info("ADistParticipantsCommand ✅ Agent Mobile: " . $agent->mobile);
                foreach ($activeCalls['value'] as $call) {
                    // Log::info("ADistParticipantsCommand ✅ Active Calls Retrieved: " . print_r($activeCalls, true));

                    $status = $call['Status'];
                    $callId = $call['Id'];

                    $durationTime = null;
                    $durationRouting = null;

                    if ($status === 'Talking') {
                        $establishedAt = new DateTime($call['EstablishedAt']);
                        $serverNow = new DateTime($call['ServerNow']);
                        $durationTime = $establishedAt->diff($serverNow)->format('%H:%I:%S');
                        // Log::info("ADistParticipantsCommand ✅ Duration Time: ".$durationTime);
                    }

                    if ($status === 'Routing') {
                        $establishedAt = new DateTime($call['EstablishedAt']);
                        $serverNow = new DateTime($call['ServerNow']);
                        $durationRouting = $establishedAt->diff($serverNow)->format('%H:%I:%S');
                        // Log::info("ADistParticipantsCommand ✅ Duration Routing: ".$durationRouting);
                    }

                    DB::beginTransaction();
                    try {
                        AutoDistributerReport::where('call_id', $callId)
                            ->update(['status' => $status, 'duration_time' => $durationTime, 'duration_routing' => $durationRouting]);
                        ADistData::where('call_id', $callId)
                            ->update(['state' => $status]);
                        Log::info("ADistParticipantsCommand ✅ mobile status:: " . $status . " Mobile:" . $call['Callee']);
                        DB::commit();
                    } catch (\Exception $e) {
                        DB::rollBack();
                        Log::error("ADistParticipantsCommand ❌ Transaction Failed for call ID {$callId}: " . $e->getMessage());
                    }
                }
            } catch (\Exception $e) {
                Log::error("ADistParticipantsCommand ❌ General error in fetching active calls: " . $e->getMessage());
            }
        // }
        Log::info("ADistParticipantsCommand ✅ Auto Dialer command execution completed.");
    }
}
