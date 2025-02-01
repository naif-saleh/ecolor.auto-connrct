<?php

namespace App\Console\Commands;

use App\Models\AutoDailerFile;
use App\Models\AutoDialerData;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Models\AutoDailerReport;
use Illuminate\Support\Facades\Http;
use App\Jobs\MakeCallJob;
use App\Models\AutoDialerProvider;
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
        Log::info('MakeCallCommand executed at ' . now());

        // Fetch all providers
        $providers = AutoDialerProvider::all();

        foreach ($providers as $provider) {
            // Fetch files uploaded today
            $files = AutoDailerFile::where('provider_id', $provider->id)
                ->whereDate('date', today())
                ->where('allow', true) // Only allowed files
                ->get();

            foreach ($files as $file) {
                $from = Carbon::parse("{$file->date} {$file->from}")->subHours(3);
                $to = Carbon::parse("{$file->date} {$file->to}")->subHours(3);

                if (now()->between($from, $to)) {
                    Log::info("✅ File ID {$file->id} is within range, processing calls...");

                    AutoDialerData::where('file_id', $file->id)
                        ->where('state', 'new')
                        ->chunk(50, function ($dataBatch) use ($provider) {
                            foreach ($dataBatch as $feedData) {
                                try {
                                    $token = app(TokenService::class);  
                                    dispatch(new MakeCallJob($feedData, $token, $provider->extension));
                                } catch (\Exception $e) {
                                    Log::error("❌ Error in dispatching call: " . $e->getMessage());
                                }
                            }
                        });

                    // Mark file as done if all calls are processed
                    if (!AutoDialerData::where('file_id', $file->id)->where('state', 'new')->exists()) {
                        $file->update(['is_done' => true]);
                        Log::info("✅✅✅ All numbers called for File ID: {$file->id}");
                    }
                } else {
                    Log::info("❌ File ID {$file->id} is NOT within range.");
                }
            }
        }

        Log::info('📞✅ MakeCallCommand execution completed at ' . now());
    }

}
