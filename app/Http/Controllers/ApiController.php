<?php

namespace App\Http\Controllers;

use App\Models\AutoDailerReport;
use App\Models\AutoDistributerReport;
use App\Models\Evaluation;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;



class ApiController extends Controller
{

    /**
     * Update the satisfaction status of a call.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $call_id
     * @return \Illuminate\Http\Response
     */

     //Get Evaluation Response From 3CX CFD
    public function Evaluation(Request $request)
    {
        // Validate the input to make sure it's a boolean value and 'call_id' is provided
        $validated = $request->validate([
            'mobile' => 'required',
            'SERVICES_PROVIDED' => 'required',
            'agent' => 'nullable',
            'lang' => 'required'
        ]);


        // Find the report by call_id
        $report = Evaluation::create([
            'mobile' => $validated['mobile'],
            'is_satisfied' => $validated['SERVICES_PROVIDED'],
            'extension' => $validated['agent'],
            'lang' => $validated['lang'],
        ]);

        // Check if the report exists
        if (!$report) {
            return response()->json(['message' => 'Evaluation not found'], Response::HTTP_NOT_FOUND);
        }

        // Update the is_satisfied field
        $report->is_satisfied = $validated['SERVICES_PROVIDED'];
        $report->save();

        Log::info('Evaluation is done successfully to ' . $report->mobile." with ".$report->is_satisfied);
        return response()->json(['message' => 'Evaluation is done successfully', 'is_satisfied' => $report->is_satisfied], Response::HTTP_OK);
    }




}
