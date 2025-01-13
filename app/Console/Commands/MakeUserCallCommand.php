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
        $token = $this->tokenService->getToken();
        Log::info('MakeCallCommand executed at ' . now());
        $autoDailerFiles = AutoDistributorUploadedData::where('state', 'new')->take(10)
        ->get();;
        $now = Carbon::now();

        foreach ($autoDailerFiles as $feed) {
            Log::info('count of calls in each time: '. $autoDailerFiles->count());
            // Calculate the time range by subtracting 2 hours from the given time
            $from = Carbon::createFromFormat('Y-m-d H:i:s', $feed->date . ' ' . $feed->from)->subHour(2);
            $to = Carbon::createFromFormat('Y-m-d H:i:s', $feed->date . ' ' . $feed->to)->subHour(2);

            // Check if the current time is within the range for this file_id
            Log::info('Current time: ' . $now);
            Log::info('Time range for file ID ' . $feed->file->id . ' From: ' . $from . ' To: ' . $to);

            if ($now > $from && $now < $to && $feed->file->allow == 1) {
                // The time is valid for this file_id, proceed with processing
                Log::info('Processing file with ID ' . $feed->file->id);

                // $providerFeeds = AutoDistributorUploadedData::


                    try {
                        if ($feed->userStatus === "Available") {
                            $ext = $feed->extension;
                            $filter = "contains(Caller, '{$ext}')";
                            $url = config('services.three_cx.api_url')."/xapi/v1/ActiveCalls?\$filter=" . urlencode($filter);

                            // Fetch active calls from API
                            $activeCallsResponse = Http::withHeaders([
                                'Authorization' => 'Bearer ' . $token,
                            ])->get($url);

                            if ($activeCallsResponse->failed()) {
                                Log::error('ADist: Failed to fetch active calls for mobile ' . $feed->mobile . '. Response: ' . $activeCallsResponse->body());
                                continue;
                            }

                            if ($activeCallsResponse->successful()) {
                                $activeCalls = $activeCallsResponse->json();

                                if (!empty($activeCalls['value'])) {
                                    Log::info("Active calls detected for extension {$ext}. Skipping call for mobile {$feed->mobile}.");
                                    continue; // Skip this number if active calls exist
                                }

                                // Proceed to make the call if no active calls are detected
                                $responseState = Http::withHeaders([
                                    'Authorization' => 'Bearer ' . $token,
                                ])->post(config('services.three_cx.api_url') . "/callcontrol/{$ext}/makecall", [
                                    'destination' => $feed->mobile,
                                ]);

                                if ($responseState->successful()) {
                                    $responseData = $responseState->json();

                                    // Save the call report
                                    $reports = AutoDistributerReport::firstOrCreate([
                                        'call_id' => $responseData['result']['callid'],
                                    ], [
                                        'status' => "Initiating",
                                        'provider' => $feed->user,
                                        'extension' => $responseData['result']['dn'],
                                        'phone_number' => $responseData['result']['party_caller_id'],
                                    ]);

                                    $reports->save();

                                    $feed->update([
                                        'state' => "Initiating",
                                        'call_date' => Carbon::now(),
                                        'call_id' => $responseData['result']['callid'],
                                        'party_dn_type' => $responseData['result']['party_dn_type'] ?? null,
                                    ]);

                                    Log::info('ADist: Call successfully made for mobile ' . $feed->mobile);
                                } else {
                                    Log::error('ADist: Failed to make call for mobile Number** ' . $feed->mobile . '. Response: ' . $responseState->body());
                                }
                            } else {
                                Log::error('ADist: Error fetching active calls for mobile ' . $feed->mobile);
                            }
                        } else {
                            Log::error('ADist: Mobile is not available. Skipping call for mobile ' . $feed->mobile);
                        }
                    } catch (\Exception $e) {
                        Log::error('ADist: An error occurred: ' . $e->getMessage());
                    }


                // After processing all provider feeds, mark the file as done if all numbers are called
                $allCalled = AutoDistributorUploadedData::where('file_id', $feed->file->id)->where('state', 'new')->count() == 0;
                if ($allCalled) {
                    $feed->file->update(['is_done' => true]);
                    Log::info('All numbers in file ' . $feed->file->slug . ' have been called. The file is marked as done.');
                }
            } else {
                // If the time is not valid, skip the file and log it
                Log::info('The current time is not within the specified range for file ID ' . $feed->file->id);
                continue; // Skip this file and continue with the next one
            }
        }






        // // Make User Call Participant
        // $providersFeeds = AutoDistributorUploadedData::whereDate('created_at', Carbon::today())->get();

        // foreach ($providersFeeds as $feed) {
        //     $ext_from = $feed->extension;

        //     try {
        //         // Fetch participants for the extension
        //         $responseState = Http::withHeaders([
        //             'Authorization' => 'Bearer ' . $token,
        //         ])->get(config('services.three_cx.api_url') . "/callcontrol/{$ext_from}/participants");

        //         if (!$responseState->successful()) {
        //             Log::error("participantsCommand Failed to fetch participants for extension {$ext_from}. HTTP Status: {$responseState->status()}. Response: {$responseState->body()}");
        //             Log::info('participantsCommand:  Response Status Code: ' . $responseState->status());
        //             Log::info('participantsCommand:  Full Response: ' . print_r($responseState, TRUE));
        //             Log::info('participantsCommand: Headers: ' . json_encode($responseState->headers()));


        //             continue;
        //         }

        //         $participants = $responseState->json();

        //         if (empty($participants)) {
        //             Log::warning("No User participants data for extension {$ext_from}");
        //             continue;
        //         }

        //         foreach ($participants as $participant_data) {
        //             try {
        //                 // Log::debug("Processing participant data For Auto Dailer: " . print_r($participant_data, true));

        //                 $filter = "contains(Caller, '{$participant_data['dn']}')";
        //                 $url = "https://ecolor.3cx.agency/xapi/v1/ActiveCalls?\$filter=" . urlencode($filter);

        //                 $activeCallsResponse = Http::withHeaders([
        //                     'Authorization' => 'Bearer ' . $token,
        //                 ])->get($url);

        //                 if ($activeCallsResponse->successful()) {
        //                     // Log::debug("Processing participant data For Auto Dailer: " . print_r($participant_data, true));
        //                     $activeCalls = $activeCallsResponse->json();
        //                     //Log::debug("Active Calls: " . print_r($participant_data, true));
        //                     Log::info("User Participant Active Call Response: " . print_r($activeCalls, true));


        //                     // Iterate through all active calls to find matching callId
        //                     foreach ($activeCalls['value'] as $call) {
        //                         // Check if the call contains the required information
        //                         // Log::info("User Participant Active Call Response: " . print_r($activeCalls, true));
        //                         if (isset($call['Id']) && isset($call['Status'])) {
        //                             // Log the status to track each call's behavior
        //                             Log::info("Processing Call ID {$call['Id']} with status {$call['Status']}");

        //                             // Check if the call is in progress
        //                             if ($call['Status'] === "Talking" || $call['Status'] === "Routing") { // Routing When Ringing
        //                                 AutoDistributerReport::where('call_id', $call['Id'])->update(['status' => $call['Status']]);
        //                                 AutoDistributorUploadedData::where('call_id', $call['Id'])->update(['state' => $call['Status']]);
        //                                 Log::info("Updated status for call ID {$call['Id']} to " . $call['Status']);
        //                             } else {
        //                                 AutoDistributorUploadedData::where('call_id', $call['Id'])->update(['state' => $call['Status']]);
        //                                 AutoDistributerReport::where('call_id', $call['Id'])->update(['status' => $call['Status']]);
        //                                 Log::info("Updated status for call ID {$call['Id']} to " . $call['Status']);
        //                             }
        //                         } else {
        //                             Log::warning("Call missing 'Id' or 'Status' for participant DN {$participant_data['dn']}. Call Data: " . print_r($call, true));
        //                         }



        //                     }
        //                 } else {
        //                     Log::error('Failed to fetch active calls. Response: ' . $activeCallsResponse->body());
        //                 }
        //             } catch (\Exception $e) {
        //                 Log::error('Failed to process participant data for call ID ' . ($participant_data['callid'] ?? 'N/A') . ': ' . $e->getMessage());
        //             }
        //         }
        //     } catch (\Exception $e) {
        //         Log::error("Error fetching participants for provider {$ext_from}: " . $e->getMessage());
        //     }
        // }




    }
}
