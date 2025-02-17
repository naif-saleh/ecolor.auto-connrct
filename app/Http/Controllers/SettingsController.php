<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Setting;


class SettingsController extends Controller
{

    public function index()
    {
        // Get current settings or use empty values
        $callTimeStart = Setting::get('call_time_start');
        $callTimeEnd = Setting::get('call_time_end');

        return view('settings.index', compact('callTimeStart', 'callTimeEnd'));
    }

    public function update(Request $request)
    {
        $request->validate([
            'call_time_start' => 'required|date_format:H:i',
            'call_time_end' => 'required|date_format:H:i',
        ]);

        Setting::set('call_time_start', $request->call_time_start . ':00', 'Start time for allowed calls');
        Setting::set('call_time_end', $request->call_time_end . ':00', 'End time for allowed calls');

        return redirect()->back()->with('success', 'Call time settings updated successfully.');
    }
}
