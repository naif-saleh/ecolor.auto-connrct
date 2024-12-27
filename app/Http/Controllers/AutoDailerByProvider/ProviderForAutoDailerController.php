<?php

namespace App\Http\Controllers\AutoDailerByProvider;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\AutoDialerProvider;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use App\Jobs\ProcessAutoDailerProvider;
use Illuminate\Support\Facades\Auth;
use App\Models\AutoDailerProviderFeed;


class ProviderForAutoDailerController extends Controller
{
    public function index()
    {
        $providers = AutoDialerProvider::all();
        return view('autoDailerByProvider.Provider.index', compact('providers'));
    }

    public function create()
    {
        return view('autoDailerByProvider.Provider.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'extension' => 'required|string|max:50',
            'file_sound' => 'nullable|file|mimes:mp3,wav',
            'user_id' => 'required|exists:users,id',
        ]);

        if ($request->hasFile('file_sound')) {
            $validated['file_sound'] = $request->file('file_sound')->store('sounds');
        }

        AutoDialerProvider::create($validated);

        return redirect()->route('autoDialerProviders.index')->with('success', 'Provider created successfully!');
    }

    public function show($id)
    {
        $provider = AutoDialerProvider::findOrFail($id);
        return view('autoDailerByProvider.Provider.show', compact('provider'));
    }

    public function edit($id)
    {
        $provider = AutoDialerProvider::findOrFail($id);
        return view('autoDailerByProvider.Provider.edit', compact('provider'));
    }

    public function update(Request $request, $id)
    {
        $provider = AutoDialerProvider::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'extension' => 'sometimes|string|max:50',
            'file_sound' => 'nullable|file|mimes:mp3,wav',
            'user_id' => 'sometimes|exists:users,id',
        ]);

        if ($request->hasFile('file_sound')) {
            $validated['file_sound'] = $request->file('file_sound')->store('sounds');
        }

        $provider->update($validated);

        return redirect()->route('autoDialerProviders.index')->with('success', 'Provider updated successfully!');
    }

    public function destroy($id)
    {
        $provider = AutoDialerProvider::findOrFail($id);
        $provider->delete();

        return redirect()->route('autoDialerProviders.index')->with('success', 'Provider deleted successfully!');
    }



    public function autoDailer(Request $request)
{
    if (!Auth::check()) {
        return response()->json(['error' => 'Unauthorized'], 401);
    }

    $autoDailer = AutoDailerProviderFeed::where('state', 'new')
        ->select('mobile', DB::raw('MAX(id) as id'), 'extension')
        ->groupBy('mobile', 'extension')
        ->get();

    $count = $autoDailer->count();

    if ($count == 0) {
        return redirect('/auto-dialer-providers')->with('wrong', 'No Auto Dialer Numbers Found. Please Insert and Call Again');
    }

    // Get Token
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

    // Dispatch jobs for each record with the same queue
    foreach ($autoDailer as $record) {
        // Specify the queue here
        ProcessAutoDailerProvider::dispatch($record, $token)->onQueue('auto_dialer_queue');
    }

    return redirect()->route('autoDialerProviders.index')->with('success', 'Auto Dialer Jobs Dispatched.');
}

}
