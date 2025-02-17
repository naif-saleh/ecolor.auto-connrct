<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Models\ADialProvider;
use App\Models\ADialData;
use App\Models\ADialFeed;

use App\Jobs\MakeCallJob;
use App\Services\TokenService;

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

    protected $tokenService;

    public function __construct(TokenService $tokenService)
    {
        parent::__construct();
        $this->tokenService = $tokenService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        Log::info('ADialMakeCallCommand executed at ' . Carbon::now());

        // Get timezone from configuration
        $timezone = config('app.timezone');
        Log::info("Using timezone: {$timezone}");

        $providers = ADialProvider::all();

        foreach ($providers as $provider) {
            $files = ADialFeed::where('provider_id', $provider->id)
                ->whereDate('date', today())
                ->where('allow', true)
                ->get();

            foreach ($files as $file) {
                // Parse times using configured timezone
                $from = Carbon::parse("{$file->date} {$file->from}")->timezone($timezone);
                $to = Carbon::parse("{$file->date} {$file->to}")->timezone($timezone);
                $now = now()->timezone($timezone);

                Log::info("ADIAL Processing window for File ID {$file->id}:");
                Log::info("Current time ({$timezone}): " . $now);
                Log::info("Call window: {$from} to {$to}");

                if ($now->between($from, $to)) {
                    Log::info("ADIAL ✅ File ID {$file->id} is within range, processing calls...");

                    ADialData::where('feed_id', $file->id)
                        ->where('state', 'new')
                        ->chunk(50, function ($dataBatch) use ($provider) {
                            foreach ($dataBatch as $feedData) {
                                try {
                                    $token = app(TokenService::class);
                                    dispatch(new MakeCallJob($feedData, $token, $provider->extension));

                                    // Add a small delay between calls to prevent system overload
                                    usleep(200000); // 0.2 seconds delay
                                } catch (\Exception $e) {
                                    Log::error("ADIAL ❌ Error in dispatching call: " . $e->getMessage());
                                }
                            }
                        });

                    if (!ADialData::where('feed_id', $file->id)->where('state', 'new')->exists()) {
                        $file->update(['is_done' => true]);
                        Log::info("ADIAL ✅✅✅ All numbers called for File ID: {$file->id}");
                    }
                } else {
                    Log::info("ADIAL ❌ File ID {$file->id} is NOT within range.");
                    Log::info("Current time: " . $now->format('Y-m-d H:i:s'));
                    Log::info("Window: {$from->format('Y-m-d H:i:s')} - {$to->format('Y-m-d H:i:s')}");
                }
            }
        }

        Log::info('📞✅ ADialMakeCallCommand execution completed at ' . now());
    }

}
