<?php

namespace App\Http\Controllers;

use App\Models\Evaluation;
use App\Models\License;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ApiController extends Controller
{
    /**
     * Update the satisfaction status of a call.
     *
     * @param  string  $call_id
     * @return \Illuminate\Http\Response
     */

    // Get Evaluation Response From 3CX CFD
    public function Evaluation(Request $request)
    {
        // Validate the input to make sure it's a boolean value and 'call_id' is provided
        $validated = $request->validate([
            'mobile' => 'required',
            'SERVICES_PROVIDED' => 'required',
            'agent' => 'nullable',
            'lang' => 'required',
        ]);

        // Find the report by call_id
        $report = Evaluation::create([
            'mobile' => $validated['mobile'],
            'is_satisfied' => $validated['SERVICES_PROVIDED'],
            'extension' => $validated['agent'],
            'lang' => $validated['lang'],
        ]);

        // Check if the report exists
        if (! $report) {
            return response()->json(['message' => 'Evaluation not found'], Response::HTTP_NOT_FOUND);
        }

        // Update the is_satisfied field
        $report->is_satisfied = $validated['SERVICES_PROVIDED'];
        $report->save();

        Log::info('Evaluation is done successfully to '.$report->mobile.' with '.$report->is_satisfied);

        return response()->json(['message' => 'Evaluation is done successfully', 'is_satisfied' => $report->is_satisfied], Response::HTTP_OK);
    }

    public function PostLicen(Request $request)
    {
        $validate = Validator::make($request->all(), [
            'license_key' => [
                'required',
                'string',
                'regex:/^[A-Z0-9]{5}-[A-Z0-9]{5}-[A-Z0-9]{5}-[A-Z0-9]{5}$/',
            ],
        ]);

        if ($validate->fails()) {
            return redirect()->back()->with('error', 'Invalid license key format.')->withInput();
        }

        try {
            // Send POST request to external licensing API
            $response = Http::post('http://127.0.0.1:8001/api/licens.ecolor/allow-license-key', [
                'license_key' => $request->license_key,
            ]);

            if ($response->successful()) {
                $responseData = $response->json();

                // Extract nested license data
                $licenseData = $responseData['data'];

                // Save to License model
                License::set('company_name', $licenseData['company_name'] ?? 'false', 'Company Name');
                License::set('license_key', $licenseData['license_key'] ?? 'false', 'License Key');
                License::set('start_date', $licenseData['start_date'] ?? 'false', 'Start Date');
                License::set('end_date', $licenseData['end_date'] ?? 'false', 'End Date');
                License::set('status', $licenseData['status'] ?? 'false', 'License Status');

                // JSON-encode module arrays before saving
                License::set(
                    'auto_dialer_modules',
                    isset($licenseData['license']['auto_dialer_modules']) && $licenseData['license']['auto_dialer_modules']
                        ? json_encode($licenseData['license']['auto_dialer_modules'])
                        : 'false',
                    'auto_dialer_modules'
                );

                License::set(
                    'auto_distributor_moduales',
                    isset($licenseData['license']['auto_distributor_moduales']) && $licenseData['license']['auto_distributor_moduales']
                        ? json_encode($licenseData['license']['auto_distributor_moduales'])
                        : 'false',
                    'auto_distributor_moduales'
                );

                License::set(
                    'evaluation_moduales',
                    isset($licenseData['license']['evaluation_moduales']) && $licenseData['license']['evaluation_moduales']
                        ? json_encode($licenseData['license']['evaluation_moduales'])
                        : 'false',
                    'evaluation_moduales'
                );

                return redirect()->back();

            } else {
                return response()->json([
                    'status' => 'error',
                    'message' => 'External API error.',
                    'response' => $response->json(),
                ], $response->status());
            }

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to connect to the external API.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
