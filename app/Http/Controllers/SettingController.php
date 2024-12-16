<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Setting;
use App\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;

class SettingController extends Controller
{

     /**
 *  @OA\Get(
 *       path="/settings",
 *       tags={"Settings"},
 *       summary="Get all Settings",
 *       description="Get list of all Settings",
 *       @OA\Response(response=200, description="Settings retrieved successfully")
 *   )
 */

    // Settings Upload Form..................................................................................................
    public function showForm()
    {
        $settings = Setting::firstOrNew();

        $currentHour = now()->setTimezone('Asia/Riyadh')->hour;
        // $currentDay = now()->dayOfWeek;


        return view('sittings', [
            'settings' => $settings,
            'currentHour' => $currentHour,
            'hours' => range(1, 24),
        ]);
    }

    // Settings Upload Data..................................................................................................
    public function saveSettings(Request $request)
    {

        $currentHour = now()->setTimezone('Asia/Riyadh')->hour;
        $currentDay = now()->dayOfWeek;


        $validated = $request->validate([
            'allow_calling' => 'required|boolean',
            'allow_auto_calling' => 'required|boolean',
            'cfd_start_time' => 'required|integer|between:1,24',
            'cfd_end_time' => 'required|integer|between:1,24|gt:cfd_start_time',
            'cfd_allow_friday' => 'nullable|boolean',
            'cfd_allow_saturday' => 'nullable|boolean',
        ]);


        $settings = Setting::firstOrNew();
        $settings->allow_calling = $validated['allow_calling'];
        $settings->allow_auto_calling = $validated['allow_auto_calling'];
        $settings->cfd_start_time = $validated['cfd_start_time'];
        $settings->cfd_end_time = $validated['cfd_end_time'];
        $settings->cfd_allow_friday = $validated['cfd_allow_friday'] ?? false;
        $settings->cfd_allow_saturday = $validated['cfd_allow_saturday'] ?? false;
        $settings->save();

        // Active Log Report...............................
        ActivityLog::create([
            'user_id' => Auth::id(),
            'operation' => 'update',
            'file_type' => 'settings',
            'file_name' => 'settings',
            'operation_time' => now(),
        ]);

        $isWeekend = in_array($currentDay, [5, 6]);


        $isInTimeRange = ($currentHour >= $settings->cfd_start_time && $currentHour < $settings->cfd_end_time);


        $response = [
            'auto_call' => ($isWeekend || !$isInTimeRange || $settings->cfd_allow_friday || $settings->cfd_allow_saturday) ? 0 : $settings->allow_auto_calling,
            'online' => ($isWeekend || !$isInTimeRange || $settings->cfd_allow_friday || $settings->cfd_allow_saturday) ? 0 : $settings->allow_calling,
            'start' => $settings->cfd_start_time,
            'end' => $settings->cfd_end_time,
        ];


        return redirect('/settings')->with('success', 'Cofiguration Done Successfully');
    }

// ********************************************************************************************************************************************
                                                // Settings Endpoints (API URLs)                                                               *
// ********************************************************************************************************************************************
    public function getCfdApi(Request $request)
    {

        if (!Auth::check()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $settings = Setting::firstOrNew();

        $currentHour = now()->setTimezone('Asia/Riyadh')->hour;
        $currentDay = now()->dayOfWeek;

        $isWeekend = in_array($currentDay, [5, 6]);

        $isInTimeRange = ($currentHour >= $settings->cfd_start_time && $currentHour < $settings->cfd_end_time);

        $response = [
            'auto_call' => ($isWeekend || !$isInTimeRange) ? 0 : $settings->allow_auto_calling,
            'online' => ($isWeekend || !$isInTimeRange) ? 0 : $settings->allow_calling,
            'start' => $settings->cfd_start_time,
            'end' => $settings->cfd_end_time,
        ];


        return response()->json($response);
    }
}
