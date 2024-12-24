<?php

namespace App\Http\Controllers;

use App\Models\Provider;
use App\Models\AutoDirtibuterData;
use App\Models\AutoDailerData;
use App\Models\User;
use App\Models\AutoDailerReport;
use App\Models\AutoDistributerReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Setting;

use function Pest\Laravel\json;

class ApiController extends Controller
{
    // Get all providers........................................................................................................................
    public function getProviders()
    {
        if (!Auth::check()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $providers = Provider::select('name', 'extension')->get();
        return response()->json($providers);
    }

    // Get Provider By Name..........................................................................................................................
    public function getProviderByName($name)
    {
        if (!Auth::check()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $provider = Provider::where('name', $name)
            ->select('name', 'extension')
            ->first();

        if (!$provider) {
            return response()->json(['message' => 'Provider not found.'], 404);
        }

        return response()->json($provider);
    }


    // Get all Auto Distributer..........................................................................................................................
    public function autoDistributer()
    {
        if (!Auth::check()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $settings = Setting::first();
        $currentHour = now()->setTimezone('Asia/Riyadh')->hour;

        if ($settings->cfd_allow_friday == 1 || $settings->cfd_allow_saturday == 1) {
            return redirect('/settings')->with('wrong', 'Today is Weekend, Auto Dialer is disabled. you can skip weekend by updating settings');
        }
        if (
            $settings->allow_calling != 1 ||
            $currentHour < $settings->cfd_start_time ||
            $currentHour >= $settings->cfd_end_time
        ) {
            return redirect('/settings')->with('wrong', 'Calls are disabled as per settings');
        }

        $autoDistributer = AutoDirtibuterData::where('state', 'new')
            ->select('mobile', DB::raw('MAX(id) as id'), 'provider_name', 'extension')
            ->groupBy('mobile', 'provider_name', 'extension')
            ->get();

        $count = AutoDirtibuterData::getQuery()->count();

        if ($count == 0) {
            return redirect('/autodistributers')->with('wrong', 'No Auto Distributer Numbers Found. Please Insert and Call Again');
        }

        // Fetch and cache token
        $response = Http::asForm()->post(config('services.three_cx.api_url') . '/connect/token', [
            'grant_type' => 'client_credentials',
            'client_id' => 'testapi',
            'client_secret' => '95ULDtdTRRJhJBZCp94K6Gd1BKRuaP1k',
        ]);

        if ($response->failed()) {
            // Log or handle authentication failure
            return response()->json([
                'status' => 'error',
                'message' => 'Authentication failed',
                'details' => $response->body(),
            ], $response->status());
        }

        $token = $response->json()['access_token'] ?? null;

        if (!$token) {
            return response()->json([
                'status' => 'error',
                'message' => 'Token not found in the authentication response.',
            ], 400);
        }

        // Loop through records and make calls
        foreach ($autoDistributer as $record) {
            $from = $record->extension;
            $to = $record->mobile;

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token,
            ])->post(config('services.three_cx.api_url') . "/callcontrol/{$from}/makecall", [
                'destination' => $to,
            ]);

            $autoDistributer = AutoDirtibuterData::find($record->id);

            if ($response->successful()) {
                $autoDistributer->state = "called";
                $autoDistributer->save();

                AutoDistributerReport::create([
                    'mobile' => $autoDistributer->mobile,
                    'provider' => $autoDistributer->provider_name,
                    'extension' => $autoDistributer->extension,
                    'state' => $autoDistributer->state,
                    'called_at' => now()->addHours(2),
                ]);
            } else {
                Log::error('3CX Call Failed', [
                    'mobile' => $to,
                    'response' => $response->body(),
                ]);
            }

            // Random delay between 30 and 60 seconds
            $delay = rand(30, 60); // Random delay between 30 and 60 seconds
            sleep($delay); // Delay the next call
        }

        if ($response->failed()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to make the call.',
                'details' => $response->body(),
            ], $response->status());
        }

