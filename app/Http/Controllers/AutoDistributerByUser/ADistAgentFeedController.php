<?php

namespace App\Http\Controllers\AutoDistributerByUser;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ADistFeed;
use App\Models\ADistAgent;
use App\Models\ADistData;
use Carbon\Carbon;
use App\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use App\Services\TokenService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class ADistAgentFeedController extends Controller
{


    protected $tokenService;

    public function __construct(TokenService $tokenService)
    {

        $this->tokenService = $tokenService;
    }


    public function index()
    {
        $agents = ADistAgent::all();
        return view('autoDistributerByUser.Agent.index', compact('agents'));
    }

    public function createFile(ADistAgent $agent)
    {
       // $agents = ADistAgent::find($agent);
        return view('autoDistributerByUser.AgentFeed.create', compact('agent'));
    }


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

        return back()->with('success', 'File added and data imported successfully!');
    }

    private function processCsvFile($filePath, $provider, $fileId)
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

    private function isValidMobile($mobile)
    {
        return preg_match('/^9665[0-9]{8}$/', $mobile); // Validates Saudi mobile numbers
    }

    // Display all files for a agents
    public function files(ADistAgent $agent)
    {
        $feeds = $agent->files()->orderBy('created_at', 'desc')->paginate(5); // Change 'created_at' to the correct column if needed
        return view('autoDistributerByUser.AgentFeed.feed', compact('agent', 'feeds'));
    }
    public function showFileContent($slug)
    {
        // Find the file by slug instead of using route model binding
        $file = ADistFeed::where('slug', $slug)->firstOrFail();

        $data = ADistData::where('feed_id',$file->id )->paginate(400);

        return view('autoDistributerByUser.AgentFeed.show', compact('file', 'data'));
    }
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
}
