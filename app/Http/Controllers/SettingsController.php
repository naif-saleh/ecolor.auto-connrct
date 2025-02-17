<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\General_Setting;
use App\Models\CountCalls;

class SettingsController extends Controller
{

    public function index()
    {
        // Get current settings or use empty values
        $callTimeStart = General_Setting::get('call_time_start');
        $callTimeEnd = General_Setting::get('call_time_end');
        $number_calls = CountCalls::get('number_calls');

        return view('settings.index', compact('callTimeStart', 'callTimeEnd', 'number_calls'));
    }

    public function updateBlockTime(Request $request)
    {
        $request->validate([
            'call_time_start' => 'required|date_format:H:i',
            'call_time_end' => 'required|date_format:H:i',
        ]);

        General_Setting::set('call_time_start', $request->call_time_start . ':00', 'Start time for allowed calls');
        General_Setting::set('call_time_end', $request->call_time_end . ':00', 'End time for allowed calls');

        return redirect()->back()->with('success_time', 'Call time settings updated successfully.');
    }


    public function indexCountCall()
    {

        $number_calls = CountCalls::get('number_calls');

        return view('settings.indexCountCalls', compact('number_calls'));
    }
    public function updateCallsNumber(Request $request)
    {
        $request->validate([
            'number_calls' => 'required',
        ]);

        CountCalls::set('number_calls', $request->number_calls, 'Number of Calls Each Time');

        return redirect()->back()->with('success_count', 'Number of Calls Updated Successfully.');
    }
}
