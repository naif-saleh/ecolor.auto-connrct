<?php

namespace App\Http\Controllers;

use App\Models\AutoDailerReport;
use App\Models\AutoDistributerReport;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;



class ApiController extends Controller
{

    /**
     * Update the satisfaction status of a call.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $call_id
     * @return \Illuminate\Http\Response
     */

    public function autoDailerUpdateSatisfaction(Request $request)
    {
        // Validate the input to make sure it's a boolean value and 'call_id' is provided
        $validated = $request->validate([
            'mobile' => 'required',
            'SERVICES_PROVIDED' => 'required',
        ]);

        // Extract the call_id from the validated data
        $mobile = $validated['mobile'];

        // Find the report by call_id
        $report = AutoDailerReport::where('call_id', $mobile)->first();

        // Check if the report exists
        if (!$report) {
            return response()->json(['message' => 'Report not found'], Response::HTTP_NOT_FOUND);
        }

        // Update the is_satisfied field
        $report->is_satisfied = $validated['SERVICES_PROVIDED'];
        $report->save();

        Log::info('Satisfaction status updated successfully to ' . $report->is_satisfied);
        return response()->json(['message' => 'ADailer:Satisfaction status updated successfully', 'is_satisfied' => $report->is_satisfied], Response::HTTP_OK);
    }



    public function autoDistributorUpdateSatisfaction(Request $request)
    {
        // Validate the input to make sure it's a boolean value and 'call_id' is provided
        $validated = $request->validate([
            'mobile' => 'required',
            'SERVICES_PROVIDED' => 'required',
        ]);

        // Extract the call_id from the validated data
        $mobile = $validated['mobile'];

        // Find the report by call_id
        $report = AutoDistributerReport::where('call_id', $mobile)->first();

        // Check if the report exists
        if (!$report) {
            return response()->json(['message' => 'Report not found'], Response::HTTP_NOT_FOUND);
        }

        // Update the is_satisfied field
        $report->is_satisfied = $validated['SERVICES_PROVIDED'];
        $report->save();

        Log::info('Satisfaction status updated successfully to ' . $report->is_satisfied);
        return response()->json(['message' => 'Adist:Satisfaction status updated successfully', 'is_satisfied' => $report->is_satisfied], Response::HTTP_OK);
    }





















    // Get all providers........................................................................................................................
    // public function getProviders()
    // {
    //     if (!Auth::check()) {
    //         return response()->json(['error' => 'Unauthorized'], 401);
    //     }

    //     $providers = Provider::select('name', 'extension')->get();
    //     return response()->json($providers);
    // }




    // Get Provider By Name..........................................................................................................................
    // public function getProviderByName($name)
    // {
    //     if (!Auth::check()) {
    //         return response()->json(['error' => 'Unauthorized'], 401);
    //     }

    //     $provider = Provider::where('name', $name)
    //         ->select('name', 'extension')
    //         ->first();

    //     if (!$provider) {
    //         return response()->json(['message' => 'Provider not found.'], 404);
    //     }

    //     return response()->json($provider);
    // }


    // Get all Auto Distributer..........................................................................................................................
    // public function autoDistributer()
    // {
    //     if (!Auth::check()) {
    //         return response()->json(['error' => 'Unauthorized'], 401);
    //     }

    //     $settings = Setting::first();
    //     $currentHour = now()->setTimezone('Asia/Riyadh')->hour;

    //     if ($settings->cfd_allow_friday == 1 || $settings->cfd_allow_saturday == 1) {
    //         return redirect('/settings')->with('wrong', 'Today is Weekend, Auto Dialer is disabled. you can skip weekend by updating settings');
    //     }
    //     if (
    //         $settings->allow_calling != 1 ||
    //         $currentHour < $settings->cfd_start_time ||
    //         $currentHour >= $settings->cfd_end_time
    //     ) {
    //         return redirect('/settings')->with('wrong', 'Calls are disabled as per settings');
    //     }

