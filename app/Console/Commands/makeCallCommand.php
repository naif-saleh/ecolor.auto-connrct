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

                 // Dispatch the job
                 MakeCallJob::dispatch($feed, $token);
             } else {
                 Log::info('Time not within range for file ID ' . $feed->file->id);
             }
         }
     }
}
