<?php

namespace App\Console\Commands;


use App\Models\AutoDistributorUploadedData;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Models\AutoDistributerReport;
use Illuminate\Support\Facades\Http;
use App\Models\AutoDistributorFile;
use App\Jobs\MakeCallJob;
use App\Services\TokenService;

class MakeUserCallCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:make-user-call-command';
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
        $autoDailerFiles = AutoDistributorFile::all();

        foreach ($autoDailerFiles as $feed) {
            // Create from and to date objects adjusted by -3 hours
            $from = Carbon::createFromFormat('Y-m-d H:i:s', $feed->date . ' ' . $feed->from)->subHours(3);
            $to = Carbon::createFromFormat('Y-m-d H:i:s', $feed->date . ' ' . $feed->to)->subHours(3);

            // Check if the current time is within the range and the file is allowed
            if (now()->between($from, $to) && $feed->allow == 1) {
                Log::info("
                \t        -----------------------------------------------------------------------
                \t\t\t\t********** Auto Distributor Time **********\n
                \t\t\t⏰✅ TIME IN: File ID " . $feed->id . " is within range ✅ ⏰
                \t        -----------------------------------------------------------------------
            ");

                $data = AutoDistributorUploadedData::where('file_id', $feed->id)->where('state', 'new')->get();
                foreach ($data as $feedData) {

                    try {
                        $token = $this->tokenService->getToken();
                        if ($feedData->userStatus === "Available") {
                            $ext = $feedData->extension;
                            $filter = "contains(Caller, '{$ext}')";
                            $url = config('services.three_cx.api_url') . "/xapi/v1/ActiveCalls?\$filter=" . urlencode($filter);

                            // Fetch active calls from API
                            $activeCallsResponse = Http::withHeaders([
                                'Authorization' => 'Bearer ' . $token,
                            ])->get($url);

                            if ($activeCallsResponse->failed()) {
                                Log::error('Auto Distributor Error: ❌ Failed to fetch active calls for mobile ' . $feedData->mobile . '. Response: ' . $activeCallsResponse->body());
                                continue;
                            }

                            if ($activeCallsResponse->successful()) {
                                $activeCalls = $activeCallsResponse->json();

                                if (!empty($activeCalls['value'])) {
                                    Log::info("
                                                \t-----------------------------------------------------------------------
                                                \t\t\t\t********** Auto Distributor Notification **********
                                                \t-----------------------------------------------------------------------
                                                \t| 🚫 Busy: Active call detected for extension {$ext}. Skipping call for mobile {$feedData->mobile}. |
                                                \t-----------------------------------------------------------------------
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
                                        \t********** Auto Distributor Response Call **********
                                        \tResponse Data:
                                        \t" . print_r($responseData, true) . "
                                        \t***************************************************
                                     ");

                                                $reports = AutoDistributerReport::firstOrCreate([
                                                    'call_id' => $responseData['result']['callid'],
                                                ], [
                                                    'status' => "Initiating",
                                                    'provider' => $feedData->user,
                                                    'extension' => $responseData['result']['dn'],
                                                    'phone_number' => $responseData['result']['party_caller_id'],
                                                ]);

                                                $reports->save();

                                                $feedData->update([
                                                    'state' => "Initiating",
                                                    'call_date' => Carbon::now(),
                                                    'call_id' => $responseData['result']['callid'],
                                                    'party_dn_type' => $responseData['result']['party_dn_type'] ?? null,
                                                ]);

                                                Log::info("
                                        \t📞 *_*_*_*_*_*_*_*_*_*_*_*_*_*_*_*_*_*_*_*_*_*_*_*_*_*_*_*_*_*_*_*_*_*_ 📞
                                        \t|        ✅ Auto Distributor Called Successfully for Mobile: " . $feedData->mobile . " |
                                        \t📞 *_*_*_*_*_*_*_*_*_*_*_*_*_*_*_*_*_*_*_*_*_*_*_*_*_*_*_*_*_*_*_*_*_*_ 📞
                                    ");
                                            } else {
                                                Log::error("
                                                \t❌ 🚨🚨🚨 ERROR: Auto Distributor Failed 🚨🚨🚨 ❌
                                                \t| 🔴 Failed to make call for Mobile Number: " . $feedData->mobile . " |
                                                \t| 🔄 Response: " . $responseState->body() . " |
                                                \t❌ 🚨🚨🚨 ERROR: Auto Distributor Failed 🚨🚨🚨 ❌
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
                            Log::error('Auto Distributor Error: 📵 Mobile is not available. Skipping call for mobile ' . $feedData->mobile);

                        }
                    } catch (\Exception $e) {
                        Log::error("
                        \t-----------------------------------------------------------------------
                        \t\t\t\t********** Auto Distributor Error **********
                        \t-----------------------------------------------------------------------
                        \t| ❌ Error occurred in Auto Dialer: " . $e->getMessage() . " |
                        \t-----------------------------------------------------------------------
                ");
                    }


                    // After processing all provider feeds, mark the file as done if all numbers are called
                    $allCalled = AutoDistributorUploadedData::where('file_id', $feedData->file->id)->where('state', 'new')->count() == 0;
                    if ($allCalled) {
                        $feedData->file->update(['is_done' => true]);
                        Log::info("
                                    \t        -----------------------------------------------------------------------
                                    \t\t\t\t********** Auto Distributor **********\n
                                    \t✅✅✅ All Numbers Called ✅✅✅
                                    \t| File: " . $feedData->file->slug . " |
                                    \t| Status: The file is marked as 'Done' |
                                    \t✅✅✅ All Numbers Called ✅✅✅
                                ");
                    }

                }
            } else {
                Log::info("
                            \t        -----------------------------------------------------------------------
                            \t\t\t\t********** Auto Distributor Time **********\n
                            \t\t\t     ⏰❌ TIME OUT: File ID " . $feed->id . " is NOT within range ❌⏰
                            \t        -----------------------------------------------------------------------
                        ");
            }
        }
    }
}