    //     $autoDistributer = AutoDirtibuterData::where('state', 'new')
    //         ->select('mobile', DB::raw('MAX(id) as id'), 'provider_name', 'extension')
    //         ->groupBy('mobile', 'provider_name', 'extension')
    //         ->get();

    //     $count = AutoDirtibuterData::getQuery()->count();

    //     if ($count == 0) {
    //         return redirect('/autodistributers')->with('wrong', 'No Auto Distributer Numbers Found. Please Insert and Call Again');
    //     }

    //     // Fetch and cache token
    //     $response = Http::asForm()->post(config('services.three_cx.api_url') . '/connect/token', [
    //         'grant_type' => 'client_credentials',
    //         'client_id' => 'testapi',
    //         'client_secret' => '95ULDtdTRRJhJBZCp94K6Gd1BKRuaP1k',
    //     ]);

    //     if ($response->failed()) {
    //         // Log or handle authentication failure
    //         return response()->json([
    //             'status' => 'error',
    //             'message' => 'Authentication failed',
    //             'details' => $response->body(),
    //         ], $response->status());
    //     }

    //     $token = $response->json()['access_token'] ?? null;

    //     if (!$token) {
    //         return response()->json([
    //             'status' => 'error',
    //             'message' => 'Token not found in the authentication response.',
    //         ], 400);
    //     }

    //     // Dispatch jobs for each record
    //     foreach ($autoDistributer as $record) {
    //         ProcessAutoDistributerrCall::dispatch($record, $token);
    //     }

    //     return redirect('/auto-distributer-report')->with('success', 'Auto Distributer is Calling Now...');
    // }



    // Call Auto Distributr By Clicking.............................................................................................................
    // public function autoDistributerByClicking(Request $request)
    // {
    //     if (!Auth::check()) {
    //         return response()->json(['error' => 'Unauthorized'], 401);
    //     }

    //     $settings = Setting::first();
    //     $currentHour = now()->setTimezone('Asia/Riyadh')->hour;

    //     if ($settings->cfd_allow_friday == 1 || $settings->cfd_allow_saturday == 1) {
    //         return redirect('/settings')->with('wrong', 'Today is Weekend, Auto Dialer is disabled. you can skip weekend by updating settings');
    //     }
    //     if (
    //         $settings->allow_calling != 1 ||
    //         $currentHour < $settings->cfd_start_time ||
    //         $currentHour >= $settings->cfd_end_time
    //     ) {
    //         return redirect('/settings')->with('wrong', 'Calls are disabled as per settings');
    //     }

    //     $autoDistributer = AutoDirtibuterData::where('state', 'new')
    //         ->select('mobile', DB::raw('MAX(id) as id'), 'provider_name', 'extension')
    //         ->groupBy('mobile', 'provider_name', 'extension')
    //         ->get();

    //     $count = AutoDirtibuterData::getQuery()->count();

    //     if ($count == 0) {
    //         return redirect('/autodistributers')->with('wrong', 'No Auto Distributer Numbers Found. Please Insert and Call Again');
    //     }

    //     if ($autoDistributer->every(fn($item) => $item->state === 'called')) {
    //         return redirect('/autodistributers')->with('success', 'All Auto Distributers are Called');
    //     }

    //     $response = Http::asForm()->post(config('services.three_cx.api_url') . '/connect/token', [
    //         'grant_type' => 'client_credentials',
    //         'client_id' => 'testapi',
    //         'client_secret' => '95ULDtdTRRJhJBZCp94K6Gd1BKRuaP1k',
    //     ]);

    //     if ($response->failed()) {
    //         return response()->json([
    //             'status' => 'error',
    //             'message' => 'Authentication failed',
    //             'details' => $response->body(),
    //         ], $response->status());
    //     }

    //     $token = $response->json()['access_token'] ?? null;

    //     if (!$token) {
    //         return response()->json([
    //             'status' => 'error',
    //             'message' => 'Token not found in the authentication response.',
    //         ], 400);
    //     }

    //      // Dispatch jobs for each record
    //      foreach ($autoDistributer as $record) {
    //         ProcessAutoDistributerrCall::dispatch($record, $token);
    //     }

