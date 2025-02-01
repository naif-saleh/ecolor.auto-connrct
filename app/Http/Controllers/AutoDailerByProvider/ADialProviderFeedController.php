<?php

namespace App\Http\Controllers\AutoDailerByProvider;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ADialData;
use App\Models\ADialFeed;
use App\Models\ADialProvider;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use App\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;
class ADialProviderFeedController extends Controller
{

    public function index()
    {
        $providers = ADialProvider::all();
        return view('autoDailerByProvider.Provider.index', compact('providers'));
    }

    public function create()
    {
        return view('autoDailerByProvider.Provider.create');
    }

    // Store New Provider
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:a_dial_providers,name',
            'extension' => 'nullable|string|max:50',
        ]);

        try {
            ADialProvider::create([
                'name' => $request->name,
                'extension' => $request->extension,
                'user_id' => auth()->id(), // Ensure the user is logged in
            ]);

            return redirect('/providers')->with('success', 'Provider Created Successfully');
        } catch (\Exception $e) {
            return response()->json(['error' => 'Something went wrong: ' . $e->getMessage()], 500);
        }
    }


    public function createFile(ADialProvider $provider)
    {
        $providers = ADialProvider::find($provider);
        return view('autoDailerByProvider.ProviderFeed.create', compact('provider'));
    }


    public function storeFile(Request $request, ADialProvider $provider)
    {
        $request->validate([
            'file_name' => 'required|string|max:255',
            'file_upload' => 'required|file|mimes:csv,txt,xlsx|max:5120', // Increased file size limit
            'from' => 'nullable|date_format:H:i',
            'to' => 'nullable|date_format:H:i',
            'date' => 'required|date',
        ]);

        // Store the uploaded file
        $filePath = $request->file('file_upload')->store('provider_files');

        // Create a record for the file
        $file = ADialFeed::create([
            'file_name' => $request->file_name,
            'slug' => Str::slug($request->file_name . '-' . time()),
            'is_done' => false,
            'allow' => false,
            'from' => $request->from,
            'to' => $request->to,
            'date' => $request->date,
            'uploaded_by' => auth()->id(),
            'provider_id' => $provider->id,
        ]);

        // Process CSV file for mobile numbers
        $this->processCsvFile($filePath, $provider, $file->id);

        return redirect()->route('provider.files.index', $provider)->with('success', 'File added and data imported successfully!');
    }

    private function processCsvFile($filePath, $provider, $fileId)
    {
        $file = Storage::get($filePath);
        $lines = explode("\n", $file);
        $batchData = [];
        $batchSize = 1000; // Process in chunks

        foreach ($lines as $line) {
            $mobile = trim($line); // Assuming each line contains only a mobile number
            Log::info('mobile executed at ' . $mobile);
            if ($this->isValidMobile($mobile)) {
                $batchData[] = [
                    'feed_id' => $fileId,
                    'mobile' => $mobile,
                    'state' => 'new', // Default state
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            } else {
                Log::info('This mobile not valid ' . $mobile);
            }

            // Insert in batches to improve performance
            if (count($batchData) >= $batchSize) {
                ADialData::insert($batchData);
                $batchData = []; // Reset batch
            }
        }
        // Insert remaining records
        if (!empty($batchData)) {
            ADialData::insert($batchData);
        }
    }

    private function isValidMobile($mobile)
    {
        return preg_match('/^9665[0-9]{8}$/', $mobile); // Validates Saudi mobile numbers
    }

    // Display all files for a provider
    public function files(ADialProvider $provider)
    {
        $files = $provider->files; // Relationship defined in the provider model
        return view('autoDailerByProvider.ProviderFeed.feed', compact('provider', 'files'));
    }


    public function showFileContent($slug)
    {
        // Find the file by slug instead of using route model binding
        $file = ADialFeed::where('slug', $slug)->firstOrFail();

        $data = ADialData::where('feed_id',$file->id )->paginate(400);

        return view('autoDailerByProvider.ProviderFeed.show', compact('file', 'data'));
    }


    public function update(Request $request, $slug)
    {
        // Validate incoming request
        $request->validate([
            'file_name' => 'required|string|max:255',
            'from' => 'nullable|date_format:H:i',
            'to' => 'nullable|date_format:H:i',
            'date' => 'required|date',
        ]);

        // Find the file by slug
        $file = ADialFeed::where('slug', $slug)->firstOrFail();

        // Update the file's data
        $file->update([
            'file_name' => $request->file_name,
            'from' => $request->from,
            'to' => $request->to,
            'date' => $request->date,
        ]);

        return redirect()->route('provider.files.index', $file->provider)
            ->with('success', 'File updated successfully!');
    }



    public function destroy($slug)
    {
        try {
            // Find the file by its slug
            $file = ADialFeed::where('slug', $slug)->firstOrFail();

            // Optionally, delete associated data or related entries if necessary
            $file->uploadedData()->delete(); // Delete all uploaded data related to this file

            // Delete the actual file from storage
            if (Storage::exists("provider_files/{$file->slug}")) {
                Storage::delete("provider_files/{$file->slug}");
            }

            // Delete the file record from the database
            $file->delete();

            return redirect()->route('provider.files.index', $file->provider)
                ->with('success', 'File deleted successfully!');
        } catch (\Exception $e) {
            Log::error("❌ Error deleting file: " . $e->getMessage());
            return redirect()->route('provider.files.index')
                ->with('error', 'Failed to delete the file. Please try again.');
        }
    }


    public function updateAllowStatus(Request $request, $slug)
    {
        $file = ADialFeed::where('slug', $slug)->firstOrFail();

        // Handle the 'allow' checkbox as a boolean
        $file->allow = $request->has('allow') ? (bool) $request->allow : false; // Ensure that allow is properly set as a boolean
        $file->save();

        // Active Log Report...............................
        ActivityLog::create([
            'user_id' => Auth::id(),
            'operation' => $file->allow ? 'Active' : "Inactive",
            'file_id' => $file->id,
            'file_type' => 'Auto-Dailer',
            'file_name' => $file->file_name,
            'operation_time' => now(),
        ]);

        return back();
    }

    
}
