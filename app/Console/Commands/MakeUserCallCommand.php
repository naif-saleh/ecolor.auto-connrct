<?php

namespace App\Console\Commands;


use App\Models\AutoDistributerExtensionFeed;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Models\AutoDailerReport;
use App\Models\AutoDistributerReport;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use App\Models\AutoDistributerFeedFile;

class MakeUserCallCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:make-user-call-command';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */

    public function handle()
    {

        $token = Cache::get('three_cx_token');

        Log::info('ADist: MakeCallCommand executed at ' . now());
        $providersFeeds = AutoDistributerFeedFile::all();

        // TODO: get only today feeds

        // Log::info('Providers Feeds:', $providersFeeds->toArray());


        //  Make Call For Providers
        foreach ($providersFeeds as $feed) {
            $ext_from = $feed->extension;
            $now = Carbon::now();

            // Parse the date and time from the data
            $from = Carbon::createFromFormat('Y-m-d H:i:s', $feed->date . ' ' . $feed->from);
            $to = Carbon::createFromFormat('Y-m-d H:i:s', $feed->date . ' ' . $feed->to);


            Log::info('ADist:  Make Provider Call, Active status ' . $feed->extension . $feed->on);

            // Check if the current time is within the range
            if ($now->between($from, $to) && $feed->on == 1) {
                Log::info('ADist: The current time is within the specified range.' . $feed->id);
                Log::info('ADist: The current time is within the specified range for extension ' . $feed->extension . $to->format('r'));
                // get all number in this feed

                //$mobiles = AutoDailerProviderFeed::find($feed->id);
                $providerFeeds = AutoDistributerExtensionFeed::byFeedFile($feed->id)
                    ->where('state', 'new')
                    ->get();
                Log::info('ADist: Provider ID ' . $feed->id . ' Feeds count = ' . $providerFeeds->count());

                $loop = 0;
                foreach ($providerFeeds as $mobile) {
                    Log::info('ADist: mobile ' . $mobile->mobile . ' in loop ' . $loop);

                    try {
                        $responseState = Http::withHeaders([
                            'Authorization' => 'Bearer ' . $token,
                        ])->post(config('services.three_cx.api_url') . "/callcontrol/{$ext_from}/makecall", [
                            'destination' => $mobile->mobile,
                        ]);

                        if ($responseState->successful()) {
                            $responseData = $responseState->json();
                            log::info("ADist: Make Call Data Response: " . print_r($responseData, TRUE));
                            $reports = AutoDistributerReport::firstOrCreate([
                                'call_id' => $responseData['result']['callid'],
                            ], [
                                'status' => $responseData['result']['status'],
                                'provider' => $mobile->extension->name,
                                'extension' => $responseData['result']['dn'],
                                'phone_number' => $responseData['result']['party_caller_id'],
                            ]);

                            $reports->save();
                            $mobile->update([
                                'state' => $responseData['result']['status'],
                                'call_date' => $now,
                                'call_id' => $responseData['result']['callid'],
                                'party_dn_type' => $responseData['result']['party_dn_type'] ?? null,
                            ]);


                            Log::info('ADist: Call successfully made for mobile ' . $mobile->mobile);
                        } else {
                            Log::error('ADist: Failed to make call for mobile ' . $mobile->mobile . '. Response: ' . $responseState->body());
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
