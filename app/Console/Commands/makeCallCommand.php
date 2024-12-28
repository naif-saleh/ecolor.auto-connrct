<?php

namespace App\Console\Commands;

use App\Models\AutoDailerFeedFile;
use App\Models\AutoDailerProviderFeed;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;


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
                            'call_id' => $responseData['result']['callid'],
                            'party_dn_type' => $responseData['result']['party_dn_type'],
                        ]);
                        Log::info('Updated state to "called" for mobile ' . $mobile->mobile);
                    } else {
                        Log::error('Failed to make call for mobile ' . $mobile->mobile . '. Response: ' . $responseState->body());
                    }

                    // [2] => Array
                    // (
                    //     [id] => 1365
                    //     [status] => Connected
                    //     [dn] => 209
                    //     [party_caller_name] =>
                    //     [party_dn] => 666
                    //     [party_caller_id] => 101
                    //     [party_did] =>
                    //     [device_id] => sip:209@127.0.0.1:5483
                    //     [party_dn_type] => Wspecialmenu
                    //     [direct_control] =>
                    //     [originated_by_dn] =>
                    //     [originated_by_type] => None
                    //     [referred_by_dn] =>
                    //     [referred_by_type] => None
                    //     [on_behalf_of_dn] =>
                    //     [on_behalf_of_type] => None
                    //     [callid] => 1183
                    //     [legid] => 1
                    // )


        //             [2] => Array
        // (
        //     [id] => 1365
        //     [status] => Dialing
        //     [dn] => 209
        //     [party_caller_name] =>
        //     [party_dn] => 666
        //     [party_caller_id] => 101
        //     [party_did] =>
        //     [device_id] => sip:209@127.0.0.1:5483
        //     [party_dn_type] => Wspecialmenu
        //     [direct_control] =>
        //     [originated_by_dn] =>
        //     [originated_by_type] => None
        //     [referred_by_dn] =>
        //     [referred_by_type] => None
        //     [on_behalf_of_dn] =>
        //     [on_behalf_of_type] => None
        //     [callid] => 1183
        //     [legid] => 1
        // )


                    //TODO: if failed

                    $responseData = $responseState->json();

                    Log::debug('makeCallCommand responseData ' . print_r($responseData, TRUE));

                    /*

 [finalstatus] => Success
    [reason] => NotSpecified
    [result] => Array
        (
            [id] => 78
            [status] => Dialing
            [dn] => 209
            [party_caller_name] =>
            [party_dn] =>
            [party_caller_id] => 101
            [party_did] =>
            [device_id] => sip:209@127.0.0.1:5483
            [party_dn_type] => None
            [direct_control] =>
            [originated_by_dn] =>
            [originated_by_type] => None
            [referred_by_dn] =>
            [referred_by_type] => None
            [on_behalf_of_dn] =>
            [on_behalf_of_type] => None
            [callid] => 26
            [legid] => 1
        )

    [reasontext] => Dialing
    */


                    $loop++;
                    // TODO: when you call the api you must change the status

                }
            } else {
                Log::info('The current time is not within the specified range.');
                Log::info('The current time is not within the specified range for extension ' . $feed->extension . $to->format('r'));
            }
        }
    }
}
