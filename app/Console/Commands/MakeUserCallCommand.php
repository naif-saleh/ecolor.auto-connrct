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
        $autoDailerFiles = AutoDistributorUploadedData::all();
        $now = Carbon::now();

        foreach ($autoDailerFiles as $feed) {
            $from = Carbon::createFromFormat('Y-m-d H:i:s', $feed->date . ' ' . $feed->from)->subHour(2);
            $to = Carbon::createFromFormat('Y-m-d H:i:s', $feed->date . ' ' . $feed->to)->subHour(2);

            if ($now->between($from, $to) && $feed->file->allow == 1) {
                Log::info('Processing file with ID ' . $feed->file->id);

                $providerFeeds = AutoDistributorUploadedData::where('file_id', $feed->file->id)
                    ->where('state', 'new')
                    ->get();

                foreach ($providerFeeds as $mobile) {
                    try {
                        // if (!$mobile || !$mobile->extension || !$mobile->mobile) {
                        //     Log::error('ADist: Invalid mobile data for mobile ' . $mobile);
                        //     return;
                        // }

                        $ext = $mobile->extension;
                        if ($mobile->userStatus === "Available") {
                            // Check if there are active calls for this extension
                            $filter = "contains(Caller, '{$ext}')";
                            $url = "https://ecolor.3cx.agency/xapi/v1/ActiveCalls?\$filter=" . urlencode($filter);

                            // Logging the API call URL for debugging
                            Log::info('Fetching active calls from URL: ' . $url);

                            $activeCallsResponse = Http::withHeaders([
                                'Authorization' => 'Bearer ' . $token,
                            ])->get($url);

                            if ($activeCallsResponse->failed()) {
                                Log::error('ADist: Failed to fetch active calls for mobile ' . $mobile->mobile . '. Response: ' . $activeCallsResponse->body());
                                return;
                            }

                            if ($activeCallsResponse->successful()) {
                                $activeCalls = $activeCallsResponse->json();

                                if (!empty($activeCalls['value'])) {
                                    Log::info("Active calls detected for extension {$ext}. Skipping call for mobile {$mobile->mobile}.");
                                    return; // Skip this number if active calls exist
                                }


                                MakeCallJob::dispatch($mobile, $token)
                                ->delay(now()->addSeconds(20));

                                // No active calls, proceed to make the call

                            } else {
                                Log::error('ADist: Error fetching active calls for mobile ' . $mobile->mobile);
                            }
                        } else {
                            Log::error('ADist: Mobile is not available. Skipping call for mobile ' . $mobile->mobile);
                        }
                    } catch (\Exception $e) {
                        Log::error('ADist: An error occurred: ' . $e->getMessage());
                    }
                }

                $allCalled = AutoDistributorUploadedData::where('file_id', $feed->file->id)->where('state', 'new')->count() == 0;
                if ($allCalled) {
                    $feed->file->update(['is_done' => true]);
                    Log::info('All numbers in file ' . $feed->file->slug . ' have been called. The file is marked as done.');
                }
            } else {
                Log::info('The current time is not within the specified range for file ID ' . $feed->file->id);
            }

            sleep(100);
        }


        // Make User Call Participant
        $providersFeeds = AutoDistributorUploadedData::whereDate('created_at', Carbon::today())->get();

        foreach ($providersFeeds as $feed) {
            $ext_from = $feed->extension;

            try {
                // Fetch participants for the extension
                $responseState = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $token,
                ])->get(config('services.three_cx.api_url') . "/callcontrol/{$ext_from}/participants");

                if (!$responseState->successful()) {
                    Log::error("participantsCommand Failed to fetch participants for extension {$ext_from}. HTTP Status: {$responseState->status()}. Response: {$responseState->body()}");
                    Log::info('participantsCommand:  Response Status Code: ' . $responseState->status());
                    Log::info('participantsCommand:  Full Response: ' . print_r($responseState, TRUE));
                    Log::info('participantsCommand: Headers: ' . json_encode($responseState->headers()));


                    continue;
                }

                $participants = $responseState->json();

                if (empty($participants)) {
                    Log::warning("No User participants data for extension {$ext_from}");
                    continue;
                }

                foreach ($participants as $participant_data) {
                    try {
                        // Log::debug("Processing participant data For Auto Dailer: " . print_r($participant_data, true));

                        $filter = "contains(Caller, '{$participant_data['dn']}')";
                        $url = "https://ecolor.3cx.agency/xapi/v1/ActiveCalls?\$filter=" . urlencode($filter);

                        $activeCallsResponse = Http::withHeaders([
                            'Authorization' => 'Bearer ' . $token,
                        ])->get($url);

                        if ($activeCallsResponse->successful()) {
                            // Log::debug("Processing participant data For Auto Dailer: " . print_r($participant_data, true));
                            $activeCalls = $activeCallsResponse->json();
                            //Log::debug("Active Calls: " . print_r($participant_data, true));
                            Log::info("User Participant Active Call Response: " . print_r($activeCalls, true));


                            // Iterate through all active calls to find matching callId
                            foreach ($activeCalls['value'] as $call) {
                                // Check if the call contains the required information
                                // Log::info("User Participant Active Call Response: " . print_r($activeCalls, true));
                                if (isset($call['Id']) && isset($call['Status'])) {
                                    // Log the status to track each call's behavior
                                    Log::info("Processing Call ID {$call['Id']} with status {$call['Status']}");

                                    // Check if the call is in progress
                                    if ($call['Status'] === "Talking" || $call['Status'] === "Routing") { // Routing When Ringing
                                        AutoDistributerReport::where('call_id', $call['Id'])->update(['status' => $call['Status']]);
                                        AutoDistributorUploadedData::where('call_id', $call['Id'])->update(['state' => $call['Status']]);
                                        Log::info("Updated status for call ID {$call['Id']} to " . $call['Status']);
                                    } else {
                                        AutoDistributorUploadedData::where('call_id', $call['Id'])->update(['state' => $call['Status']]);
                                        AutoDistributerReport::where('call_id', $call['Id'])->update(['status' => $call['Status']]);
                                        Log::info("Updated status for call ID {$call['Id']} to " . $call['Status']);
                                    }
                                } else {
                                    Log::warning("Call missing 'Id' or 'Status' for participant DN {$participant_data['dn']}. Call Data: " . print_r($call, true));
                                }



                            }
                        } else {
                            Log::error('Failed to fetch active calls. Response: ' . $activeCallsResponse->body());
                        }
                    } catch (\Exception $e) {
                        Log::error('Failed to process participant data for call ID ' . ($participant_data['callid'] ?? 'N/A') . ': ' . $e->getMessage());
                    }
                }
            } catch (\Exception $e) {
                Log::error("Error fetching participants for provider {$ext_from}: " . $e->getMessage());
            }
        }




    }
}
