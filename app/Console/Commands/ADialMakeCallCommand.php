<?php

namespace App\Console\Commands;

use App\Models\ToQueue;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
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

    /**
     * ThreeCx Service
     *
     * @var ThreeCxService
     */
    protected $threeCxService;

    /**
     * Lock timeout in seconds
     *
     * @var int
     */
    protected $lockTimeout = 600;

    /**
     * Minimum time between calls to the same number (minutes)
     *
     * @var int
     */
    protected $duplicateCallWindow = 5;

    /**
     * Maximum calls per minute
     *
     * @var int
     */
    protected $maxCallsPerMinute = 96;

    /**
     * Delay between calls in microseconds
     *
     * @var int
     */
    protected $callDelay = 300000; // 300ms

    public function __construct(ThreeCxService $threeCxService)
    {
        parent::__construct();
        $this->threeCxService = $threeCxService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $lockKey = 'adial_make_call_running';

        if (Cache::has($lockKey)) {
            Log::info('ADialMakeCallCommand: 🔒 Another instance is already running');
            return;
        }

        Cache::put($lockKey, true, $this->lockTimeout);

        try {
            Log::info('✅ 📡 ADialMakeCallCommand started at ' . Carbon::now());

            $providers = ADialProvider::all();
            Log::info("ADialMakeCallCommand: 🔍📡 Found {$providers->count()} providers to process.");

            $timezone = config('app.timezone');
            $now = now()->timezone($timezone);

            foreach ($providers as $provider) {
                $this->processProvider($provider, $now, $timezone);
            }

            Log::info('ADialMakeCallCommand: 📞🏁 Execution completed successfully. ✅');
        } catch (\Exception $e) {
            Log::error('ADialMakeCallCommand: ❌ Error: ' . $e->getMessage());
            report($e);
        } finally {
            Cache::forget($lockKey);
        }
    }


    /**
     * Check if current time is within global call window
     *
     * @return bool
     */
    protected function isWithinGlobalCallWindow($file)
    {
        $timezone = config('app.timezone');
        $callTimeStart = General_Setting::get('call_time_start');
        $callTimeEnd = General_Setting::get('call_time_end');

        if (!$callTimeStart || !$callTimeEnd) {
            Log::warning("ADialMakeCallCommand: ⚠️ Call time settings not configured.");
            return false;
        }

        $now = now()->timezone($timezone);
        $globalStart = Carbon::parse(date('Y-m-d') . ' ' . $callTimeStart)->timezone($timezone);
        $globalEnd = Carbon::parse(date('Y-m-d') . ' ' . $callTimeEnd)->timezone($timezone);

        if (!$now->between($globalStart, $globalEnd)) {
            Log::info('ADialMakeCallCommand: 🕒🚫📞 Outside global call time.');
            $file->update(['is_done' => 'not_called']);
            Log::info("ADialMakeCallCommand: 📁 File '{$file->file_name}' marked as not_called.");
            return false;
        }

        return true;
    }


    /**
     * Process a provider and its associated feeds
     *
     * @param ADialProvider $provider
     * @param Carbon $now
     * @param string $timezone
     * @return void
     */
    protected function processProvider($provider, $now, $timezone)
    {
        $files = ADialFeed::where('provider_id', $provider->id)
            ->whereDate('date', today())
            ->where('allow', true)
            ->where('is_done', false)
            ->get();

        Log::info("ADialMakeCallCommand: 📑🔍 Found {$files->count()} feeds for provider {$provider->name}.");

        foreach ($files as $file) {
            $this->processFile($file, $provider, $now, $timezone);
        }
    }


    /**
     * Process a file and make calls for its data
     *
     * @param ADialFeed $file
     * @param ADialProvider $provider
     * @param Carbon $now
     * @param string $timezone
     * @return void
     */
    protected function processFile($file, $provider, $now, $timezone)
    {
        $from = Carbon::parse("{$file->date} {$file->from}", $timezone);
        $to = Carbon::parse("{$file->date} {$file->to}", $timezone);
        if ($to->lessThanOrEqualTo($from)) $to->addDay();

        if (!$now->between($from, $to) || !$this->isWithinGlobalCallWindow($file)) {
            $remainingCalls = ADialData::where('feed_id', $file->id)->where('state', 'new')->count();
            $status = $remainingCalls == 0 ? "called" : "not_called";
            $file->update(['is_done' => $status]);

            $logMessage = $status === "called"
            ? "✅ All numbers called for File '{$file->file_name}'."
            : "🚫 Time over or outside global call window for File '{$file->file_name}'. Not completed.";
            Log::info("ADialMakeCallCommand: {$logMessage}");

            return;
        }
        
        $callsInLastMinute = AutoDailerReport::where('created_at', '>=', now()->subMinute())->count();
        if ($callsInLastMinute >= $this->maxCallsPerMinute) {
            Log::info("ADialMakeCallCommand: ⏱️ Rate limit hit. Skipping.");
            return;
        }

        $callLimit = CountCalls::get('number_calls');
        try {
            $activeCalls = $this->threeCxService->getAllActiveCalls();
            $currentCalls = count($activeCalls['value'] ?? []);
            Log::info("ADialMakeCallCommand: 📞📊 Active calls: {$currentCalls}, Limit: {$callLimit}.");
        } catch (\Throwable $e) {
            Log::error("ADialMakeCallCommand: ❌ Error fetching active calls: " . $e->getMessage());
            return;
        }

        if ($currentCalls > 96) {
            Log::error("ADialMakeCallCommand: 🚫 Too many active calls.");
            return;
        }

        $remainingCapacity = $callLimit - $currentCalls;
        if ($remainingCapacity <= 0) {
            Log::info("ADialMakeCallCommand: 🚫 No capacity left.");
            return;
        }

        $recentlyCalledNumbers = AutoDailerReport::where('created_at', '>=', now()->subMinutes($this->duplicateCallWindow))
            ->pluck('phone_number')->toArray();

        $potentialCalls = ADialData::where('feed_id', $file->id)
            ->where('state', 'new')
            ->count();

        Log::info("ADialMakeCallCommand: 🔍 Found {$potentialCalls} potential calls for file {$file->id}");

        $feedData = ADialData::where('feed_id', $file->id)
            ->where('state', 'new')
            ->take($callLimit)
            ->get();

        $file->update(['is_done' => "calling"]);
        Log::info("ADialMakeCallCommand: 📞 File {$file->file_name} status updated to 'calling'.");

        foreach ($feedData as $data) {
            if (!$now->between($from, $to)) {
                $remainingCalls = ADialData::where('feed_id', $file->id)->where('state', 'new')->count();
                if ($remainingCalls != 0) {
                    $file->update(['is_done' => "not_called"]);
                    Log::info("ADialMakeCallCommand: 🕒 File '{$file->file_name}' marked as not_called during loop.");
                }
                break;
            }

            $this->makeCall($data, $provider);
        }

        $remainingCalls = ADialData::where('feed_id', $file->id)->where('state', 'new')->count();
        if ($remainingCalls == 0) {
            $file->update(['is_done' => "called"]);
            Log::info("ADialMakeCallCommand: ✅ All numbers called for File '{$file->file_name}'.");
        } else {
            Log::info("ADialMakeCallCommand: 📝 File {$file->file_name} has {$remainingCalls} calls remaining.");
        }
    }


    /**
     * Make a call to a number with proper error handling and state management
     *
     * @param ADialData $data
     * @param ADialProvider $provider
     * @return void
     */
    protected function makeCall($data, $provider)
    {
        // Additional check to prevent duplicate calls
        $recentCall = AutoDailerReport::where('phone_number', $data->mobile)
            ->where('created_at', '>=', now()->subMinutes($this->duplicateCallWindow))
            ->first();

        if ($recentCall) {
            Log::info("ADialMakeCallCommand: 🔄 Skipping duplicate call to {$data->mobile} - previous call ID: {$recentCall->call_id}");
            return;
        }

        //$this->threeCxService->makeCall($provider->extension, $data->mobile);
        try {
            DB::transaction(function () use ($data) {
                // Mark as processing before making the call
                $data->update([
                    'state' => 'processing',
                ]);
            });

            // Separate block for making the actual call
            try {
                $responseData = $this->threeCxService->makeCall(
                    $provider->extension,
                    $data->mobile
                );

                $callId = $responseData['result']['callid'];
                $status = $responseData['result']['status'];

                DB::transaction(function () use ($callId, $status, $data, $provider) {
                    $report = AutoDailerReport::create([
                        'call_id' => $callId,
                        'status' => $status,
                        'provider' => $provider->name,
                        'extension' => $provider->extension,
                        'phone_number' => $data->mobile
                    ]);

                    ToQueue::create([
                        'call_id' => $callId,
                        'status' => $status,
                        'a_dial_report_id' => $report->id
                    ]);

                    $data->update([
                        'state' => $status,
                        'call_date' => now(),
                        'call_id' => $callId
                    ]);
                });

                Log::info("ADialMakeCallCommand: 📞✅📞 Call successful for mobile: {$data->mobile}. Call ID: " . $callId);
                usleep($this->callDelay);
            } catch (\Exception $e) {
                DB::transaction(function () use ($data) {
                    $data->update([
                        'state' => 'failed',
                    ]);
                });

                Log::error("ADialMakeCallCommand: ❌ Call Failed to number {$data->mobile}: " . $e->getMessage());
            }
        } catch (\Exception $e) {
            Log::error("ADialMakeCallCommand: ❌ Database error for number {$data->mobile}: " . $e->getMessage());
        }
    }
}
