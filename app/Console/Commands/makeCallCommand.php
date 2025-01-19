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
        Log::info('MakeCallCommand executed at ' . now());

        $autoDailerFiles = AutoDailerFile::all();

        foreach ($autoDailerFiles as $feed) {
            // Create from and to date objects adjusted by -3 hours
            $from = Carbon::createFromFormat('Y-m-d H:i:s', $feed->date . ' ' . $feed->from)->subHours(3);
            $to = Carbon::createFromFormat('Y-m-d H:i:s', $feed->date . ' ' . $feed->to)->subHours(3);

            // Check if the current time is within the range and the file is allowed
            if (now()->between($from, $to) && $feed->allow == 1) {
                Log::info('******TIME IN*******Time is within range for file ID ' . $feed->id);

                $data = AutoDailerUploadedData::where('file_id', $feed->id)->where('state', 'new')->get();
                foreach ($data as $feedData) {

                    try {
                        $token = $this->tokenService->getToken();
                        $ext = $feedData->extension;

                        $responseState = Http::withHeaders([
                            'Authorization' => 'Bearer ' . $token,
                        ])->post(config('services.three_cx.api_url') . "/callcontrol/{$ext}/makecall", [
                            'destination' => $feedData->mobile,
                        ]);

                        if ($responseState->successful()) {
                            $responseData = $responseState->json();
                            Log::info('ADailer:ResponseUserCall: ' . print_r($responseData));

                            $reports = AutoDailerReport::firstOrCreate([
                                'call_id' => $responseData['result']['callid'],
                            ], [
                                'status' => $responseData['result']['status'],
                                'provider' => $feedData->provider,
                                'extension' => $responseData['result']['dn'],
                                'phone_number' => $responseData['result']['party_caller_id'],
                            ]);

                            $reports->save();

                            $feedData->update([
                                'state' => "Routing",
                                'call_date' => Carbon::now(),
                                'call_id' => $responseData['result']['callid'],
                                'party_dn_type' => $responseData['result']['party_dn_type'] ?? null,
                            ]);

                            Log::info('ADailer: Call successfully made for mobile ' . $feedData->mobile);
                        } else {
                            Log::error('ADailer: Failed to make call for mobile Number ' . $feedData->mobile . '. Response: ' . $responseState->body());
                        }

                    } catch (\Exception $e) {
                        Log::error('ADailer: An error occurred: ' . $e->getMessage());
                    }
                    $allCalled = AutoDailerUploadedData::where('file_id', $feedData->file->id)->where('state', 'new')->count() == 0;
                    if ($allCalled) {
                        $feedData->file->update(['is_done' => true]);
                        Log::info('All numbers in file ' . $feedData->file->slug . ' have been called. The file is marked as done.');
                    }
                }
            } else {
                Log::info('******TIME OUT*******Time is not within range for file ID ' . $feed->id);
            }
        }
    }
}
