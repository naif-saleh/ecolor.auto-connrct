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
use App\Jobs\MakeCallJob;
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
        $token = $this->tokenService->getToken();
        Log::info('MakeCallCommand executed at ' . now());
        $autoDailerFiles = AutoDailerUploadedData::where('state', 'new')->get();

        $now = Carbon::now();

        foreach ($autoDailerFiles as $feed) {
            $from = Carbon::createFromFormat('Y-m-d H:i:s', $feed->date . ' ' . $feed->from)->subHour(2);
            $to = Carbon::createFromFormat('Y-m-d H:i:s', $feed->date . ' ' . $feed->to)->subHour(2);

            if ($now > $from && $now < $to && $feed->file->allow == 1) {
                Log::info('Processing file with ID ' . $feed->file->id);

                // $providerFeeds = AutoDailerUploadedData::where('file_id', $feed->file->id)
                //     ->where('state', 'new')
                //     ->get();


                    try {
                        // Make the call
                        $responseState = Http::withHeaders([
                            'Authorization' => 'Bearer ' . $token,
                        ])->post(config('services.three_cx.api_url') . "/callcontrol/{$feed->extension}/makecall", [
                            'destination' => $feed->mobile,
                        ]);

                        // Wait for the response before proceeding to the next call
                        if ($responseState->successful()) {
                            $responseData = $responseState->json();

                            // Update or create report
                            AutoDailerReport::firstOrCreate([
                                'call_id' => $responseData['result']['callid'],
                                'status' => $responseData['result']['status'],
                                'provider' => $feed->provider,
                                'extension' => $responseData['result']['dn'],
                                'phone_number' => $responseData['result']['party_caller_id'],
                            ]);

                            // Update the status for the current mobile
                            $feed->update([
                                'state' => $responseData['result']['status'],
                                'call_date' => Carbon::now(),
                                'call_id' => $responseData['result']['callid'],
                            ]);

                            Log::info('Call successfully made for mobile ' . $feed->mobile);
                        } else {
                            Log::error('Failed to make call for mobile ' . $feed->mobile . '. Response: ' . $responseState->body());
                        }
                    } catch (\Exception $e) {
                        Log::error('An error occurred: ' . $e->getMessage());
                    }


                $allCalled = AutoDailerUploadedData::where('file_id', $feed->file->id)->where('state', 'new')->count() == 0;
                if ($allCalled) {
                    $feed->file->update(['is_done' => true]);
                    Log::info('All numbers in file ' . $feed->file->slug . ' have been called. The file is marked as done.');
                }
            } else {
                Log::info('The current time is not within the specified range for file ID ' . $feed->file->id);
            }
        }
    }
}
