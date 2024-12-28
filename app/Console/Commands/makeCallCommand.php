<?php

namespace App\Console\Commands;

use App\Models\AutoDailerFeedFile;
use App\Models\AutoDailerProviderFeed;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Mockery\Expectation;

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

    /**
     * Execute the console command.
     */
    public function handle()
    {

        $token = Cache::get('three_cx_token');

        Log::info('MakeCallCommand executed at ' . now());
        $providersFeeds = AutoDailerFeedFile::all();
        // TODO: get only today feeds




        foreach ($providersFeeds as $feed) {
            $ext_from = $feed->extension;
            $now = Carbon::now();

            // Parse the date and time from the data
            $from = Carbon::createFromFormat('Y-m-d H:i:s', $feed->date . ' ' . $feed->from);
            $to = Carbon::createFromFormat('Y-m-d H:i:s', $feed->date . ' ' . $feed->to);


            Log::info('on status ' . $feed->extension . $feed->on);

            // Check if the current time is within the range
            if ($now->between($from, $to) && $feed->on == 1) {
                Log::info('The current time is within the specified range.' . $feed->id);
                Log::info('The current time is within the specified range for extension ' . $feed->extension . $to->format('r'));
                // get all number in this feed

                //$mobiles = AutoDailerProviderFeed::find($feed->id);
                $providerFeeds = AutoDailerProviderFeed::byFeedFile($feed->id)
                    ->where('state', 'new')
                    ->get();

                $loop = 0;
                foreach ($providerFeeds as  $mobile) {
                    Log::info('mobile ' . $mobile->mobile . ' in loop ' . $loop);
                    // TODO: make call

                    try {
                       
                 
                    $responseState = Http::withHeaders([
                        'Authorization' => 'Bearer ' . $token,
                    ])->post(
                        config('services.three_cx.api_url') . "/callcontrol/{$ext_from}/makecall",

                        [
                            'destination' => $mobile->mobile,
                        ]
                    );

                    if ($responseState->successful()) {
                        $responseData = $responseState->json();
                        $mobile->update([
                            'state' => $responseData['result']['status'],
                            'call_date' => $now,
                            'call_id' => $responseData['result']['id'],
                            'party_dn_type' => $responseData['result']['party_dn_type'],
                        ]);
                        Log::info('Updated state to "called" for mobile ' . $mobile->mobile);
                    } else {
                        Log::error('Failed to make call for mobile ' . $mobile->mobile . '. Response: ' . $responseState->body());
                    }

                   
                    //TODO: if failed

                    $responseData = $responseState->json();

                    //   Log::debug('makeCallCommand responseData ' . print_r($responseData, TRUE));

                   

                    $loop++;
                    // TODO: when you call the api you must change the status
                } catch (Expectation $e) {
                    //throw $th;
                }
                }
            } else {
                Log::info('The current time is not within the specified range.');
                Log::info('The current time is not within the specified range for extension ' . $feed->extension . $to->format('r'));
            }
        }
    }
}
