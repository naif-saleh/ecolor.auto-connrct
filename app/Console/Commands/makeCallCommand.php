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
        $now = Carbon::now(); // Calculate the current time once

        foreach ($autoDailerFiles as $feed) {
            // Recalculate 'from' and 'to' for each feed
            $from = Carbon::createFromFormat('Y-m-d H:i:s', $feed->date . ' ' . $feed->from)->subHour(2);
            $to = Carbon::createFromFormat('Y-m-d H:i:s', $feed->date . ' ' . $feed->to)->subHour(2);

            // Check if the current time is within the range for this particular file
            if ($now->between($from, $to) && $feed->file->allow == 1) {
                Log::info('Processing file with ID ' . $feed->file->id);
                Log::info('File Active: ' . $feed->file->slug);
                Log::info('File Allow Status: ' . $feed->file->allow);

                // Get all provider feeds for this particular file
                $providerFeeds = AutoDailerUploadedData::where('file_id', $feed->file->id)->where('state', 'new')->get();
                foreach ($providerFeeds as $mobile) {
                    Log::info('Processing mobile ' . $mobile->mobile);

                    // Make API call
                    try {
                        $responseState = Http::withHeaders([
                            'Authorization' => 'Bearer ' . $token,
                        ])->post(config('services.three_cx.api_url') . "/callcontrol/{$mobile->extension}/makecall", [
                            'destination' => $mobile->mobile,
                        ]);

                        if ($responseState->successful()) {
                            $responseData = $responseState->json();
                            AutoDailerReport::firstOrCreate([
                                'call_id' => $responseData['result']['callid'],
                                'status' => $responseData['result']['status'],
                                'provider' => $mobile->provider,
                                'extension' => $responseData['result']['dn'],
                                'phone_number' => $responseData['result']['party_caller_id'],
                            ]);

                            $mobile->update([
                                'state' => $responseData['result']['status'],
                                'call_date' => $now,
                                'call_id' => $responseData['result']['callid'],
                            ]);
                            Log::info('Call successfully made for mobile ' . $mobile->mobile);
                        } else {
                            Log::error('Failed to make call for mobile ' . $mobile->mobile . '. Response: ' . $responseState->body());
                        }
                    } catch (\Exception $e) {
                        Log::error('An error occurred: ' . $e->getMessage());
                    }
                }

                // Check if all mobiles in this file are called
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
