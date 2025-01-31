<?php

namespace App\Console\Commands;

use App\Models\AutoDailerFile;
use App\Models\AutoDailerUploadedData;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Models\AutoDailerReport;
use Illuminate\Support\Facades\Http;
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
        Log::info("
                    \t-----------------------------------------------------------------------
                    \t\t\t********** Auto Dialer **********\n
                    \t-----------------------------------------------------------------------
                    \t| 📞 ✅ MakeCallCommand executed at " . now() . "               |
                    \t-----------------------------------------------------------------------
                ");

        //$token = $this->tokenService;
        AutoDailerFile::chunk(100, function ($autoDailerFiles) {
            foreach ($autoDailerFiles as $feed) {
                $from = Carbon::parse("{$feed->date} {$feed->from}")->subHours(3);
                $to = Carbon::parse("{$feed->date} {$feed->to}")->subHours(3);

                if (now()->between($from, $to) && $feed->allow == 1) {
                    Log::info("
                            \t        -----------------------------------------------------------------------
                            \t\t\t\t********** Auto Dialer Time **********\n
                            \t\t\t⏰✅ TIME IN: File ID " . $feed->id . " is within range ✅ ⏰
                            \t        -----------------------------------------------------------------------
                        ");

                    AutoDailerUploadedData::where('file_id', $feed->id)
                        ->where('state', 'new')
                        ->chunk(50, function ($dataBatch) use ($feed) {
                            foreach ($dataBatch as $feedData) {
                                try {
                                    $token = $this->tokenService;
                                    dispatch(new MakeCallJob($feedData, $token));

                                    Log::info("
                                        \t📞 *_*_*_*_*_*_*_*_*_*_*_*_*_*_*_*_*_*_*_*_*_*_*_*_*_*_*_*_*_*_*_*_*_*_ 📞
                                        \t|        ✅ Auto Dialer Dispatched Call for Mobile: " . $feedData->mobile . " |
                                        \t📞 *_*_*_*_*_*_*_*_*_*_*_*_*_*_*_*_*_*_*_*_*_*_*_*_*_*_*_*_*_*_*_*_*_*_ 📞
                                    ");
                                } catch (\Exception $e) {
                                    Log::error("
                                        \t-----------------------------------------------------------------------
                                        \t\t\t\t********** Auto Dialer Error **********
                                        \t-----------------------------------------------------------------------
                                        \t| ❌ Error occurred in Auto Diale:" . $e->getMessage() . " |
                                        \t-----------------------------------------------------------------------
                                    ");
                                }
                            }
                        });
                } else {
                    Log::info("
                            \t        -----------------------------------------------------------------------
                            \t\t\t\t********** Auto Dialer Time **********\n
                            \t\t\t    ⏰❌ TIME OUT: File ID " . $feed->id . " is NOT within range ❌⏰
                            \t        -----------------------------------------------------------------------
                        ");
                }

                $count = !AutoDailerUploadedData::where('file_id', $feed->id)->where('state', 'new')->exists();
                if ($count) {
                    $feed->update(['is_done' => 1]);
                    Log::info("✅✅✅ All Numbers Called is_done: {$feed->is_done}");
                }
            }
        });

        Log::info("
                    \t-----------------------------------------------------------------------
                    \t\t\t********** Auto Dialer Execution Completed **********\n
                    \t-----------------------------------------------------------------------
                    \t| 📞 ✅ MakeCallCommand finished at " . now() . "               |
                    \t-----------------------------------------------------------------------
                ");
    }
}
