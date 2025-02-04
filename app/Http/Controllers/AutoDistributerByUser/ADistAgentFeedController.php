<?php

namespace App\Http\Controllers\AutoDistributerByUser;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ADistFeed;
use App\Models\ADistAgent;
use App\Models\ADistData;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use App\Services\TokenService;
use Illuminate\Support\Facades\Storage;
use App\Models\ActivityLog;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ADistAgentFeedController extends Controller
{

    //Identify token to get token from tokenService
    protected $tokenService;

    //class constructor
    public function __construct(TokenService $tokenService)
    {
        $this->tokenService = $tokenService;
    }

    //Auto Distributor Index
    public function index()
    {
        $agents = ADistAgent::paginate(20);
        return view('autoDistributerByUser.Agent.index', compact('agents'));
    }

    //Create File For an Agent
    public function createFile(ADistAgent $agent)
    {
        return view('autoDistributerByUser.AgentFeed.create', compact('agent'));
    }


    //Store New File For an Agent
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
            'slug' => Str::slug($request->file_name . '-' . time()),
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

    //Proccessing CSV File Data
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
            }
            else{
                Log:info('Mobile Number is Invalid: '.$mobile);
                continue;
             }

            // Insert in batches to improve performance
            if (count($batchData) >= $batchSize) {
                ADistData::insert($batchData);
                $batchData = []; // Reset batch
            }
        }

        // Insert remaining records
        if (!empty($batchData)) {
            ADistData::insert($batchData);
        }
    }

    //Validate If Mobile Number is_KSA
    private function isValidMobile($mobile)
    {
        return preg_match('/^9665[0-9]{8}$/', $mobile); // Validates Saudi mobile numbers
    }

    // Display all files for an Agents
    public function files(ADistAgent $agent)
    {
        $feeds = $agent->files()->orderBy('created_at', 'desc')->paginate(10); // Change 'created_at' to the correct column if needed
        return view('autoDistributerByUser.AgentFeed.feed', compact('agent', 'feeds'));
    }

    //Show Data Inside File
    public function showFileContent($slug)
    {
        // Find the file by slug instead of using route model binding
        $file = ADistFeed::where('slug', $slug)->firstOrFail();
        $numbers = ADistData::where('feed_id', $file->id)->count();
        $data = ADistData::where('feed_id', $file->id)->paginate(400);

        return view('autoDistributerByUser.AgentFeed.show', compact('file', 'data', 'numbers'));
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
            'operation' => $file->allow ? 'Active' : "Inactive",
            'file_id' => $file->id,
            'file_type' => 'Auto-Dist',
            'file_name' => $file->file_name,
            'operation_time' => now(),
        ]);

        if($file->allow === false){
            return back()->with('inactive', 'File is Disactivited ⚠️');
        }
        return back()->with('active', 'File is Activited ✅');
    }

    //Update File : File_name, From, To, Date
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


    //Delete File with All Data
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

     //Import Huge Count of Numbers to all Providers Directlly From CSV File
     public function importCsvData(Request $request)
     {
         $errors = [];
         $successCount = 0;

         if (($handle = fopen($request->file, 'r')) !== false) {
             $header = fgetcsv($handle);

             if (!$header) {
                 $errors[] = "Failed to read the CSV header.";
                 return response()->json(['errors' => $errors], 422);
             }

             DB::beginTransaction();

             try {
                 Log::info('CSV Import Started', ['file' => $request->file->getClientOriginalName()]);

                 while (($data = fgetcsv($handle)) !== false) {
                     if ($data === false) {
                         $errors[] = "Invalid row in CSV file.";
                         continue;
                     }

                     list($mobile, $name, $extension, $from, $to, $date) = $data;

                     //   Validate Mobile Number (Must be KSA Format)
                     if (!preg_match('/^9665[0-9]{8}$/', $mobile)) {
                         $errors[] = "Invalid Saudi mobile number: $mobile";
                         continue;
                     }

                     //   Find or Create Agent
                     $agent = ADistAgent::firstOrCreate(
                         ['extension' => $extension],
                         ['user_id' => auth()->id()]
                     );

                     //This condition not working
                     if (!$agent) {
                         $errors[] = "Failed to find or create agent for extension: $extension";
                         continue;
                     }

                     //   Process Date and Time
                     $currentFeedData = [
                         'from' => Carbon::createFromFormat('h:i A', $from)->format('H:i:s'),
                         'to' => Carbon::createFromFormat('h:i A', $to)->format('H:i:s'),
                         'date' => Carbon::parse($date)->format('Y-m-d'),
                     ];

                     //  Check if Feed Exists or Create a New One
                     $feed = ADistFeed::where([
                         'agent_id' => $agent->id,
                         'from' => $currentFeedData['from'],
                         'to' => $currentFeedData['to'],
                         'date' => $currentFeedData['date'],
                     ])->first();

                     if (!$feed) {
                         $slug = Str::uuid();
                         $feed = ADistFeed::create([
                             'file_name' => $name,
                             'slug' => $slug,
                             'date' => $currentFeedData['date'],
                             'from' => $currentFeedData['from'],
                             'to' => $currentFeedData['to'],
                             'agent_id' => $agent->id,
                             'uploaded_by' => auth()->id()
                         ]);
                     }

                     //  Skip Duplicate Mobile Numbers
                     if (ADistData::where('mobile', $mobile)->where('feed_id', $feed->id)->exists()) {
                         $errors[] = "Duplicate mobile number skipped: $mobile";
                         Log::info('Duplicate mobile number ' . $mobile);
                         continue;
                     }

                     //  Store Valid Data
                     ADistData::create([
                         'feed_id' => $feed->id,
                         'mobile' => $mobile,
                         'state' => 'new',
                     ]);

                     Log::info('Adding mobile ' . $mobile);
                     $successCount++;
                 }

                 fclose($handle);

                 if ($successCount > 0) {
                     DB::commit(); //   Commit if there are valid rows
                     Log::info('CSV Import Completed Successfully', ['file' => $request->file->getClientOriginalName()]);
                 } else {
                     DB::rollBack(); //  Rollback if no valid records
                     Log::error('No valid records found. Rolling back.');
                     return response()->json(['errors' => ["No valid records found. Nothing was imported."]], 422);
                 }

                 return response()->json([
                     'message' => "$successCount records imported successfully!",
                     'errors' => $errors
                 ], 200);
             } catch (\Exception $e) {
                 DB::rollBack(); //   Rollback on error
                 Log::error('CSV Import Error: ' . $e->getMessage(), ['file' => $request->file->getClientOriginalName(), 'error' => $e->getTraceAsString()]);
                 fclose($handle);
                 return response()->json(['errors' => ["Internal server error: " . $e->getMessage()]], 500);
             }
         }

         return response()->json(['errors' => ["Failed to open CSV file."]], 422);
     }



}