    //     return redirect('/auto-distributer-report')->with('success', 'Auto Distributers are Calling Now...');
    // }




    // Get all Auto Dailer..........................................................................................................................
    // public function autoDailer(Request $request)
    // {
    //     if (!Auth::check()) {
    //         return response()->json(['error' => 'Unauthorized'], 401);
    //     }

    //     $settings = Setting::first();
    //     $currentHour = now()->setTimezone('Asia/Riyadh')->hour;

    //     if ($settings->cfd_allow_friday == 1 || $settings->cfd_allow_saturday == 1) {
    //         return redirect('/settings')->with('wrong', 'Today is Weekend, Auto Dialer is disabled. you can skip weekend by updating settings');
    //     }

    //     if (
    //         $settings->allow_auto_calling != 1 ||
    //         $currentHour < $settings->cfd_start_time ||
    //         $currentHour >= $settings->cfd_end_time
    //     ) {
    //         return redirect('/settings')->with('wrong', 'Calls are disabled as per settings');
    //     }

    //     $autoDailer = AutoDailerData::where('state', 'new')
    //         ->select('mobile', DB::raw('MAX(id) as id'), 'provider_name', 'extension')
    //         ->groupBy('mobile', 'provider_name', 'extension')
    //         ->get();

    //     $count = $autoDailer->count();

    //     if ($count == 0) {
    //         return redirect('/autodailers')->with('wrong', 'No Auto Dialer Numbers Found. Please Insert and Call Again');
    //     }

    //     // Get Token
    //     $response = Http::asForm()->post(config('services.three_cx.api_url') . '/connect/token', [
    //         'grant_type' => 'client_credentials',
    //         'client_id' => 'testapi',
    //         'client_secret' => '95ULDtdTRRJhJBZCp94K6Gd1BKRuaP1k',
    //     ]);

    //     if ($response->failed()) {
    //         return response()->json([
    //             'status' => 'error',
    //             'message' => 'Authentication failed',
    //             'details' => $response->body(),
    //         ], $response->status());
    //     }

    //     $token = $response->json()['access_token'] ?? null;

    //     if (!$token) {
    //         return response()->json([
    //             'status' => 'error',
    //             'message' => 'Token not found in the authentication response.',
    //         ], 400);
    //     }

    //     // Dispatch jobs for each record
    //     foreach ($autoDailer as $record) {
    //         ProcessAutoDailerCall::dispatch($record, $token);
    //     }

    //     return redirect('/auto-dailer-report')->with('success', 'Auto Dialer Jobs Dispatched.');
    // }


    // Call Auto Dailers By Clicking...................................................................................................................


    // public function autoDailerByClick(Request $request)
    // {
    //     if (!Auth::check()) {
    //         return response()->json(['error' => 'Unauthorized'], 401);
    //     }

    //     $settings = Setting::first();
    //     $currentHour = now()->setTimezone('Asia/Riyadh')->hour;

    //     if ($settings->cfd_allow_friday == 1 || $settings->cfd_allow_saturday == 1) {
    //         // return response()->json(["data" => "Today is Weekend, Auto Dialer is disabled."], 200);
    //         return redirect('/settings')->with('wrong', 'Today is Weekend, Auto Dialer is disabled. you can skip weekend by updating settings');
    //     }

    //     if (
    //         $settings->allow_auto_calling != 1 ||
    //         $currentHour < $settings->cfd_start_time ||
    //         $currentHour >= $settings->cfd_end_time
    //     ) {
    //         // return response()->json(["data" => "Calls are disabled as per settings"], 200);
    //         return redirect('/settings')->with('wrong', 'Calls are disabled as per settings');
    //     }

    //     $autoDailer = AutoDailerData::where('state', 'new')
    //         ->select('mobile', DB::raw('MAX(id) as id'), 'provider_name', 'extension')
    //         ->groupBy('mobile', 'provider_name', 'extension')
    //         ->get();


    //     $count = AutoDailerData::getQuery()->count();

    //     if ($count == 0) {
    //         return redirect('/autodailers')->with('wrong', 'No Auto Dailer Numbers Found. Please Insert and Call Again');
    //     }


