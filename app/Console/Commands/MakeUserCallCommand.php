<?php

namespace App\Console\Commands;

use App\Models\AutoDistributorUploadedData;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Models\AutoDistributerReport;
use Illuminate\Support\Facades\Http;
use App\Models\AutoDistributorFile;
use App\Services\TokenService;

class MakeUserCallCommand extends Command
{
    protected $signature = 'app:make-user-call-command';
    protected $description = 'Handles automated user calls';
    protected $tokenService;

    public function __construct(TokenService $tokenService)
    {
        parent::__construct();
        $this->tokenService = $tokenService;
    }

    public function handle()
    {
        Log::info("\n\t---------------- Auto Distributor ----------------\n\t✅ MakeCallCommand executed at " . now() . "\n\t------------------------------------------------");

        $autoDailerFiles = AutoDistributorFile::where('allow', 1)->get();

        foreach ($autoDailerFiles as $feed) {
            $from = Carbon::parse("{$feed->date} {$feed->from}")->subHours(3);
            $to = Carbon::parse("{$feed->date} {$feed->to}")->subHours(3);

            if (!now()->between($from, $to)) {
                Log::info("⏰❌ TIME OUT: File ID {$feed->id} is NOT within range");
                continue;
            }

            Log::info("⏰✅ TIME IN: Processing File ID {$feed->id}");

            $data = AutoDistributorUploadedData::where('file_id', $feed->id)->where('state', 'new')->paginate(50);
            foreach ($data as $feedData) {
                try {
                    if ($feedData->userStatus !== "Available") {
                        Log::error("📵 Employee not available. Skipping call for mobile {$feedData->mobile}");
                        continue;
                    }

                    $token = $this->tokenService->getToken();
                    $ext = $feedData->extension;

                    $activeCalls = Http::withHeaders(['Authorization' => "Bearer $token"])
                        ->get(config('services.three_cx.api_url') . "/xapi/v1/ActiveCalls?\$filter=" . urlencode("contains(Caller, '$ext')"))
                        ->json();

                    if (!empty($activeCalls['value'])) {
                        Log::info("🚫 Busy: Active call detected for extension {$ext}. Skipping {$feedData->mobile}");
                        continue;
                    }

                    $devices = Http::withHeaders(['Authorization' => "Bearer $token"])
                        ->get(config('services.three_cx.api_url') . "/callcontrol/{$ext}/devices")
                        ->json();

                    foreach ($devices as $device) {
                        if ($device['user_agent'] === '3CX Mobile Client') {
                            $responseState = Http::withHeaders(['Authorization' => "Bearer $token"])
                                ->post(config('services.three_cx.api_url') . "/callcontrol/{$ext}/devices/{$device['device_id']}/makecall", [
                                    'destination' => $feedData->mobile,
                                ]);

                            if ($responseState->successful()) {
                                $responseData = $responseState->json();
                                Log::info("📞✅ Successfully called {$feedData->mobile}", $responseData);

                                AutoDistributerReport::updateOrCreate(
                                    ['call_id' => $responseData['result']['callid']],
                                    [
                                        'status' => "Initiating",
                                        'provider' => $feedData->user,
                                        'extension' => $responseData['result']['dn'],
                                        'phone_number' => $responseData['result']['party_caller_id'],
                                    ]
                                );

                                $feedData->update([
                                    'state' => "Initiating",
                                    'call_date' => now(),
                                    'call_id' => $responseData['result']['callid'],
                                    'party_dn_type' => $responseData['result']['party_dn_type'] ?? null,
                                ]);
                            } else {
                                Log::error("❌ Failed to make call for {$feedData->mobile}", [$responseState->body()]);
                            }
                            break;
                        }
                    }
                } catch (\Exception $e) {
                    Log::error("❌ Error in Auto Dialer: " . $e->getMessage());
                }
            }

            if (!AutoDistributorUploadedData::where('file_id', $feed->id)->where('state', 'new')->exists()) {
                $feed->update(['is_done' => true]);
                Log::info("✅✅✅ All Numbers Called for File: {$feed->slug}");
            }
        }
    }
}
