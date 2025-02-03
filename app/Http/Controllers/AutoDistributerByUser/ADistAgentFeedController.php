<?php

namespace App\Http\Controllers\AutoDistributerByUser;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ADistFeed;
use App\Models\ADistAgent;
use App\Models\ADistData;
use App\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use App\Services\TokenService;
use Illuminate\Support\Facades\Storage;

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

        return back();
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

}
