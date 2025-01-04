<?php

namespace App\Console\Commands;


use App\Models\AutoDistributerExtensionFeed;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Models\AutoDailerReport;
use App\Models\AutoDistributererExtension;
use App\Models\AutoDistributerReport;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use App\Models\AutoDistributerFeedFile;
use App\Services\ThreeCXTokenService;
class MakeUserCallCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:make-user-call-command';
    protected $threeCXTokenService;

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    public function __construct(ThreeCXTokenService $threeCXTokenService)
    {
        parent::__construct(); // This is required
        $this->threeCXTokenService = $threeCXTokenService;
    }

    /**
     * Execute the console command.
     */

    public function handle()
    {

       // $token = Cache::get('three_cx_token');
$token = $this->threeCXTokenService->fetchToken();
        Log::info('ADist: MakeCallCommand executed at ' . now());
        $providersFeeds = AutoDistributerFeedFile::all();



        //  Make Call For Providers
        foreach ($providersFeeds as $feed) {
            $ext_from = $feed->extension;

            $now = Carbon::now();

            // Parse the date and time from the data
            $from = Carbon::createFromFormat('Y-m-d H:i:s', $feed->date . ' ' . $feed->from);
            $to = Carbon::createFromFormat('Y-m-d H:i:s', $feed->date . ' ' . $feed->to);


            Log::info('ADist:  Make Provider Call, Active status ' . $feed->extension . $feed->on);
            Log::info('ADist:  User Status' . $feed->userStatus);

            // Check if the current time is within the range
            if ($now->between($from, $to) && $feed->on == 1) {
                Log::info('ADist: The current time is within the specified range.' . $feed->id);
                Log::info('ADist: The current time is within the specified range for extension ' . $feed->extension . $to->format('r'));
                // get all number in this feed

                //$mobiles = AutoDailerProviderFeed::find($feed->id);
                $providerFeeds = AutoDistributerExtensionFeed::byFeedFile($feed->id)
                    ->where('state', 'new')
                    ->get();
                // Log::info('ADist: Provider ID ' . $feed->id . ' User Status = ' . $feed->userStatus);

                $loop = 0;
                foreach ($providerFeeds as $mobile) {
                    Log::info('ADist: Processing mobile ' . $mobile->mobile);

                    try {
                        if ($feed->userStatus === "Available") {
                            // Check if there are active calls for this extension
                            $filter = "contains(Caller, '{$ext_from}')";
                            $url = "https://ecolor.3cx.agency/xapi/v1/ActiveCalls?\$filter=" . urlencode($filter);

                            $activeCallsResponse = Http::withHeaders([
                                'Authorization' => 'Bearer ' . $token,
                            ])->get($url);

                            if ($activeCallsResponse->successful()) {
                                $activeCalls = $activeCallsResponse->json();

                                if (!empty($activeCalls['value'])) {
                                    Log::info("Active calls detected for extension {$ext_from}. Skipping call for mobile {$mobile->mobile}.");
                                    continue; // Skip this number if active calls exist
                                }

                                // No active calls, proceed to make the call
                                $responseState = Http::withHeaders([
                                    'Authorization' => 'Bearer ' . $token,
                                ])->post(config('services.three_cx.api_url') . "/callcontrol/{$ext_from}/makecall", [
                                    'destination' => $mobile->mobile,
                                ]);

                                if ($responseState->successful()) {
                                    $responseData = $responseState->json();

                                    // Handle the report creation and update
                                    $reports = AutoDistributerReport::firstOrCreate([
                                        'call_id' => $responseData['result']['callid'],
                                    ], [
                                        'status' => $responseData['result']['status'],
                                        'provider' => $mobile->extension->name,
                                        'extension' => $responseData['result']['dn'],
                                        'phone_number' => $responseData['result']['party_caller_id'],
                                    ]);

                                    $reports->save();

                                    // Update the mobile record
                                    $mobile->update([
                                        'state' => $responseData['result']['status'],
                                        'call_date' => now(),
                                        'call_id' => $responseData['result']['callid'],
                                        'party_dn_type' => $responseData['result']['party_dn_type'] ?? null,
                                    ]);

                                    Log::info('ADist: Call successfully made for mobile ' . $mobile->mobile);
                                } else {
                                   
                                    Log::error('ADist: Failed to make call for mobile ' . $mobile->mobile . '. Response: ' . $responseState->body());
                                    Log::info('ADist:  Response Status Code: ' . $responseState->status());
                                    Log::info('ADist:  Full Response: ' . print_r($responseState, TRUE));
                                    Log::info('ADist: Headers: ' . json_encode($responseState->headers()));
                                }

                                // Add a 30-second delay between calls
                                Log::info("ADist: Waiting for 30 seconds before making the next call.");
                                // sleep(100); // Wait for 30 seconds before continuing to the next iteration

                            } else {
                                Log::error('ADist: Error fetching active calls for mobile ' . $mobile->mobile . '. Response: ' . $activeCallsResponse->body());
                            }
                        }
                    } catch (\Exception $e) {
                        Log::error('ADist: An error occurred: ' . $e->getMessage());
                    }
                }
            } else {
                Log::info('The current time is not within the specified range.');
                Log::info('The current time is not within the specified range for extension ' . $feed->extension . $to->format('r'));
            }
        }
    }
}