        return redirect('/auto-distributer-report')->with('success', 'Auto Distributer is Calling Now...');
    }



    // Call Auto Distributr By Clicking.............................................................................................................
    public function autoDistributerByClicking(Request $request)
    {
        if (!Auth::check()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $settings = Setting::first();
        $currentHour = now()->setTimezone('Asia/Riyadh')->hour;

        if ($settings->cfd_allow_friday == 1 || $settings->cfd_allow_saturday == 1) {
            return redirect('/settings')->with('wrong', 'Today is Weekend, Auto Dialer is disabled. you can skip weekend by updating settings');
        }
        if (
            $settings->allow_calling != 1 ||
            $currentHour < $settings->cfd_start_time ||
            $currentHour >= $settings->cfd_end_time
        ) {
            return redirect('/settings')->with('wrong', 'Calls are disabled as per settings');
        }

        $autoDistributer = AutoDirtibuterData::where('state', 'new')
            ->select('mobile', DB::raw('MAX(id) as id'), 'provider_name', 'extension')
            ->groupBy('mobile', 'provider_name', 'extension')
            ->get();

        $count = AutoDirtibuterData::getQuery()->count();

        if ($count == 0) {
            return redirect('/autodistributers')->with('wrong', 'No Auto Distributer Numbers Found. Please Insert and Call Again');
        }

        if ($autoDistributer->every(fn($item) => $item->state === 'called')) {
            return redirect('/autodistributers')->with('success', 'All Auto Distributers are Called');
        }

        $response = Http::asForm()->post(config('services.three_cx.api_url') . '/connect/token', [
            'grant_type' => 'client_credentials',
            'client_id' => 'testapi',
            'client_secret' => '95ULDtdTRRJhJBZCp94K6Gd1BKRuaP1k',
        ]);

        if ($response->failed()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Authentication failed',
                'details' => $response->body(),
            ], $response->status());
        }

        $token = $response->json()['access_token'] ?? null;

        if (!$token) {
            return response()->json([
                'status' => 'error',
                'message' => 'Token not found in the authentication response.',
            ], 400);
        }

        foreach ($autoDistributer as $record) {
            $from = $record->extension;
            $to = $record->mobile;

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token,
            ])->post(config('services.three_cx.api_url') . "/callcontrol/{$from}/makecall", [
                'destination' => $to,
            ]);

            $autoDistributer = AutoDirtibuterData::find($record->id);

            if ($response->successful()) {
                $autoDistributer->state = "called";
                $autoDistributer->save();

                AutoDistributerReport::create([
                    'mobile' => $autoDistributer->mobile,
                    'provider' => $autoDistributer->provider_name,
                    'extension' => $autoDistributer->extension,
                    'state' => $autoDistributer->state,
                    'called_at' => now()->addHours(2),
                ]);
            } else {
                Log::error('3CX Call Failed', [
                    'mobile' => $to,
                    'response' => $response->body(),
                ]);
            }
            // Random delay between 30 and 60 seconds
            $delay = rand(30, 60); // Random delay between 30 and 60 seconds
            sleep($delay); // Delay the next call
        }
        if ($response->failed()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to make the call.',
                'details' => $response->body(),
            ], $response->status());
        }

        return redirect('/auto-distributer-report')->with('success', 'Auto Distributers are Calling Now...');
    }


    // Get all Auto Dailer..........................................................................................................................
    public function autoDailer(Request $request)
{
    if (!Auth::check()) {
        return response()->json(['error' => 'Unauthorized'], 401);
    }

    $settings = Setting::first();
    $currentHour = now()->setTimezone('Asia/Riyadh')->hour;

    if ($settings->cfd_allow_friday == 1 || $settings->cfd_allow_saturday == 1) {
        return redirect('/settings')->with('wrong', 'Today is Weekend, Auto Dialer is disabled. you can skip weekend by updating settings');
    }

    if (
        $settings->allow_auto_calling != 1 ||
        $currentHour < $settings->cfd_start_time ||
        $currentHour >= $settings->cfd_end_time
    ) {
        return redirect('/settings')->with('wrong', 'Calls are disabled as per settings');
    }

    $autoDailer = AutoDailerData::where('state', 'new')
        ->select('mobile', DB::raw('MAX(id) as id'), 'provider_name', 'extension')
        ->groupBy('mobile', 'provider_name', 'extension')
        ->get();

    $count = AutoDailerData::getQuery()->count();

    if ($count == 0) {
        return redirect('/autodailers')->with('wrong', 'No Auto Dailer Numbers Found. Please Insert and Call Again');
    }

    $response = Http::asForm()->post(config('services.three_cx.api_url') . '/connect/token', [
        'grant_type' => 'client_credentials',
        'client_id' => 'testapi',
        'client_secret' => '95ULDtdTRRJhJBZCp94K6Gd1BKRuaP1k',
    ]);

    if ($response->failed()) {
        return response()->json([
            'status' => 'error',
            'message' => 'Authentication failed',
            'details' => $response->body(),
        ], $response->status());
    }

    $token = $response->json()['access_token'] ?? null;

    if (!$token) {
        return response()->json([
            'status' => 'error',
            'message' => 'Token not found in the authentication response.',
        ], 400);
    }

    foreach ($autoDailer as $record) {
        $from = $record->extension;
        $to = $record->mobile;

        // Step 1: Make the call
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->post(config('services.three_cx.api_url') . "/callcontrol/{$from}/makecall", [
            'destination' => $to,
        ]);

        $autoDailerData = AutoDailerData::find($record->id);

        if ($response->failed()) {
            // Log failed call
            Log::error('Failed to initiate call', [
                'mobile' => $to,
                'response' => $response->body(),
            ]);
            continue; // Skip to the next record
        }

        // Step 2: Poll the participants endpoint for status
        $participantData = null;
        for ($attempts = 0; $attempts < 5; $attempts++) { // Retry up to 5 times
            sleep(2); // Delay between attempts (adjust as necessary)

            $responseState = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token,
            ])->get(config('services.three_cx.api_url') . "/callcontrol/{$from}/participants");

            if ($responseState->successful()) {
                $responseData = $responseState->json();
                dd($response->body());
                // Assuming `participants` is an array in the response
                $participantData = $responseData['status'] ?? null;
                dd($participantData);
                if ($participantData) {
                    break; // Exit loop if data is found
                }
            }
        }

        if (!$participantData) {
            // Log or handle cases where participant data is unavailable
            Log::warning('No participant data found after polling', [
                'mobile' => $to,
            ]);
            continue;
        }

        // Step 3: Process participant data
        if ($participantData === "Wextension") {
            $autoDailerData->state = "answered";
        } elseif ($participantData === "Wspecialmenu") {
            $autoDailerData->state = "declined";
        } elseif ($participantData === "Wroutepoint") {
            $autoDailerData->state = "no answer";
        } else {
            $autoDailerData->state = "unknown";
        }

        $autoDailerData->save();

        // Log or save report
        AutoDailerReport::create([
            'mobile' => $autoDailerData->mobile,
            'provider' => $autoDailerData->provider_name,
            'extension' => $autoDailerData->extension,
            'state' => $autoDailerData->state,
            'called_at' => now()->addHours(2),
        ]);
    }


    return redirect('/auto-dailer-report')->with('success', 'Auto Dialer is Calling Now...');
}


    // Call Auto Dailers By Clicking...................................................................................................................


    public function autoDailerByClick(Request $request)
    {
        if (!Auth::check()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $settings = Setting::first();
        $currentHour = now()->setTimezone('Asia/Riyadh')->hour;

        if ($settings->cfd_allow_friday == 1 || $settings->cfd_allow_saturday == 1) {
            // return response()->json(["data" => "Today is Weekend, Auto Dialer is disabled."], 200);
            return redirect('/settings')->with('wrong', 'Today is Weekend, Auto Dialer is disabled. you can skip weekend by updating settings');
        }

        if (
            $settings->allow_auto_calling != 1 ||
            $currentHour < $settings->cfd_start_time ||
            $currentHour >= $settings->cfd_end_time
        ) {
            // return response()->json(["data" => "Calls are disabled as per settings"], 200);
            return redirect('/settings')->with('wrong', 'Calls are disabled as per settings');
        }

        $autoDailer = AutoDailerData::where('state', 'new')
            ->select('mobile', DB::raw('MAX(id) as id'), 'provider_name', 'extension')
            ->groupBy('mobile', 'provider_name', 'extension')
            ->get();


        $count = AutoDailerData::getQuery()->count();

        if ($count == 0) {
            return redirect('/autodailers')->with('wrong', 'No Auto Dailer Numbers Found. Please Insert and Call Again');
        }


        if ($autoDailer->every(fn($item) => $item->state === 'called')) {
            return redirect('/autodailers')->with('success', 'All Auto Dailers are Called');
        }


        $response = Http::asForm()->post(config('services.three_cx.api_url') . '/connect/token', [
            'grant_type' => 'client_credentials',
            'client_id' => 'testapi',
            'client_secret' => '95ULDtdTRRJhJBZCp94K6Gd1BKRuaP1k',
        ]);

        if ($response->failed()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Authentication failed',
                'details' => $response->body(),
            ], $response->status());
        }

        $token = $response->json()['access_token'] ?? null;

        if (!$token) {
            return response()->json([
                'status' => 'error',
                'message' => 'Token not found in the authentication response.',
            ], 400);
        }

        foreach ($autoDailer as $record) {
            $from = $record->extension;
            $to = $record->mobile;

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token,
            ])->post(config('services.three_cx.api_url') . "/callcontrol/{$from}/makecall", [
                'destination' => $to,
            ]);

            $autoDailerData = AutoDailerData::find($record->id);

            if ($response->successful()) {
                $autoDailerData->state = "called";
                $autoDailerData->save();

                AutoDailerReport::create([
                    'mobile' => $autoDailerData->mobile,
                    'provider' => $autoDailerData->provider_name,
                    'extension' => $autoDailerData->extension,
                    'state' => $autoDailerData->state,
                    'called_at' => now()->addHours(2),
                ]);
            } else {
                Log::error('3CX Call Failed', [
                    'mobile' => $to,
                    'response' => $response->body(),
                ]);
            }

            // Random delay between 30 and 60 seconds
            $delay = rand(30, 60); // Random delay between 30 and 60 seconds
            sleep($delay); // Delay the next call
        }

        if ($response->failed()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to make the call.',
                'details' => $response->body(),
            ], $response->status());
        }

        return redirect('/auto-dailer-report')->with('success', 'Auto Dialer is Calling Now...');
    }



    public function autoDailerShowState($id)
    {
        $providers = AutoDailerData::where('id', $id)->first();
        return response()->json($providers);
    }



    // Update the state of an AutoDailer.............................................................................................................
    public function updateState(Request $request)
    {
        $request->validate([
            'id' => 'required|integer',
            'state' => 'required',
        ]);

        $id = $request->input('id');

        $autoDailerData = AutoDailerData::find($id);

        if (!$autoDailerData) {
            return response()->json([
                'message' => 'AutoDailerData not found.'
            ], Response::HTTP_NOT_FOUND);
        }

        if ($request['state'] == True) {
            $autoDailerData->state = "called";
            $autoDailerData->save();
        }


        $report = AutoDailerReport::create(

            [
                'mobile' => $autoDailerData->mobile,
                'provider' => $autoDailerData->provider_name,
                'extension' => $autoDailerData->extension,
                'state' => $autoDailerData->state,
                'called_at' => now(),
                'declined_at' => now()
            ]
        );

        return response()->json([
            'message' => 'State and report updated successfully.',
            'data' => $report
        ], Response::HTTP_OK);
    }





    // Update the state of an AutoDistributer.............................................................................................................
    public function autoDistributerUpdateState(Request $request)
    {

        $request->validate([
            'id' => 'required|integer',
            'state' => 'required',
        ]);

        $id = $request->input('id');

        $autoDailerData = AutoDirtibuterData::find($id);

        if (!$autoDailerData) {
            return response()->json([
                'message' => 'AutoDistributerReport not found.'
            ], Response::HTTP_NOT_FOUND);
        }

        $autoDailerData->state = $request->input('state') ? 'answered' : 'no answer';
        $autoDailerData->save();

        $report = AutoDistributerReport::create(

            [
                'mobile' => $autoDailerData->mobile,
                'provider' => $autoDailerData->provider_name,
                'extension' => $autoDailerData->extension,
                'state' => $autoDailerData->state,
                'called_at' => now(),
            ]
        );

        return response()->json([
            'message' => 'State and report updated successfully.',
            'data' => $report
        ], Response::HTTP_OK);
    }


    // Get All Users.............................................................................................................
    public function getUsers(Request $request)
    {

        if (!Auth::check()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $user = User::select('name', 'email', 'role')->get();
        return response()->json($user);
    }
}
