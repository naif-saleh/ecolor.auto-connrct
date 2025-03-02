<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Models\ADialProvider;
use App\Models\ADialData;
use App\Models\ADialFeed;
use App\Models\General_Setting;
use App\Models\CountCalls;
use App\Services\ThreeCxService;
use App\Models\AutoDailerReport;


class ADialMakeCallCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:ADial-make-call-command';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command to make auto dialer calls';
    protected $threeCxService;

    public function __construct(ThreeCxService $threeCxService)
    {
        parent::__construct();
        $this->threeCxService = $threeCxService;
    }



    public function handle()
    {
        Log::info('ADialMakeCallCommand started at ' . Carbon::now());

        // Check call time constraints
        $timezone = config('app.timezone');
        $callTimeStart = General_Setting::get('call_time_start');
        $callTimeEnd = General_Setting::get('call_time_end');

        if (!$callTimeStart || !$callTimeEnd) {
            Log::warning("ADialMakeCallCommand: ⚠️ Call time settings not configured.");
            return;
        }

        $now = now()->timezone($timezone);
        $globalStart = Carbon::parse(date('Y-m-d') . ' ' . $callTimeStart)->timezone($timezone);
        $globalEnd = Carbon::parse(date('Y-m-d') . ' ' . $callTimeEnd)->timezone($timezone);

        if (!$now->between($globalStart, $globalEnd)) {
            Log::info('ADialMakeCallCommand: 📞❌ Calls are not allowed at this time.');
            return;
        }

        Log::info("ADialMakeCallCommand: Allowed call window: {$globalStart} to {$globalEnd}");

        // Process each provider
        $providers = ADialProvider::all();
        Log::info("ADialMakeCallCommand: Found {$providers->count()} providers to process");

        foreach ($providers as $provider) {
            $this->processProvider($provider, $now, $timezone);
        }

        Log::info('ADialMakeCallCommand: 📞✅ ADialMakeCallCommand execution completed.');
    }

    protected function processProvider($provider, $now, $timezone)
    {
        $files = ADialFeed::where('provider_id', $provider->id)
            ->whereDate('date', today())
            ->where('allow', true)
            ->get();

        Log::info("ADialMakeCallCommand: Found {$files->count()} feeds for provider {$provider->name}");

        foreach ($files as $file) {
            $this->processFile($file, $provider, $now, $timezone);
        }
    }

    protected function processFile($file, $provider, $now, $timezone)
    {
        // Check time window for this file
        $from = Carbon::parse("{$file->date} {$file->from}")->timezone($timezone);
        $to = Carbon::parse("{$file->date} {$file->to}")->timezone($timezone);

        if (!$now->between($from, $to)) {
            Log::info("ADialMakeCallCommand: ❌ Skipping File ID {$file->id}, not in call window.");
            return;
        }

        // Check current call limits
        $callLimit = CountCalls::get('number_calls');

        try {
            $activeCalls = $this->threeCxService->getAllActiveCalls();
            $currentCalls = count($activeCalls['value'] ?? []);

            Log::info("ADialMakeCallCommand: Current active calls: {$currentCalls}, Limit: {$callLimit}");
        } catch (\Throwable $e) {
            Log::error("ADialMakeCallCommand: ❌ Error fetching active calls: " . $e->getMessage());
            return;
        }

        // if($activeCalls > 96){
        //     Log::error("ADialMakeCallCommand: ❌ active calls retched size: " . $activeCalls);
        //     return;
        // }

        $callsToMake = max(0, $callLimit - $currentCalls);


        // Make calls
        $feedData = ADialData::where('feed_id', $file->id)
            ->where('state', 'new')
            ->take($callsToMake)
            ->get();

        Log::info("ADialMakeCallCommand: Making {$feedData->count()} calls for feed ID {$file->id}");

        foreach ($feedData as $data) {
            if (!$now->between($from, $to)) {
                Log::info("ADialMakeCallCommand: ❌ Call window expired during execution.");
                break;
            }

            $this->makeCallWithRetries($data, $provider);
        }

        // Mark file as done if all calls processed
        if (!ADialData::where('feed_id', $file->id)->where('state', 'new')->exists()) {
            $file->update(['is_done' => true]);
            Log::info("ADialMakeCallCommand: ✅ All numbers called for File ID: {$file->id}");
        }
    }

    protected function makeCallWithRetries($data, $provider)
    {

        try {
            // Make the call
            $responseData = $this->threeCxService->makeCall(
                $provider->extension,
                $data->mobile
            );

            $callId = $responseData['result']['callid'];
            $status = $responseData['result']['status'];


            // Update call record
            Log::info("ADialMakeCallCommand: ✅ Call successful. Call ID: " . $responseData['result']['callid']);
            AutoDailerReport::create(

                [
                    'call_id' => $callId,
                    'status' => $status,
                    'provider' => $provider->name,
                    'extension' => $provider->extension,
                    'phone_number' => $data->mobile
                ]
            );


            // Update dial data
            $data->update([
                'state' => $status,
                'call_date' => now(),
                'call_id' => $callId
            ]);

            Log::info("ADialMakeCallCommand: ✅ Mobile Number Called and Saved Successfully: {$data->mobile}, Call ID: {$callId}");


            // Add a small delay between calls
            usleep(300000); // 300ms


        } catch (\Exception $e) {
            Log::error("ADialMakeCallCommand: ❌ ADial MakeCall: Call Failed to number {$data->mobile}: " . $e->getMessage());
        }
    }
}
