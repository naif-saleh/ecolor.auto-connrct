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
        Log::info('ADialMakeCallCommand executed at ' . now());

        // Fetch all providers
        $providers = ADialProvider::all();
      
        foreach ($providers as $provider) {
            // TODO: add new feature to turn of the provider
            // Fetch files uploaded today
            $files = ADialFeed::where('provider_id', $provider->id)
                ->whereDate('date', today())
                ->where('allow', true) // Only allowed files
                ->get();

            foreach ($files as $file) {
            
                $from = Carbon::parse("{$file->date} {$file->from}")->subHours(3);
                $to = Carbon::parse("{$file->date} {$file->to}")->subHours(3);

                if (now()->between($from, $to)) {
                
                    Log::info("✅ File ID {$file->id} is within range, processing calls...");

                    ADialData::where('feed_id', $file->id)
                        ->where('state', 'new')
                        ->chunk(50, function ($dataBatch) use ($provider) {
                            Log::info("dadad before call ");
                            foreach ($dataBatch as $feedData) {
                                try {
                                    $token = app(TokenService::class);
                                    Log::info("dadad before call id " . $feedData->mobile);
                                    $job = dispatch(new MakeCallJob($feedData, $token, $provider->extension));
                                    Log::info("dadad job field call id " . print_r($job, TRUE));

                                    if (!$job) {
                                        Log::info("dadad job field call id " . $feedData->mobile);
                                    }
                                } catch (\Exception $e) {
                                    Log::error("❌ Error in dispatching call: " . $e->getMessage());
                                }
                            }
                        });

                    // Mark file as done if all calls are processed
                    if (!ADialData::where('feed_id', $file->id)->where('state', 'new')->exists()) {
                        $file->update(['is_done' => true]);
                        Log::info("✅✅✅ All numbers called for File ID: {$file->id}");
                    }
                } else {
                    Log::info("❌ File ID {$file->id} is NOT within range.");
                }
            }
        }

        Log::info('📞✅ ADialMakeCallCommand execution completed at ' . now());
    }

}
