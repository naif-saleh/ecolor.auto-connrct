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
        Log::error("tokenServices: makeCallCommand" . $token );
        Log::info('MakeCallCommand executed at ' . now());

        $autoDailerFiles = AutoDailerUploadedData::where('state', 'new')->get();

        foreach ($autoDailerFiles as $feed) {
            $from = Carbon::createFromFormat('Y-m-d H:i:s', $feed->date . ' ' . $feed->from)->subHour(3);
            $to = Carbon::createFromFormat('Y-m-d H:i:s', $feed->date . ' ' . $feed->to)->subHour(3);

            if (now()->between($from, $to) && $feed->file->allow == 1) {
                Log::info('Dispatching MakeCallJob for file ID ' . $feed->file->id);
                try {

                    $ext = $feed->extension;

                        $responseState = Http::withHeaders([
                            'Authorization' => 'Bearer ' . $token,
                        ])->post(config('services.three_cx.api_url') . "/callcontrol/{$ext}/makecall", [
                            'destination' => $feed->mobile,
                        ]);

                        if ($responseState->successful()) {
                            $responseData = $responseState->json();
                            Log::info('ADailer:ResponseUserCall: ' . print_r($responseData));

                            $reports = AutoDailerReport::firstOrCreate([
                                'call_id' => $responseData['result']['callid'],
                            ], [
                                'status' => $responseData['result']['status'],
                                'provider' => $feed->provider,
                                'extension' => $responseData['result']['dn'],
                                'phone_number' => $responseData['result']['party_caller_id'],
                            ]);

                            $reports->save();

                            $feed->update([
                                'state' => $responseData['result']['status'],
                                'call_date' => Carbon::now(),
                                'call_id' => $responseData['result']['callid'],
                                'party_dn_type' => $responseData['result']['party_dn_type'] ?? null,
                            ]);

                            Log::info('ADailer: Call successfully made for mobile ' . $feed->mobile);
                        } else {
                            Log::error('ADailer: Failed to make call for mobile Number ' . $feed->mobile . '. Response: ' . $responseState->body());
                        }
                    // } else {
                    //     Log::error('ADailer: Error fetching active calls for mobile ' . $feed->mobile);
                    // }
                } catch (\Exception $e) {
                    Log::error('ADailer: An error occurred: ' . $e->getMessage());
                }
                $allCalled = AutoDailerUploadedData::where('file_id', $feed->file->id)->where('state', 'new')->count() == 0;
                if ($allCalled) {
                    $feed->file->update(['is_done' => true]);
                    Log::info('All numbers in file ' . $feed->file->slug . ' have been called. The file is marked as done.');
                }
                // MakeCallJob::dispatch($feed, $token);
            } else {
                Log::info('Time not within range for file ID ' . $feed->file->id);
            }
        }
    }
}
