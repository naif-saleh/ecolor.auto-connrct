<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\AutoDailerUploadedData;
use App\Models\AutoDailerReport;
use Carbon\Carbon;

class MakeCallJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $feed;
    protected $token;

    /**
     * Create a new job instance.
     *
     * @param  $feed
     * @param  string  $token
     * @return void
     */
    public function __construct($feed, $token)
    {
        $this->feed = $feed;
        $this->token = $token;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        try {

            $ext = $this->feed->extension;
            $filter = "contains(Caller, '{$ext}')";
            $url = config('services.three_cx.api_url') . "/xapi/v1/ActiveCalls?\$filter=" . urlencode($filter);

            // Fetch active calls from API
            $activeCallsResponse = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->token,
            ])->get($url);

            if ($activeCallsResponse->failed()) {
                Log::error('ADist: Failed to fetch active calls for mobile ' . $this->feed->mobile . '. Response: ' . $activeCallsResponse->body());
                return;
            }

            if ($activeCallsResponse->successful()) {
                $activeCalls = $activeCallsResponse->json();

                // if (!empty($activeCalls['value'])) {
                //     Log::info("Active calls detected for extension {$ext}. Skipping call for mobile {$this->feed->mobile}.");
                //     return; // Skip this number if active calls exist
                // }




                $responseState = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $this->token,
                ])->post(config('services.three_cx.api_url') . "/callcontrol/{$ext}/makecall", [
                    'destination' => $this->feed->mobile,
                ]);

                if ($responseState->successful()) {
                    $responseData = $responseState->json();
                    Log::info('Adist:ResponseUserCall: ' . print_r($responseData));

                    $reports = AutoDailerReport::firstOrCreate([
                        'call_id' => $responseData['result']['callid'],
                    ], [
                        'status' => $responseData['result']['status'],
                        'provider' => $this->feed->user,
                        'extension' => $responseData['result']['dn'],
                        'phone_number' => $responseData['result']['party_caller_id'],
                    ]);

                    $reports->save();

                    $this->feed->update([
                        'state' => $responseData['result']['status'],
                        'call_date' => Carbon::now(),
                        'call_id' => $responseData['result']['callid'],
                        'party_dn_type' => $responseData['result']['party_dn_type'] ?? null,
                    ]);

                    Log::info('ADist: Call successfully made for mobile ' . $this->feed->mobile);
                } else {
                    Log::error('ADist: Failed to make call for mobile Number ' . $this->feed->mobile . '. Response: ' . $responseState->body());
                }
            } else {
                Log::error('ADist: Error fetching active calls for mobile ' . $this->feed->mobile);
            }
        } catch (\Exception $e) {
            Log::error('ADist: An error occurred: ' . $e->getMessage());
        }

        $allCalled = AutoDailerUploadedData::where('file_id', $this->feed->file->id)->where('state', 'new')->count() == 0;
                if ($allCalled) {
                    $this->feed->file->update(['is_done' => true]);
                    Log::info('All numbers in file ' . $this->feed->file->slug . ' have been called. The file is marked as done.');
                }


    }
}
