<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\General_Setting;
use App\Models\CountCalls;
use App\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;

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

        // Retrieve current values
        $timeStart = General_Setting::get('call_time_start');
        $timeEnd = General_Setting::get('call_time_end');

        $comment = "Update Main Time from (Start) $timeStart to $request->call_time_start
                    and (End) from $timeEnd to $request->call_time_end";

        // Update settings
        General_Setting::set('call_time_start', $request->call_time_start . ':00', 'Start time for allowed calls');
        General_Setting::set('call_time_end', $request->call_time_end . ':00', 'End time for allowed calls');

        // Log the operation
        ActivityLog::create([
            'user_id' => Auth::id(),
            'operation' => $comment,
            'file_type' => 'Main-Time',
            'file_name' => 'Main-Time',
            'operation_time' => now(),
        ]);

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

        $count = CountCalls::get('number_calls');
        CountCalls::set('number_calls', $request->number_calls, 'Number of Calls Each Time');
        $comment = "Update Number of Calls in moment from $count to $request->number_calls";

         // Log the operation
         ActivityLog::create([
            'user_id' => Auth::id(),
            'operation' => $comment,
            'file_type' => 'Number of Calls in moment',
            'file_name' => 'Number of Calls in moment',
            'operation_time' => now(),
        ]);

        return redirect()->back()->with('success_count', 'Number of Calls Updated Successfully.');
    }
}
