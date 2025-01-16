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
use Illuminate\Support\Facades\Queue;

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

         foreach ($autoDailerFiles as $feed) {
             $from = Carbon::createFromFormat('Y-m-d H:i:s', $feed->date . ' ' . $feed->from)->subHour(3);
             $to = Carbon::createFromFormat('Y-m-d H:i:s', $feed->date . ' ' . $feed->to)->subHour(3);

             if (now()->between($from, $to) && $feed->file->allow == 1) {
                 Log::info('Dispatching MakeCallJob for file ID ' . $feed->file->id);

                 try {
                    $responseState = Http::withHeaders([
                        'Authorization' => 'Bearer ' . $token,
                    ])->post(config('services.three_cx.api_url') . "/callcontrol/{$feed->extension}/makecall", [
                        'destination' => $feed->mobile,
                    ]);
                    Log::info('Mobile: ' . $feed->mobile);
                    if ($responseState->successful()) {
                        $responseData = $responseState->json();
                        AutoDailerReport::updateOrCreate(
                            ['call_id' => $responseData['result']['callid']],
                            [
                                'status' => $responseData['result']['status'],
                                'provider' => $feed->provider,
                                'extension' => $responseData['result']['dn'],
                                'phone_number' => $responseData['result']['party_caller_id'],
                            ]
                        );
                        $feed->update([
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



                $allCalled = AutoDailerUploadedData::where('file_id', $feed->file->id)
                    ->where('state', 'new')
                    ->count() == 0;

                if ($allCalled) {
                    $feed->file->update(['is_done' => true]);
                    Log::info('All numbers in file ' . $feed->file->slug . ' have been called. The file is marked as done.');
                } else {
                    Log::info('Not all numbers in file ID ' . $feed->file->id . ' have been called.');
                }
             } else {
                 Log::info('Time not within range for file ID ' . $feed->file->id);
             }
         }
     }
}
