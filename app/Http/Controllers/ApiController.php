<?php

namespace App\Http\Controllers;

use App\Models\Provider;
use App\Models\AutoDirtibuterData;
use App\Models\AutoDailerData;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ApiController extends Controller
{
    // Get all providers..........................................................................................................................
    public function index()
    {
        if (!Auth::check()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $providers = Provider::select('name', 'extension')->get();
        return response()->json($providers);
    }

    // Get Provider By Name..........................................................................................................................
    public function getByName($name)
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
    public function autoDistributet()
    {
        if (!Auth::check()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $providers = AutoDirtibuterData::select('mobile', 'provider_name', 'extension')->get();
        return response()->json($providers);
    }

    // Get all Auto Dailer..........................................................................................................................
    public function autoDailer()
    {
        if (!Auth::check()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $providers = AutoDailerData::select('mobile', 'provider_name', 'extension')->get();
        return response()->json($providers);
    }

    // Update the state of an AutoDailer.............................................................................................................
    public function autoDailerUpdateState(Request $request)
    {

        if (!Auth::check()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $request->validate([
            'state' => 'required|string|in:answerd,no_answred',
        ]);

        $autodailer = AutoDailerData::find($request->$id);
        if (!$autodailer) {
            return response()->json(['error' => 'AutoDailer not found'], 404);
        }

        $autodailer->$request->state;
        $autodailer->save();
        return response()->json($autodailer);
    }

     // Update the state of an AutoDistributer.............................................................................................................
     public function autoDistributerUpdateState(Request $request)
     {

         if (!Auth::check()) {
             return response()->json(['error' => 'Unauthorized'], 401);
         }

         $request->validate([
             'state' => 'required|string|in:answerd,no_answred',
         ]);

         $autodistributer = AutoDirtibuterData::find($request->$id);
         if (!$autodistributer) {
             return response()->json(['error' => 'AutoDailer not found'], 404);
         }

         $autodistributer->$request->state;
         $autodistributer->save();
         return response()->json($autodistributer);
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
