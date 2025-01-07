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

        // $token = Cache::get('three_cx_token');
        $token = $this->tokenService->getToken();
        Log::info('MakeCallCommand executed at ' . now());
        Log::info('MakeCallCommand e ' . $token);
        $autoDailerFiles = AutoDailerUploadedData::all();

        // TODO: get only today feeds



        //  Make Call For Providers
        foreach ($autoDailerFiles as $feed) {
            $ext_from = $feed->extension;
            $now = Carbon::now();

            // Parse the date and time from the data
            $from = Carbon::createFromFormat('Y-m-d H:i:s', $feed->date . ' ' . $feed->from);
            $to = Carbon::createFromFormat('Y-m-d H:i:s', $feed->date . ' ' . $feed->to);
            $from = $from->subHour(2);
            $to = $to->subHour(2);

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
                $files = AutoDailerFile::all();
                Log::info('Files:', $files->toArray());

                foreach ($files as $file) {
                    $providerFeeds = AutoDailerUploadedData::where('file_id', $file->id)->where('state', 'new')->get();
                    Log::info('Provider Feeds:', $providerFeeds->toArray());

                    $loop = 0;
                    foreach ($providerFeeds as $mobile) {
                        Log::info('Mobile ' . $mobile->mobile . ' in loop ' . $loop);

                        try {
                            $responseState = Http::withHeaders([
                                'Authorization' => 'Bearer ' . $token,
                            ])->post(config('services.three_cx.api_url') . "/callcontrol/{$ext_from}/makecall", [
                                'destination' => $mobile->mobile,
                            ]);

                            if ($responseState->successful()) {
                                $responseData = $responseState->json();
                                // Log::info('Make Call Response: ' . print_r($responseData, true));

                                // Check if the fields exist in the response
                                     $reports = AutoDailerReport::firstOrCreate([
                                        'call_id' => $responseData['result']['callid'],
                                        'status' => $responseData['result']['status'],
                                        'provider' => $mobile->provider,
                                        'extension' => $responseData['result']['dn'],
                                        'phone_number' => $responseData['result']['party_caller_id'],
                                    ]);

                                    if ($reports->save()) {
                                        Log::info('Report saved successfully.');
                                    } else {
                                        Log::error('Failed to save report.');
                                    }

                                    // Update the mobile record
                                    $mobile->update([
                                        'state' => $responseData['result']['status'],
                                        'call_date' => $now,
                                        'call_id' => $responseData['result']['callid'],
                                    ]);

                                    Log::info('Call successfully made for mobile ' . $mobile->mobile);

                            }
                             else {
                                Log::error('ADailer: Failed to make call for mobile ' . $mobile->mobile . '. Response: ' . $responseState->body());
                                Log::info('ADailer: Response Status Code: ' . $responseState->status());
                                // Log::info('ADailer: Full Response: ' . print_r($responseState, TRUE));
                                // Log::info('ADailer: Headers: ' . json_encode($responseState->headers()));
                            }
                        } catch (\Exception $e) {
                            Log::error('An error occurred: ' . $e->getMessage());
                        }
                    }

                    // Check if all mobiles in this file are called (state == 'called')
                    $allCalled = AutoDailerUploadedData::where('file_id', $file->id)->where('state', '==', 'new')->count() == 0;

                    // If all calls have been made, update the AutoDailerFile status
                    if ($allCalled) {
                        $file->update(['is_done' => true]); // Ensure 'is_done' column exists in your model and database
                        Log::info('All numbers in file ' . $file->slug . ' have been called. The file is done.');
                    }
                }
            } else {
                Log::info('The current time is not within the specified range.');
                Log::info('The current time is not within the specified range for extension ' . $feed->extension . $to->format('r'));
            }
        }
    }
}
