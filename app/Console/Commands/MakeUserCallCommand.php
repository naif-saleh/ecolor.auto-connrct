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
    

        $autoDailerFiles = AutoDistributorUploadedData::where('state','new')->orderBy('created_at', 'desc')->get();


        $now = Carbon::now();

        foreach ($autoDailerFiles as $feed) {
            Log::info('count of calls in each time: ' . $autoDailerFiles->count());
            // Calculate the time range by subtracting 3 hours from the given time
            $from = Carbon::createFromFormat('Y-m-d H:i:s', $feed->date . ' ' . $feed->from)->subHour(3);
            $to = Carbon::createFromFormat('Y-m-d H:i:s', $feed->date . ' ' . $feed->to)->subHour(3);

            // Check if the current time is within the range for this file_id
            Log::info('Current time: ' . $now);
            Log::info('Time range for file ID ' . $feed->file->id . ' From: ' . $from . ' To: ' . $to);

            if ($now > $from && $now < $to && $feed->file->allow == 1) {
                // The time is valid for this file_id, proceed with processing
                Log::info('Processing file with ID ' . $feed->file->id);

                try {
                    if ($feed->userStatus === "Available") {
                        $ext = $feed->extension;
                        $filter = "contains(Caller, '{$ext}')";
                        $url = config('services.three_cx.api_url') . "/xapi/v1/ActiveCalls?\$filter=" . urlencode($filter);

                        // Fetch active calls from API
                        $token = $this->tokenService->getToken();
                        Log::info("tokenServices: MakeUserCallCommand" . $token );
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
                                            'destination' => $feed->mobile,
                                        ]);

                                        if ($responseState->successful()) {
                                            $responseData = $responseState->json();
                                            Log::info('Adist:ResponseUserCall: ' . print_r($responseData));

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
                                            Log::error('ADist: Failed to make call for mobile Number ' . $feed->mobile . '. Response: ' . $responseState->body());
                                        }
                                        break; // Exit loop after making the call
                                    }
                                }
                            } else {
                                Log::error('ADist: Error fetching devices for extension ' . $ext);
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

    }



}
