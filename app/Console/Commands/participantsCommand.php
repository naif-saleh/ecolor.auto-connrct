<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Models\AutoDailerFeedFile;
use App\Models\AutoDailerProviderFeed;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class participantsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:participants-command';
    protected $record;
    protected $token;

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
        Log::info('participantsCommand executed at ' . now());
        $token = Cache::get('three_cx_token');

        $providersFeeds = AutoDailerFeedFile::all();
        // TODO: get only today feeds

        foreach ($providersFeeds as $feed) {
            
            $ext_from = $feed->extension;
            $now = Carbon::now();


            $responseState = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token,
            ])->get(config('services.three_cx.api_url') . "/callcontrol/{$ext_from}/participants");

            $responseData = $responseState->json();

            Log::debug('responseData ' . print_r($responseData, TRUE));

            // if ($responseState->successful()) {
            //     $responseData = $responseState->json();
            //     foreach ($responseData as $participant) {

            //         $partyDnType = $participant['party_dn_type'] ?? "None";

            //         if (in_array($partyDnType, ["Wextension", "Wspecialmenu", "None"])) {
            //             break 2;
            //         }
            //     }
            // }


            if ($responseState->failed()) {
                //TODO: set message
            }


            /*

         [id] => 6
            [status] => Connected
            [dn] => 209
            [party_caller_name] => Naif saleh
            [party_dn] => 101
            [party_caller_id] => 101
            [party_did] =>
            [device_id] => sip:209@127.0.0.1:5483
            [party_dn_type] => Wextension
            [direct_control] =>
            [originated_by_dn] =>
            [originated_by_type] => None
            [referred_by_dn] =>
            [referred_by_type] => None
            [on_behalf_of_dn] =>
            [on_behalf_of_type] => None
            [callid] => 2
            [legid] => 2
        )
        */
        }
    }
}
