<?php

namespace App\Console\Commands;


use App\Models\AutoDistributorUploadedData;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Models\AutoDistributerReport;
use Illuminate\Support\Facades\Http;
use App\Models\AutoDistributorFile;
use App\Jobs\MakeUserCallJob;
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


        // $token = Cache::get('three_cx_token');
        $tokenService = app(TokenService::class);
        $token = $this->tokenService->getToken();
        Log::info('ADist: MakeCallCommand executed at ' . now());
        $providersFeeds = AutoDistributorUploadedData::all();
        $now = Carbon::now();

        foreach ($providersFeeds as $feed) {
            // $ext_from = $feed->extension;

            // Recalculate 'from' and 'to' for each feed
            $from = Carbon::createFromFormat('Y-m-d H:i:s', $feed->date . ' ' . $feed->from)->subHour(2);
            $to = Carbon::createFromFormat('Y-m-d H:i:s', $feed->date . ' ' . $feed->to)->subHour(2);
            // Log::info("From Time: " . $from . " | To Time: " . $to);
            // Log::info('Make Provider Call, Active status ' . $feed->extension . " | " . $feed->file->allow);
            // Log::info("Current Time: " . $now);
            // Log::info("From Time: " . $from);
            // Log::info("To Time: " . $to);
            // Log::info("File Allow Status: " . $feed->file->allow);
            // Check if the current time is within the range
            if ($now->between($from, $to) && $feed->file->allow == 1) {
                Log::info('The current time is within the specified range.' . $feed->id);
                Log::info('The current time is within the specified range for extension ' . $feed->extension . $to->format('r'));

                // Get all numbers in this feed
                $files = AutoDistributorFile::all();
                Log::info('Files:', $files->toArray());

                foreach ($files as $file) {
                    $providerFeeds = AutoDistributorUploadedData::where('file_id', $file->id)->where('state', 'new')->get();
                    Log::info('Provider Feeds:', $providerFeeds->toArray());

                    $loop = 0;
                    foreach ($providerFeeds as $mobile) {
                        Log::info('Mobile ' . $mobile->mobile . ' in loop ' . $loop);

                        $delay = 0;
                        MakeUserCallJob::dispatch($mobile, $tokenService)->delay(now()->addSeconds($delay));
                        $delay += 10;

                    }

                    // Check if all mobiles in this file are called (state == 'called')
                    $allCalled = AutoDistributorUploadedData::where('file_id', $feed->file->id)->where('state', 'new')->count() == 0;
                    if ($allCalled) {
                        $feed->file->update(['is_done' => true]);
                        Log::info('All numbers in file ' . $feed->file->slug . ' have been called. The file is marked as done.');
                    }
                }
            } else {
                Log::info('The current time is not within the specified range.');
                Log::info('The current time is not within the specified range for extension ' . $feed->extension . $to->format('r'));
            }
        }
    }






















    //  Make Call For Providers
    // foreach ($providersFeeds as $feed) {
    //     $ext_from = $feed->extension;

    //     $now = Carbon::now();

    //     // Parse the date and time from the data
    //     $from = Carbon::createFromFormat('Y-m-d H:i:s', $feed->date . ' ' . $feed->from);
    //     $to = Carbon::createFromFormat('Y-m-d H:i:s', $feed->date . ' ' . $feed->to);


    //     Log::info('ADist:  Make Provider Call, Active status ' . $feed->extension . $feed->on);
    //     Log::info('ADist:  User Status' . $feed->userStatus);

    //     // Check if the current time is within the range
    //     if ($now->between($from, $to) && $feed->on == 1) {
    //         Log::info('ADist: The current time is within the specified range.' . $feed->id);
    //         Log::info('ADist: The current time is within the specified range for extension ' . $feed->extension . $to->format('r'));
    //         // get all number in this feed

    //         //$mobiles = AutoDailerProviderFeed::find($feed->id);
    //         $providerFeeds = AutoDistributorUploadedData::byFeedFile($feed->id)
    //             ->where('state', 'new')
    //             ->get();
    //         // Log::info('ADist: Provider ID ' . $feed->id . ' User Status = ' . $feed->userStatus);

    //         $loop = 0;
    //         foreach ($providerFeeds as $mobile) {
    //             Log::info('ADist: Processing mobile ' . $mobile->mobile);

    //             try {
    //                 if ($feed->userStatus === "Available") {
    //                     // Check if there are active calls for this extension
    //                     $filter = "contains(Caller, '{$ext_from}')";
    //                     $url = "https://ecolor.3cx.agency/xapi/v1/ActiveCalls?\$filter=" . urlencode($filter);

    //                     $activeCallsResponse = Http::withHeaders([
    //                         'Authorization' => 'Bearer ' . $token,
    //                     ])->get($url);

    //                     if ($activeCallsResponse->successful()) {
    //                         $activeCalls = $activeCallsResponse->json();

    //                         if (!empty($activeCalls['value'])) {
    //                             Log::info("Active calls detected for extension {$ext_from}. Skipping call for mobile {$mobile->mobile}.");
    //                             continue; // Skip this number if active calls exist
    //                         }

    //                         // No active calls, proceed to make the call
    //                         $responseState = Http::withHeaders([
    //                             'Authorization' => 'Bearer ' . $token,
    //                         ])->post(config('services.three_cx.api_url') . "/callcontrol/{$ext_from}/makecall", [
    //                             'destination' => $mobile->mobile,
    //                         ]);

    //                         if ($responseState->successful()) {
    //                             $responseData = $responseState->json();

    //                             // Handle the report creation and update
    //                             $reports = AutoDistributerReport::firstOrCreate([
    //                                 'call_id' => $responseData['result']['callid'],
    //                             ], [
    //                                 'status' => $responseData['result']['status'],
    //                                 'provider' => $mobile->extension->name,
    //                                 'extension' => $responseData['result']['dn'],
    //                                 'phone_number' => $responseData['result']['party_caller_id'],
    //                             ]);

    //                             $reports->save();

    //                             // Update the mobile record
    //                             $mobile->update([
    //                                 'state' => $responseData['result']['status'],
    //                                 'call_date' => now(),
    //                                 'call_id' => $responseData['result']['callid'],
    //                                 'party_dn_type' => $responseData['result']['party_dn_type'] ?? null,
    //                             ]);

    //                             Log::info('ADist: Call successfully made for mobile ' . $mobile->mobile);
    //                         } else {

    //                             Log::error('ADist: Failed to make call for mobile ' . $mobile->mobile . '. Response: ' . $responseState->body());
    //                             Log::info('ADist:  Response Status Code: ' . $responseState->status());
    //                             Log::info('ADist:  Full Response: ' . print_r($responseState, TRUE));
    //                             Log::info('ADist: Headers: ' . json_encode($responseState->headers()));
    //                         }

    //                         // Add a 30-second delay between calls
    //                         // Log::info("ADist: Waiting for 30 seconds before making the next call.");


    //                     }       // Wait for 30 seconds before the next call
    //                     //  $this->waitFor(30);
    //                 } else {
    //                     Log::error('ADist: Error fetching active calls for mobile ' . $mobile['mobile']);
    //                 }
    //             } catch (\Exception $e) {
    //                 Log::error('ADist: An error occurred: ' . $e->getMessage());
    //             }
    //         }
    //     } else {
    //         Log::info('The current time is not within the specified range.');
    //         Log::info('The current time is not within the specified range for extension ' . $feed->extension . $to->format('r'));
    //     }
    // }



    private function waitFor($seconds)
    {
        $startTime = time();
        while (time() - $startTime < $seconds) {
        }
    }
}
