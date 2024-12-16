<?php

namespace App\Http\Controllers;

use App\Models\Provider;
use App\Models\AutoDirtibuterData;
use App\Models\AutoDailerData;
use App\Models\User;
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

        $providers = AutoDirtibuterData::select('mobile', 'id', 'provider_name', 'extension')->get();
        return response()->json($providers);
    }

    // Get all Auto Dailer..........................................................................................................................
    public function autoDailer()
    {
        if (!Auth::check()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $providers = AutoDailerData::select('mobile', 'id', 'provider_name', 'extension')->get();
        return response()->json($providers);
    }


    public function autoDailerShowState($id){
        $providers = AutoDailerData::where('id', $id)->first();
        return response()->json($providers);
    }



    // Update the state of an AutoDailer.............................................................................................................
    public function updateState(Request $request, $id)
    {
        $request->validate([
            'state' => 'required|string|in:new,answered,no_answer', // Allowed states
        ]);

        $autoDailerData = AutoDailerData::find($id);

        if (!$autoDailerData) {
            return response()->json([
                'message' => 'AutoDailerData not found.'
            ], Response::HTTP_NOT_FOUND);
        }

        $autoDailerData->state = $request->input('state');
        $autoDailerData->save();

        return response()->json([
            'message' => 'State updated successfully.',
            'data' => $autoDailerData
        ], Response::HTTP_OK);
    }

     // Update the state of an AutoDistributer.............................................................................................................
     public function autoDistributerUpdateState(Request $request, $id)
     {

        $request->validate([
            'state' => 'required|string|in:new,answered,no_answer', // Allowed states
        ]);

        $autoDailerData = AutoDirtibuterData::find($id);

        if (!$autoDailerData) {
            return response()->json([
                'message' => 'AutoDirtibuterData not found.'
            ], Response::HTTP_NOT_FOUND);
        }

        $autoDailerData->state = $request->input('state');
        $autoDailerData->save();

        return response()->json([
            'message' => 'State updated successfully.',
            'data' => $autoDailerData
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
