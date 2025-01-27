<?php

namespace App\Console\Commands;

use App\Models\AutoDailerFeedFile;
use App\Models\AutoDailerUploadedData;
use App\Models\AutoDailerFile;
use App\Models\AutoDailerProviderFeed;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Models\AutoDailerReport;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use App\Jobs\MakeCallJob;
use App\Services\TokenService;


class makeCallCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:make-call-command';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    protected $tokenService;

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
                    \t\t\t********** Auto Dialer **********\n
                    \t-----------------------------------------------------------------------
                    \t| 📞 ✅ MakeCallCommand executed at " . now() . "               |
                    \t-----------------------------------------------------------------------
                ");





        $autoDailerFiles = AutoDailerFile::all();


        foreach ($autoDailerFiles as $feed) {
            // Create from and to date objects adjusted by -3 hours
            $from = Carbon::createFromFormat('Y-m-d H:i:s', $feed->date . ' ' . $feed->from)->subHours(3);
            $to = Carbon::createFromFormat('Y-m-d H:i:s', $feed->date . ' ' . $feed->to)->subHours(3);

            // Check if the current time is within the range and the file is allowed
            if (now()->between($from, $to) && $feed->allow == 1) {
                Log::info("
                            \t        -----------------------------------------------------------------------
                            \t\t\t\t********** Auto Dialer Time **********\n
                            \t\t\t⏰✅ TIME IN: File ID " . $feed->id . " is within range ✅ ⏰
                            \t        -----------------------------------------------------------------------
                        ");


                $data = AutoDailerUploadedData::where('file_id', $feed->id)->where('state', 'new')->paginate(50);
                foreach ($data as $feedData) {

                    try {
                        $token = $this->tokenService->getToken();
                        $ext = $feedData->extension;

                        $responseState = Http::withHeaders([
                            'Authorization' => 'Bearer ' . $token,
                        ])->post(config('services.three_cx.api_url') . "/callcontrol/{$ext}/makecall", [
                            'destination' => $feedData->mobile,
                        ]);

                        if ($responseState->successful()) {
                            $responseData = $responseState->json();
                            Log::info("
                                        \t********** Auto Dialer Response Call **********
                                        \tResponse Data:
                                        \t" . print_r($responseData, true) . "
                                        \t***********************************************
                                     ");


                            $reports = AutoDailerReport::firstOrCreate([
                                'call_id' => $responseData['result']['callid'],
                            ], [
                                'status' => $responseData['result']['status'],
                                'provider' => $feedData->provider,
                                'extension' => $responseData['result']['dn'],
                                'phone_number' => $responseData['result']['party_caller_id'],
                            ]);

                            $reports->save();

                            $feedData->update([
                                'state' => "Routing",
                                'call_date' => Carbon::now(),
                                'call_id' => $responseData['result']['callid'],
                                'party_dn_type' => $responseData['result']['party_dn_type'] ?? null,
                            ]);

                            Log::info("
                                        \t📞 *_*_*_*_*_*_*_*_*_*_*_*_*_*_*_*_*_*_*_*_*_*_*_*_*_*_*_*_*_*_*_*_*_*_ 📞
                                        \t|        ✅ Auto Dialer Called Successfully for Mobile: " . $feedData->mobile . " |
                                        \t📞 *_*_*_*_*_*_*_*_*_*_*_*_*_*_*_*_*_*_*_*_*_*_*_*_*_*_*_*_*_*_*_*_*_*_ 📞
                                    ");
                        } else {
                            Log::error("
                            \t❌ 🚨🚨🚨 ERROR: Auto Dialer Failed 🚨🚨🚨 ❌
                            \t| 🔴 Failed to make call for Mobile Number: " . $feedData->mobile . " |
                            \t| 🔄 Response: " . $responseState->body() . " |
                            \t❌ 🚨🚨🚨 ERROR: Auto Dialer Failed 🚨🚨🚨 ❌
                            ");
                        }
                    } catch (\Exception $e) {
                        Log::error("
                                                    \t-----------------------------------------------------------------------
                                                    \t\t\t\t********** Auto Dialer Error **********
                                                    \t-----------------------------------------------------------------------
                                                    \t| ❌ Error occurred in Auto Dialer: " . $e->getMessage() . " |
                                                    \t-----------------------------------------------------------------------
                                            ");
                    }
                    $allCalled = AutoDailerUploadedData::where('file_id', $feedData->file->id)->where('state', 'new')->count() == 0;
                    if ($allCalled) {
                        $feedData->file->update(['is_done' => true]);
                        Log::info("
                                    \t        -----------------------------------------------------------------------
                                    \t\t\t\t********** Auto Dailer **********\n
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
                            \t\t\t\t********** Auto Dialer Time **********\n
                            \t\t\t    ⏰❌ TIME OUT: File ID " . $feed->id . " is NOT within range ❌⏰
                            \t        -----------------------------------------------------------------------
                        ");
            }
        }
    }
}
