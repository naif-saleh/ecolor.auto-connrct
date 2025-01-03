<?php

namespace App\Http\Controllers\AutoDistributerByUser;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\AutoDistributererExtension;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class UserForAutoDistributer extends Controller
{

    public function index()
    {
        $extensions = AutoDistributererExtension::all();
        return view('autoDistributerByUser.User.index', compact('extensions'));
    }

    public function import()
    {
        $token = Cache::get('three_cx_token');
        
        try {
            $responseState = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token,
            ])->get(config('services.three_cx.api_url') . "/xapi/v1/Users");
            dd($responseState);
            if ($responseState->successful()) {
                $responseData = $responseState->json();
dd($responseData);
//foreach 
// foreach ($responseData as $data) {
//     # code...
// }
// add $data['DisplayName']
// $data['Id']
// $data['Number']
// 

// AutoDistributererExtension::firstOrcreate([
//     "name"=> $data['DisplayName'],
//     "ext" => $data['Number'],
//     "3cxID" => $data['Id'],
// ]);

            }

        } catch (\Exception $e) {
            Log::error('import: An error occurred: ' . $e->getMessage());
        }


    }
    public function create()
    {
        $users = \App\Models\User::all();
        return view('autoDistributerByUser.User.create', compact('users'));
    }


    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'extension' => 'required|string|max:20',

        ]);

        AutoDistributererExtension::create($request->all());

        return redirect()->route('auto_distributerer_extensions.index')
                         ->with('success', 'Extension created successfully.');
    }


    public function show(AutoDistributererExtension $autoDistributererExtension)
    {
        return view('autoDistributerByUser.User.show', compact('autoDistributererExtension'));
    }


    public function edit(AutoDistributererExtension $autoDistributererExtension)
    {
        $users = \App\Models\User::all();
        return view('autoDistributerByUser.User.edit', compact('autoDistributererExtension','users'));
    }


    public function update(Request $request, AutoDistributererExtension $autoDistributererExtension)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'extension' => 'required|string|max:20',
            'user_id' => 'required|exists:users,id',
        ]);

        $autoDistributererExtension->update($request->all());

        return redirect()->route('auto_distributerer_extensions.index')
                         ->with('success', 'Extension updated successfully.');
    }


    public function destroy(AutoDistributererExtension $autoDistributererExtension)
    {
        $autoDistributererExtension->delete();

        return redirect()->route('auto_distributerer_extensions.index')
                         ->with('success', 'Extension deleted successfully.');
    }


}
