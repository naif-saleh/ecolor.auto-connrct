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
            $responseState = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->token,
            ])->post(config('services.three_cx.api_url') . "/callcontrol/{$this->feed->extension}/makecall", [
                'destination' => $this->feed->mobile,
            ]);
            Log::info('Mobile: ' . $this->feed->mobile);
            if ($responseState->successful()) {
                $responseData = $responseState->json();
                AutoDailerReport::updateOrCreate(
                    ['call_id' => $responseData['result']['callid']],
                    [
                        'status' => $responseData['result']['status'],
                        'provider' => $this->feed->provider,
                        'extension' => $responseData['result']['dn'],
                        'phone_number' => $responseData['result']['party_caller_id'],
                    ]
                );
                $this->feed->update([
                    'state' => $responseData['result']['status'],
                    'call_date' => now(),
                    'call_id' => $responseData['result']['callid'],
                ]);
            } else {
                Log::error('Failed to make call. Response: ' . $responseState->body());
            }
        } catch (\Exception $e) {
            Log::error('An error occurred: ' . $e->getMessage());
        }



        $allCalled = AutoDailerUploadedData::where('file_id', $this->feed->file->id)
            ->where('state', 'new')
            ->count() == 0;

        if ($allCalled) {
            $this->feed->file->update(['is_done' => true]);
            Log::info('All numbers in file ' . $this->feed->file->slug . ' have been called. The file is marked as done.');
        } else {
            Log::info('Not all numbers in file ID ' . $this->feed->file->id . ' have been called.');
        }
    }
}
