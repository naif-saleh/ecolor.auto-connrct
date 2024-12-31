<?php

namespace App\Console\Commands;

use App\Models\AutoDistributerFeedFile;
use App\Models\AutoDistributerExtensionFeed;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Models\AutoDistributerReport;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

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

        Log::info('MakeUserCallCommand executed at ' . now());
        $usersFeeds = AutoDistributerFeedFile::all();


        // Make Call For Users
        foreach ($usersFeeds as $feed) {
            $ext_from = $feed->extension;
            $now = Carbon::now();
            $from = Carbon::createFromFormat('Y-m-d H:i:s', $feed->date . ' ' . $feed->from);
            $to = Carbon::createFromFormat('Y-m-d H:i:s', $feed->date . ' ' . $feed->to);



            if ($now->between($from, $to) && $feed->on == 1) {
                Log::info('Succesfully....{The current time is within the specified range.' . " And File is Active");
                Log::info('The current time is within the specified range for extension ' . $feed->extension . $to->format('r') . " }");
                // get all number in this feed

                $providerFeeds = AutoDistributerExtensionFeed::byFeedFile($feed->id)
                    ->where('state', 'new')
                    ->get();

                $loop = 0;
                foreach ($providerFeeds as $mobile) {
                    Log::info('Processing mobile ' . $mobile->mobile . ' with name ' . $mobile->name);

                    try {
                        // Make the call using the 3CX API
                        $responseState = Http::withHeaders([
                            'Authorization' => 'Bearer ' . $token,
                        ])->post(config('services.three_cx.api_url') . "/callcontrol/{$ext_from}/makecall", [
                            'destination' => $mobile->mobile,
                        ]);

                        if ($responseState->successful()) {
                            $responseData = $responseState->json();

                            // Save or update the report
                            $reports = AutoDistributerReport::updateOrCreate([
                                'call_id' => $responseData['result']['id'],
                            ], [
                                'status' => $responseData['result']['status'],
                                'provider' => $mobile->provider->name,
                                'extension' => $responseData['result']['dn'],
                                'phone_number' => $responseData['result']['party_caller_id'] ?? null,
                            ]);

                            Log::info('Call successfully made for mobile ' . $mobile->mobile);

                            // Update the mobile record with call details
                            $mobile->update([
                                'state' => "Dialing",
                                'call_date' => $now,
                                'call_id' => $responseData['result']['id'],
                                'party_dn_type' => $responseData['result']['party_dn_type'] ?? null,
                            ]);

                            // Break the loop if a call is successfully made
                            break;
                        } else {
                            Log::error('Failed to make call for mobile ' . $mobile->mobile . '. Response: ' . $responseState->body());
                        }
                    } catch (\Exception $e) {
                        Log::error('An error occurred: ' . $e->getMessage());
                    }
                }
            } else {
                Log::info('Warning.......{The The File is Inactive.' . $feed->id . " or ");
                Log::info('The current time is within the specified range for extension ' . $feed->extension . $to->format('r') . "}");
            }
        }
    }
}