    //     if ($autoDailer->every(fn($item) => $item->state === 'called')) {
    //         return redirect('/autodailers')->with('success', 'All Auto Dailers are Called');
    //     }


    //     $response = Http::asForm()->post(config('services.three_cx.api_url') . '/connect/token', [
    //         'grant_type' => 'client_credentials',
    //         'client_id' => 'testapi',
    //         'client_secret' => '95ULDtdTRRJhJBZCp94K6Gd1BKRuaP1k',
    //     ]);

    //     if ($response->failed()) {
    //         return response()->json([
    //             'status' => 'error',
    //             'message' => 'Authentication failed',
    //             'details' => $response->body(),
    //         ], $response->status());
    //     }

    //     $token = $response->json()['access_token'] ?? null;

    //     if (!$token) {
    //         return response()->json([
    //             'status' => 'error',
    //             'message' => 'Token not found in the authentication response.',
    //         ], 400);
    //     }

    //     // Dispatch jobs for each record
    //     foreach ($autoDailer as $record) {
    //         ProcessAutoDailerCall::dispatch($record, $token);
    //     }

    //     return redirect('/auto-dailer-report')->with('success', 'Auto Dialer is Calling Now...');
    // }



    // public function autoDailerShowState($id)
    // {
    //     $providers = AutoDailerData::where('id', $id)->first();
    //     return response()->json($providers);
    // }



    // Update the state of an AutoDailer.............................................................................................................
    // public function updateState(Request $request)
    // {
    //     $request->validate([
    //         'id' => 'required|integer',
    //         'state' => 'required',
    //     ]);

    //     $id = $request->input('id');

    //     $autoDailerData = AutoDailerData::find($id);

    //     if (!$autoDailerData) {
    //         return response()->json([
    //             'message' => 'AutoDailerData not found.'
    //         ], Response::HTTP_NOT_FOUND);
    //     }

    //     if ($request['state'] == True) {
    //         $autoDailerData->state = "called";
    //         $autoDailerData->save();
    //     }


    //     $report = AutoDailerReport::create(

    //         [
    //             'mobile' => $autoDailerData->mobile,
    //             'provider' => $autoDailerData->provider_name,
    //             'extension' => $autoDailerData->extension,
    //             'state' => $autoDailerData->state,
    //             'called_at' => now(),
    //             'declined_at' => now()
    //         ]
    //     );

    //     return response()->json([
    //         'message' => 'State and report updated successfully.',
    //         'data' => $report
    //     ], Response::HTTP_OK);
    // }





    // Update the state of an AutoDistributer.............................................................................................................
    // public function autoDistributerUpdateState(Request $request)
    // {

    //     $request->validate([
    //         'id' => 'required|integer',
    //         'state' => 'required',
    //     ]);

    //     $id = $request->input('id');

    //     $autoDailerData = AutoDirtibuterData::find($id);

    //     if (!$autoDailerData) {
    //         return response()->json([
    //             'message' => 'AutoDistributerReport not found.'
    //         ], Response::HTTP_NOT_FOUND);
    //     }

    //     $autoDailerData->state = $request->input('state') ? 'answered' : 'no answer';
    //     $autoDailerData->save();

    //     $report = AutoDistributerReport::create(

    //         [
    //             'mobile' => $autoDailerData->mobile,
    //             'provider' => $autoDailerData->provider_name,
    //             'extension' => $autoDailerData->extension,
    //             'state' => $autoDailerData->state,
    //             'called_at' => now(),
    //         ]
    //     );

    //     return response()->json([
    //         'message' => 'State and report updated successfully.',
    //         'data' => $report
    //     ], Response::HTTP_OK);
    // }


    // Get All Users.............................................................................................................
    // public function getUsers(Request $request)
    // {

    //     if (!Auth::check()) {
    //         return response()->json(['error' => 'Unauthorized'], 401);
    //     }

    //     $user = User::select('name', 'email', 'role')->get();
    //     return response()->json($user);
    // }
}
