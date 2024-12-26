<?php

namespace App\Http\Controllers;

use App\Models\Provider;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProviderController extends Controller
{

      /**
 *  @OA\Get(
 *       path="/provider",
 *       tags={"Provider"},
 *       summary="Get all Provider",
 *       description="Get list of all Provider",
 *       @OA\Response(response=200, description="Provider retrieved successfully")
 *   )
 */


    public function index()
    {
        $providers = Provider::with('user')->latest()->get();
        return view('providers.index', compact('providers'));
    }

    public function create()
    {
        return view('providers.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'extension' => 'required|string|max:10',
        ]);

        Provider::create([
            'name' => $request->name,
            'extension' => $request->extension,
            'user_id' => Auth::id(),
        ]);


        // Active Log Report...............................
        ActivityLog::create([
            'user_id' => Auth::id(),
            'operation' => 'create',
            'file_type' => 'Provider',
            'file_name' => $request->input('name'),
            'operation_time' => now(),
        ]);

        return redirect()->route('providers.index')->with('success', 'Provider created successfully.');
    }

    public function edit($id)
    {
        $provider = Provider::findOrFail($id);
        return view('providers.edit', compact('provider'));
    }

    // Update the provider details
    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'extension' => 'required|string|max:10',
        ]);

        $provider = Provider::findOrFail($id);
        $provider->update([
            'name' => $request->name,
            'extension' => $request->extension,
        ]);


        // Active Log Report...............................
        ActivityLog::create([
            'user_id' => Auth::id(),
            'operation' => 'update',
            'file_type' => 'Provider',
            'file_name' => $request->input('name'),
            'operation_time' => now(),
        ]);

        return redirect()->route('providers.index')->with('success', 'Provider updated successfully.');
    }

    public function destroy($id)
    {
        $provider = Provider::find($id);
        $provider->delete();
        // Active Log Report...............................
        ActivityLog::create([
            'user_id' => Auth::id(),
            'operation' => 'delete',
            'file_type' => 'Provider',
            'file_name' => $provider->name,
            'operation_time' => now(),
        ]);
        return back()->with('success', 'Provider deleted.');
    }


}
