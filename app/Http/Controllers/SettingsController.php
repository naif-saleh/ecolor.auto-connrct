<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\General_Setting;
use App\Models\CountCalls;
use App\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class SettingsController extends Controller
{

    public function index()
    {
        // Get current settings or use empty values
        $callTimeStart = General_Setting::get('call_time_start');
        $callTimeEnd = General_Setting::get('call_time_end');
        $number_calls = CountCalls::get('number_calls');
        $logo = General_Setting::get('logo', 'logos/default.png');

        return view('settings.index', compact('callTimeStart', 'callTimeEnd', 'logo', 'number_calls'));
    }

    public function updateBlockTime(Request $request)
{
    Log::info('Settings update request received.', ['request' => $request->all()]);

    $request->validate([
        'call_time_start' => 'required|date_format:H:i',
        'call_time_end' => 'required|date_format:H:i',
        'number_calls' => 'required',
        'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
    ]);

    // Update Call Time Settings
    General_Setting::set('call_time_start', $request->call_time_start . ':00', 'Start time for allowed calls');
    General_Setting::set('call_time_end', $request->call_time_end . ':00', 'End time for allowed calls');

    // Log previous number_calls value
    $count = CountCalls::get('number_calls');
    Log::info("Previous number_calls value: ", ['count' => $count]);

    // Update number_calls
    CountCalls::set('number_calls', $request->number_calls, 'Number of Calls Each Time');
    Log::info("Updated number_calls from $count to {$request->number_calls}");

    // Handle Logo Upload
    if ($request->hasFile('logo')) {
        Log::info('Logo file detected. Processing upload.');

        $file = $request->file('logo');
        $fileName = time() . '.' . $file->getClientOriginalExtension();
        $path = $file->storeAs('logos', $fileName, 'public');

        Log::info("Logo stored at: $path");

        if ($path) {
            General_Setting::set('logo', $path, 'Application Logo');
            Log::info("Database updated with logo path: $path");
        } else {
            Log::error('Failed to store logo.');
        }
    } else {
        Log::info('No logo uploaded.');
    }

    return redirect()->back()->with('success_time', 'Settings updated successfully.');
}





   
}
