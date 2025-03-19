<?php

namespace App\Console\Commands;

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
    protected $duplicateCallWindow = 30;

    /**
     * Maximum calls per minute
     *
     * @var int
     */
    protected $maxCallsPerMinute = 10;

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

        // Prevent concurrent execution
        if (Cache::has($lockKey)) {
            Log::info('ADialMakeCallCommand: 🔒 Another instance is already running');
            return;
        }

        // Acquire lock
        Cache::put($lockKey, true, $this->lockTimeout);

        try {
            Log::info('✅ 📡 ADialMakeCallCommand started at ' . Carbon::now());

            if (!$this->isWithinGlobalCallWindow()) {
                return;
            }

            // Process each provider
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
            // Always release the lock
            Cache::forget($lockKey);
        }
    }

    /**
     * Check if current time is within global call window
     *
     * @return bool
     */
    protected function isWithinGlobalCallWindow()
    {
        $timezone = config('app.timezone');
        $callTimeStart = General_Setting::get('call_time_start');
        $callTimeEnd = General_Setting::get('call_time_end');

        if (!$callTimeStart || !$callTimeEnd) {
            Log::warning("ADialMakeCallCommand: ⚠️ Call time settings not configured. ⚠️");
            return false;
        }

        $now = now()->timezone($timezone);
        $globalStart = Carbon::parse(date('Y-m-d') . ' ' . $callTimeStart)->timezone($timezone);
        $globalEnd = Carbon::parse(date('Y-m-d') . ' ' . $callTimeEnd)->timezone($timezone);

        if (!$now->between($globalStart, $globalEnd)) {
            Log::info('ADialMakeCallCommand: 🕒🚫📞 Calls are not allowed at this time. 🕒🚫📞');
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
            ->where('is_done', false) // Only process files that aren't done
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
        // Convert times to Carbon instances with correct timezone
        $from = Carbon::parse("{$file->date} {$file->from}", $timezone);
        $to = Carbon::parse("{$file->date} {$file->to}", $timezone);

        // If "to" time is before "from" time, assume it's on the next day
        if ($to->lessThanOrEqualTo($from)) {
            $to->addDay();
        }

        // Log the calculated time window
        Log::info("ADialMakeCallCommand: Processing File ID {$file->id} | From: {$from->toDateTimeString()} | To: {$to->toDateTimeString()} | Now: {$now->toDateTimeString()}");

        // Check if the current time is within the call window
        if (!$now->between($from, $to)) {
            Log::info("ADialMakeCallCommand: 🛑📑 Skipping File ID {$file->id}, not in call window.");
            return;
        }

        // Rate limiting check
        $callsInLastMinute = AutoDailerReport::where('created_at', '>=', now()->subMinute())
            ->count();

        if ($callsInLastMinute >= $this->maxCallsPerMinute) {
            Log::info("ADialMakeCallCommand: ⏱️ Rate limit reached ({$callsInLastMinute} calls in last minute). Pausing.");
            return;
        }

        // Check current call limits
        $callLimit = CountCalls::get('number_calls');

        try {
            $activeCalls = $this->threeCxService->getAllActiveCalls();
            $currentCalls = count($activeCalls['value'] ?? []);

            Log::info("ADialMakeCallCommand: 📞📊 Current active calls: {$currentCalls}, Limit: {$callLimit}.");
        } catch (\Throwable $e) {
            Log::error("ADialMakeCallCommand: ❌ Error fetching active calls: " . $e->getMessage());
            return;
        }

        if ($currentCalls > 96) {
            Log::error("ADialMakeCallCommand: 🚫📞 Active calls reached limit: " . $currentCalls);
            return;
        }

        // Calculate remaining capacity
        $remainingCapacity = $callLimit - $currentCalls;
        if ($remainingCapacity <= 0) {
            Log::info("ADialMakeCallCommand: 🚫📞 No capacity for new calls.");
            return;
        }

        // Get phone numbers that haven't been called recently
        $recentlyCalledNumbers = AutoDailerReport::where('created_at', '>=', now()->subMinutes($this->duplicateCallWindow))
            ->pluck('phone_number')
            ->toArray();

        // Fetch new calls within limit
        $feedData = ADialData::where('feed_id', $file->id)
            ->where('state', 'new')
            ->whereNotIn('mobile', $recentlyCalledNumbers)
            ->take($remainingCapacity)
            ->lockForUpdate()
            ->get();

        foreach ($feedData as $data) {
            if (!$now->between($from, $to)) {
                Log::info("ADialMakeCallCommand: 🕒🚫📞 Call window expired during execution.");
                break;
            }

            $this->makeCall($data, $provider);
        }

        // Mark file as done if all calls processed
        $remainingCalls = ADialData::where('feed_id', $file->id)
            ->where('state', 'new')
            ->count();

        if ($remainingCalls == 0) {
            $file->update(['is_done' => true]);
            Log::info("ADialMakeCallCommand: ✅📑📞 All numbers called for File Name: {$file->file_name}");
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

        try {
            DB::beginTransaction();

            // Mark as processing before making the call
            $data->update([
                'state' => 'processing',
                'processing_started_at' => now()
            ]);

            DB::commit();

            // Separate transaction for the actual call
            try {
                // Make the call
                $responseData = $this->threeCxService->makeCall(
                    $provider->extension,
                    $data->mobile
                );

                $callId = $responseData['result']['callid'];
                $status = $responseData['result']['status'];

                DB::beginTransaction();

                // Create call report
                AutoDailerReport::create([
                    'call_id' => $callId,
                    'status' => $status,
                    'provider' => $provider->name,
                    'extension' => $provider->extension,
                    'phone_number' => $data->mobile
                ]);

                // Update dial data
                $data->update([
                    'state' => $status,
                    'call_date' => now(),
                    'call_id' => $callId
                ]);

                DB::commit();

                Log::info("ADialMakeCallCommand: 📞✅📞 Call successful for mobile: {$data->mobile}. Call ID: " . $callId);

                // Add delay between calls
                usleep($this->callDelay);
            } catch (\Exception $e) {
                DB::beginTransaction();

                // Mark as failed
                $data->update([
                    'state' => 'failed',
                    'last_error' => $e->getMessage()
                ]);

                DB::commit();

                Log::error("ADialMakeCallCommand: ❌ Call Failed to number {$data->mobile}: " . $e->getMessage());
            }
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("ADialMakeCallCommand: ❌ Database error for number {$data->mobile}: " . $e->getMessage());
        }
    }
}
