<?php

namespace App\Console\Commands;


use App\Models\AutoDistributorUploadedData;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Models\AutoDistributerReport;
use Illuminate\Support\Facades\Http;
use App\Models\ADistFeed;
use App\Jobs\MakeCallJob;
use App\Models\ADistAgent;
use App\Models\ADistData;
use App\Services\TokenService;

class ADistMakeCallCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:ADist-make-call-command';
    protected $threeCXTokenService;
    protected $tokenService;
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    public function __construct(TokenService $tokenService)
    {
        parent::__construct(); // This is required
        $this->tokenService = $tokenService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {

        Log::info("
                    \t-----------------------------------------------------------------------
                    \t\t\t********** Auto Distributor **********\n
                    \t-----------------------------------------------------------------------
                    \t| 📞 ✅ MakeCallCommand executed at " . now() . "               |
                    \t-----------------------------------------------------------------------
                ");

        $agents = ADistAgent::all();
        foreach ($agents as $agent) {

            $feeds = ADistFeed::where('agent_id', $agent->id)
            ->whereDate('date', today())
            ->where('allow', true) // Only allowed files
            ->get();


            foreach ($feeds as $feed) {
                // Create from and to date objects adjusted by -3 hours
                $from = Carbon::createFromFormat('Y-m-d H:i:s', $feed->date . ' ' . $feed->from)->subHours(3);
                $to = Carbon::createFromFormat('Y-m-d H:i:s', $feed->date . ' ' . $feed->to)->subHours(3);

                // Check if the current time is within the range and the file is allowed
                if (now()->between($from, $to) && $feed->allow == 1) {
                    Log::info("
                            -----------------------------------------------------------------------
                            ********** Auto Distributor Time **********\n
                            ⏰✅ TIME IN: File ID " . $feed->id . " is within range ✅ ⏰
                            -----------------------------------------------------------------------
                        ");

                    ADistData::where('feed_id', $feed->id)
                        ->where('state', 'new')
                        ->chunk(50, function ($dataChunk) use ($agent) {
                            foreach ($dataChunk as $feedData) {
                                try {
                                    $token = $this->tokenService->getToken();
                                    if ($agent->status === "Available") {
                                        $ext = $agent->extension;
                                        $filter = "contains(Caller, '{$ext}')";
                                        $url = config('services.three_cx.api_url') . "/xapi/v1/ActiveCalls?\$filter=" . urlencode($filter);

                                        // Fetch active calls from API
                                        $activeCallsResponse = Http::withHeaders([
                                            'Authorization' => 'Bearer ' . $token,
                                        ])->get($url);

                                        if ($activeCallsResponse->failed()) {
                                            Log::error('Auto Distributor Error: ❌ Failed fetch active calls for mobile ' . $feedData->mobile . '. Response: ' . $activeCallsResponse->body());
                                            continue;
                                        }

                                        if ($activeCallsResponse->successful()) {
                                            $activeCalls = $activeCallsResponse->json();

                                            if (!empty($activeCalls['value'])) {
                                                Log::info("
                                                        -----------------------------------------------------------------------
                                                        ********** Auto Distributor Notification **********
                                                        🚫 Busy: Active call detected for extension {$ext}. Skipping call for mobile {$feedData->mobile}. |
                                                        -----------------------------------------------------------------------
                                                    ");
                                                continue; // Skip this number if active calls exist
                                            }

                                            // Fetch devices for the extension
                                            $dnDevices = Http::withHeaders([
                                                'Authorization' => 'Bearer ' . $token,
                                            ])->get(config('services.three_cx.api_url') . "/callcontrol/{$ext}/devices");

                                            if ($dnDevices->successful()) {
                                                $devices = $dnDevices->json();

                                                // Filter the device where user_agent is '3CX Mobile Client'
                                                foreach ($devices as $device) {
                                                    if ($device['user_agent'] === '3CX Mobile Client') {
                                                        $responseState = Http::withHeaders([
                                                            'Authorization' => 'Bearer ' . $token,
                                                        ])->post(config('services.three_cx.api_url') . "/callcontrol/{$ext}/devices/{$device['device_id']}/makecall", [
                                                            'destination' => $feedData->mobile,
                                                        ]);

                                                        if ($responseState->successful()) {
                                                            $responseData = $responseState->json();
                                                            Log::info("
                                                                    ********** Auto Distributor Response Call **********
                                                                    Response Data:
                                                                    " . print_r($responseData, true) . "
                                                                    ***************************************************
                                                                ");

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
                                                                'call_date' => Carbon::now(),
                                                                'call_id' => $responseData['result']['callid'],
                                                               // 'party_dn_type' => $responseData['result']['party_dn_type'] ?? null,
                                                            ]);

                                                            Log::info("
                                                                    📞 *_*_*_*_*_*_*_*_*_*_*_*_*_*_*_*_*_*_*_*_*_*_*_*_*_*_*_*_*_*_*_* 📞
                                                                    ✅ Auto Distributor Called Successfully for Mobile: " . $feedData->mobile . "
                                                                    📞 *_*_*_*_*_*_*_*_*_*_*_*_*_*_*_*_*_*_*_*_*_*_*_*_*_*_*_*_*_*_*_* 📞
                                                                ");
                                                        } else {
                                                            Log::error("
                                                                    ❌ 🚨 ERROR: Auto Distributor Failed 🚨 ❌
                                                                    🔴 Failed to make call for Mobile Number: " . $feedData->mobile . "
                                                                    🔄 Response: " . $responseState->body() . "
                                                                    ❌ 🚨 ERROR: Auto Distributor Failed 🚨 ❌
                                                                ");
                                                        }
                                                        break; // Exit loop after making the call
                                                    }
                                                }
                                            } else {
                                                Log::error('Auto Distributor Error: ❌ Error fetching devices for extension ' . $ext);
                                            }
                                        } else {
                                            Log::error('Auto Distributor Error: ❌ Error fetching active calls for mobile ' . $feedData->mobile);
                                        }
                                    } else {
                                        Log::error('Auto Distributor Error: 📵 Employee is not available. Skipping call for mobile ' . $feedData->mobile);
                                    }
                                } catch (\Exception $e) {
                                    Log::error("
                                            -----------------------------------------------------------------------
                                            ********** Auto Distributor Error **********
                                            ❌ Error occurred in Auto Dialer: " . $e->getMessage() . "
                                            -----------------------------------------------------------------------
                                        ");
                                }
                            }
                        });

                    // After processing all provider feeds, mark the file as done if all numbers are called
                    $allCalled = ADistData::where('feed_id', $feed->id)->where('state', 'new')->count() == 0;
                    if ($allCalled) {
                        $feed->update(['is_done' => true]);
                        Log::info("
                                -----------------------------------------------------------------------
                                ********** Auto Distributor **********\n
                                ✅✅✅ All Numbers Called ✅✅✅
                                | File: " . $feed->slug . " |
                                | Status: The file is marked as 'Done' |
                                ✅✅✅ All Numbers Called ✅✅✅
                                -----------------------------------------------------------------------
                            ");
                    }
                } else {
                    Log::info("
                            -----------------------------------------------------------------------
                            ********** Auto Distributor Time **********\n
                            ⏰❌ TIME OUT: File ID " . $feed->id . " is NOT within range ❌⏰
                            -----------------------------------------------------------------------
                        ");
                }
            }
        }
    }
}
