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

        // Retrieve provider by exact name with only name and extension
        $provider = Provider::where('name', $name)
            ->select('name', 'extension') // Select only name and extension
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

        // Fetch only records where state is not 'answered'
        $autoDistributer = AutoDirtibuterData::where('state', '!=', 'answered')
            ->select('mobile', 'id', 'provider_name', 'extension')
            ->get();

        return response()->json($autoDistributer);

    }

    // Get all Auto Dailer..........................................................................................................................
    public function autoDailer()
    {
        if (!Auth::check()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // Fetch only records where state is not 'answered'
        $autoDailer = AutoDailerData::where('state', '=', 'new')
            ->select('mobile', 'id', 'provider_name', 'extension')
            ->get();


            return response()->json(
                 $autoDailer,

            );
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
       

        $autoDailerData->state = $request->input('state') ? 'answered' : 'no answer';
        $autoDailerData->save();

        $report = AutoDailerReport::create(

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
