<?php

namespace App\Http\Controllers\AutoDistributerByUser;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\ADistAgent;
use App\Models\ADistData;
use App\Models\ADistFeed;
use App\Models\ADistSkippedNumbers;
use App\Services\LicenseService;
use App\Services\TokenService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ADistAgentFeedController extends Controller
{
    // Identify token to get token from tokenService
    protected $tokenService;

    // class constructor
    public function __construct(TokenService $tokenService)
    {
        $this->tokenService = $tokenService;
    }

    // Auto Distributor Index
    // Auto Distributor Controller

    // Show the agents with selection functionality
    public function index(LicenseService $licenseService)
    {
        // Get all agents from database
        $allAgents = ADistAgent::all();
        $totalAgents = $allAgents->count();

        // Get max agents allowed from license
        $maxAgents = $licenseService->getMaxAutoDistributorAgents();
        $licenseInfo = $licenseService->getLicenseInfo();
        $isLicenseValid = $licenseService->isLicenseValid();

        // Get currently active agents
        $activeAgents = ADistAgent::where('is_active', true)->get();
        $activeAgentsCount = $activeAgents->count();

        // Initialize error variable
        $error = null;

        // Check license validity
        if (! $isLicenseValid) {
            $error = 'Your license is not active or has expired.';
        } elseif ($maxAgents <= 0) {
            $error = 'Auto Distributor module is not enabled or your license does not allow any agents.';
        }

        // Pass data to view
        return view('autoDistributerByUser.Agent.index', compact(
            'allAgents',
            'activeAgents',
            'activeAgentsCount',
            'totalAgents',
            'maxAgents',
            'licenseInfo',
            'isLicenseValid',
            'error'
        ));
    }

    // Handle agent activation/deactivation via AJAX
    public function toggleAgentStatus(Request $request, LicenseService $licenseService)
    {
        $agentId = $request->input('agent_id');
        $activate = filter_var($request->input('activate', true), FILTER_VALIDATE_BOOLEAN);

        // Get max agents allowed from license
        $maxAgents = $licenseService->getMaxAutoDistributorAgents();

        // Find the agent
        $agent = ADistAgent::findOrFail($agentId);

        // Get current status before changing it
        $wasActive = $agent->is_active;

        // Count current active agents
        $activeAgentsCount = ADistAgent::where('is_active', true)->count();

        // Only show error message when maxAgents = 0
        if ($activate && ! $wasActive) {
            if ($maxAgents == 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'License limit reached. Maximum 2 agents allowed. Please upgrade your license to activate more agents.',
                    'activeCount' => $activeAgentsCount,
                    'maxAllowed' => 2,
                    'showError' => true,
                ], 422);
            }
        }

        // Set new status
        $agent->is_active = $activate;

        // Only modify the count if status is actually changing
        if ($wasActive !== $activate) {
            if ($activate) {
                // Activating: decrease available count
                $licenseService->decrementAgentCount();
            } else {
                // Deactivating: increase available count
                $licenseService->incrementAgentCount();
            }
        }

        $agent->save();

        // Get updated count
        $newActiveCount = ADistAgent::where('is_active', true)->count();

        return response()->json([
            'success' => true,
            'message' => $activate ? 'Agent activated successfully.' : 'Agent deactivated successfully.',
            'activeCount' => $newActiveCount,
            'maxAllowed' => $maxAgents,
            'showError' => false,
        ]);
    }

    // Create File For an Agent
    public function createFile(ADistAgent $agent)
    {
        return view('autoDistributerByUser.AgentFeed.create', compact('agent'));
    }

    // Store New File For an Agent
    public function storeFile(Request $request, ADistAgent $agent)
    {

        $request->validate([
            'file_name' => 'required|string|max:255',
            'file_upload' => 'required|file|mimes:csv,txt,xlsx|max:5120', // Increased file size limit
            'from' => 'nullable|date_format:H:i',
            'to' => 'nullable|date_format:H:i',
            'date' => 'required|date',
        ]);

        // Store the uploaded file
        $filePath = $request->file('file_upload')->store('user_files');

        // Create a record for the file
        $file = ADistFeed::create([
            'file_name' => $request->file_name,
            'slug' => Str::slug($request->file_name.'-'.time()),
            'is_done' => false,
            'allow' => false,
            'from' => $request->from,
            'to' => $request->to,
            'date' => $request->date,
            'uploaded_by' => auth()->id(),
            'agent_id' => $agent->id,
        ]);

        // Process CSV file for mobile numbers
        $this->processCsvFile($filePath, $agent, $file->id);

        return redirect()->route('users.index', $file)->with('success', 'File added and data imported successfully!');
    }

    // Proccessing CSV File Data
    private function processCsvFile($filePath, $agent, $fileId)
    {
        $file = Storage::get($filePath);
        $lines = explode("\n", $file);
        $batchData = [];
        $batchSize = 1000; // Process in chunks

        foreach ($lines as $line) {
            $mobile = trim($line); // Assuming each line contains only a mobile number

            if ($this->isValidMobile($mobile)) {
                $batchData[] = [
                    'feed_id' => $fileId,
                    'mobile' => $mobile,
                    'state' => 'new', // Default state
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            } else {
                Log:
                info('Mobile Number is Invalid: '.$mobile);

                continue;
            }

            // Insert in batches to improve performance
            if (count($batchData) >= $batchSize) {
                ADistData::insert($batchData);
                $batchData = []; // Reset batch
            }
        }

        // Insert remaining records
        if (! empty($batchData)) {
            ADistData::insert($batchData);
        }
    }

    // Validate If Mobile Number is_KSA
    private function isValidMobile($mobile)
    {
        return preg_match('/^9665[0-9]{8}$/', $mobile); // Validates Saudi mobile numbers
    }

    // Display all files for an Agents
    public function files(ADistAgent $agent)
    {
        $feeds = $agent->files()->orderBy('created_at', 'desc')->paginate(10);

        return view('autoDistributerByUser.AgentFeed.feed', compact('agent', 'feeds'));
    }

    // Download File Data
    // This method is used to download the mobile numbers from a specific file
    // It retrieves all mobile numbers associated with the file and creates a CSV response
    public function downloadFileData(Request $request, ADistAgent $agent, ADistFeed $file)
    {
        // Get all mobile numbers for this file
        $numbers = ADistData::where('feed_id', $file->id)->pluck('mobile')->toArray();

        // Create CSV content
        $csvContent = "Mobile Number\n";
        foreach ($numbers as $number) {
            $csvContent .= $number."\n";
        }

        // Create a response with CSV headers
        $filename = $file->file_name.'_export_'.date('Y-m-d').'.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ];

        return response($csvContent, 200, $headers);
    }

    public function downloadSkippedNumbers($slug)
    {
        $file = ADistFeed::where('slug', $slug)->firstOrFail();

        $skippedNumbers = ADistSkippedNumbers::where('feed_id', $file->id)->get();

        if ($skippedNumbers->isEmpty()) {
            return redirect()->back()->with('error', 'No skipped numbers found for this file.');
        }

        $csvHeader = ['Mobile', 'Extension'];  // Define the columns of the CSV
        $csvData = [];

        foreach ($skippedNumbers as $skippedNumber) {
            $csvData[] = [$skippedNumber->mobile, $skippedNumber->extension];
        }

        $filename = "skipped_numbers_{$file->slug}.csv";
        $handle = fopen('php://output', 'w');
        fputcsv($handle, $csvHeader);

        foreach ($csvData as $row) {
            fputcsv($handle, $row);
        }

        fclose($handle);

        return Response::stream(
            function () use ($csvData) {
                $handle = fopen('php://output', 'w');
                fputcsv($handle, $csvHeader);
                foreach ($csvData as $row) {
                    fputcsv($handle, $row);
                }
                fclose($handle);
            },
            200,
            [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => "attachment; filename={$filename}",
            ]
        );
    }

    // Show Data Inside File
    public function showFileContent($slug)
    {
        // Find the file by slug instead of using route model binding
        $file = ADistFeed::where('slug', $slug)->firstOrFail();
        $numbers = ADistData::where('feed_id', $file->id)->count();
        $called = ADistData::where('feed_id', $file->id)->where('state', '!=', 'new')->count();
        $data = ADistData::where('feed_id', $file->id)->paginate(400);

        return view('autoDistributerByUser.AgentFeed.show', compact('file', 'data', 'numbers', 'called'));
    }

    // Active and Inactine File
    public function updateAllowStatus(Request $request, $slug)
    {
        $file = ADistFeed::where('slug', $slug)->firstOrFail();

        // Handle the 'allow' checkbox as a boolean
        $file->allow = $request->has('allow') ? (bool) $request->allow : false; // Ensure that allow is properly set as a boolean
        $file->save();

        // Active Log Report...............................
        ActivityLog::create([
            'user_id' => Auth::id(),
            'operation' => $file->allow ? 'Active' : 'Inactive',
            'file_id' => $file->id,
            'file_type' => 'Auto-Dist',
            'file_name' => $file->file_name,
            'operation_time' => now(),
        ]);

        if ($file->allow === false) {
            return back()->with('inactive', 'File is Disactivited ⚠️');
        }

        return back()->with('active', 'File is Activited ✅');
    }

    // Update File : File_name, From, To, Date
    public function update(Request $request, $slug)
    {
        $request->validate([
            'file_name' => 'required|string|max:255',
            'from' => 'nullable|date_format:H:i',
            'to' => 'nullable|date_format:H:i',
            'date' => 'required|date',
        ]);

        $file = ADistFeed::where('slug', $slug)->firstOrFail();

        $file->update([
            'file_name' => $request->file_name,
            'from' => $request->from,
            'to' => $request->to,
            'date' => $request->date,
        ]);

        return back()->with('success', 'File updated successfully');
    }

    // Delete File with All Data
    public function destroy($slug)
    {
        try {
            // Find the file by slug
            $file = ADistFeed::where('slug', $slug)->firstOrFail();
            $file->delete();

            return back()->with('success', 'File deleted successfully');
        } catch (\Exception $e) {
            return back()->with('wrong', 'No Query Results For');
        }
    }

    public function importCsvData(Request $request)
    {
        $skippedNumbers = [];
        $successCount = 0;
        $validRows = [];
        $agentCache = [];
        $feedCache = [];
        $batchSize = 1000;

        if (($handle = fopen($request->file, 'r')) !== false) {
            $header = fgetcsv($handle);

            if (! $header) {
                return response()->json(['errors' => ['Failed to read the CSV header.']], 422);
            }

            DB::disableQueryLog();
            Log::info('CSV Import Started', ['file' => $request->file->getClientOriginalName()]);

            // Preload ONLY active agents
            $activeAgents = ADistAgent::where('is_active', true)->pluck('extension', 'id')->toArray();
            $activeExtensions = array_flip($activeAgents);
            $seenNumbers = [];

            while (($data = fgetcsv($handle)) !== false) {
                if ($data === false) {
                    continue;
                }

                [$mobile, $name, $extension, $from, $to, $date] = $data;

                // Check if extension is valid AND agent is active
                if (! isset($activeExtensions[$extension])) {
                    $skippedNumbers[] = "$mobile - ⚠️ Agent Not Found or Inactive (Ext: $extension)";

                    continue;
                }

                // Validate mobile number (only numeric)
                if (! preg_match('/^\d+$/', $mobile)) {
                    $skippedNumbers[] = "$mobile - ❌ Contains non-numeric characters";

                    continue;
                }

                // Check for duplicate mobile numbers
                if (isset($seenNumbers[$mobile])) {
                    $skippedNumbers[] = "$mobile - 🔁 Duplicate Entry in CSV";

                    continue;
                }
                $seenNumbers[$mobile] = true;

                // Get agent ID from our preloaded active agents
                $agentId = array_search($extension, $activeAgents);

                // Cache agent lookup (avoid repeated queries)
                if (! isset($agentCache[$extension])) {
                    $agentCache[$extension] = ADistAgent::find($agentId);
                }
                $agent = $agentCache[$extension];

                // Format date and time
                $fromFormatted = Carbon::createFromFormat('h:i A', $from)->format('H:i:s');
                $toFormatted = Carbon::createFromFormat('h:i A', $to)->format('H:i:s');
                $dateFormatted = Carbon::parse($date)->format('Y-m-d');

                // Cache feed lookup (avoid repeated queries)
                $feedKey = "{$agent->id}-{$fromFormatted}-{$toFormatted}-{$dateFormatted}";
                if (! isset($feedCache[$feedKey])) {
                    $feedCache[$feedKey] = ADistFeed::create([
                        'agent_id' => $agent->id,
                        'from' => $fromFormatted,
                        'to' => $toFormatted,
                        'date' => $dateFormatted,
                        'file_name' => $name,
                        'slug' => Str::uuid(),
                        'uploaded_by' => auth()->id(),
                    ]);
                }
                $feed = $feedCache[$feedKey];

                // Prepare data for batch insert
                $validRows[] = [
                    'feed_id' => $feed->id,
                    'mobile' => $mobile,
                    'state' => 'new',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
                $successCount++;

                // Batch insert every 1000 records
                if (count($validRows) >= $batchSize) {
                    DB::transaction(function () use (&$validRows) {
                        ADistData::insert($validRows);
                    });
                    $validRows = []; // Clear batch
                }
            }

            // Insert remaining records
            if (! empty($validRows)) {
                DB::transaction(function () use (&$validRows) {
                    ADistData::insert($validRows);
                });
            }

            fclose($handle);

            // Bulk insert skipped numbers instead of looping
            if (! empty($skippedNumbers)) {
                $skippedInsertData = [];
                foreach ($skippedNumbers as $skipped) {
                    [$mobile, $message] = explode(' - ', $skipped);
                    preg_match('/(Ext: \d+)/', $message, $matches);
                    $extension = $matches[0] ?? null;

                    $skippedInsertData[] = [
                        'mobile' => $mobile,
                        'message' => $message,
                        'uploaded_by' => auth()->id(),
                        'agent_id' => $feed->agent_id ?? null,
                        'feed_id' => $feed->id ?? null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }

                if (! empty($skippedInsertData)) {
                    DB::transaction(function () use ($skippedInsertData) {
                        ADistSkippedNumbers::insert($skippedInsertData);
                    });
                }
            }

            Log::info('CSV Import Completed Successfully', ['file' => $request->file->getClientOriginalName()]);

            return response()->json([
                'message' => "$successCount records imported successfully!",
                'skippedNumbers' => $skippedNumbers,
            ], 200);
        }

        return response()->json(['errors' => ['Failed to open CSV file.']], 422);
    }
}
