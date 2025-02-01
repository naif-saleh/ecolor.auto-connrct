<?php

namespace App\Http\Controllers\AutoDailerByProvider;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\AutoDialerProvider;
use App\Models\AutoDailerFile;
use App\Models\AutoDialerData;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;


class ProviderFeedController extends Controller
{

    public function index()
    {
        $providers = AutoDialerProvider::all();
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
            'name' => 'required|string|max:255|unique:auto_dialer_providers,name',
            'extension' => 'nullable|string|max:50',
        ]);

        try {
            AutoDialerProvider::create([
                'name' => $request->name,
                'extension' => $request->extension,
                'user_id' => auth()->id(), // Ensure the user is logged in
            ]);

            return redirect('/providers')->with('success', 'Provider Created Successfully');
        } catch (\Exception $e) {
            return response()->json(['error' => 'Something went wrong: ' . $e->getMessage()], 500);
        }
    }


    public function createFile(AutoDialerProvider $provider)
    {
        $providers = AutoDialerProvider::find($provider);
        return view('autoDailerByProvider.ProviderFeed.create', compact('provider'));
    }


    public function storeFile(Request $request, AutoDialerProvider $provider)
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
        $file = AutoDailerFile::create([
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

            if ($this->isValidMobile($mobile)) {
                $batchData[] = [
                    'auto_dailer_file_id' => $fileId,
                    'mobile' => $mobile,
                    'provider_name' => $provider->name,
                    'extension' => $provider->extension,
                    'state' => 'new', // Default state
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            // Insert in batches to improve performance
            if (count($batchData) >= $batchSize) {
                AutoDialerData::insert($batchData);
                $batchData = []; // Reset batch
            }
        }

        // Insert remaining records
        if (!empty($batchData)) {
            AutoDialerData::insert($batchData);
        }
    }

    private function isValidMobile($mobile)
    {
        return preg_match('/^\+?[1-9]\d{7,14}$/', $mobile); // Supports international numbers
    }

    // Display all files for a provider
    public function files(AutoDialerProvider $provider)
    {
        $files = $provider->files; // Relationship defined in the provider model
        return view('autoDailerByProvider.ProviderFeed.feed', compact('provider', 'files'));
    }


    public function showFileContent($slug)
    {
        // Find the file by slug instead of using route model binding
        $file = AutoDailerFile::where('slug', $slug)->firstOrFail();

        // Get the file path
        $filePath = storage_path("app/provider_files/{$file->slug}");

        // Check if the file exists
        if (!Storage::exists("provider_files/{$file->slug}")) {
            return redirect()->back()->with('error', 'File not found.');
        }

        // Read the file contents
        $content = Storage::get("provider_files/{$file->slug}");

        // Convert CSV into an array
        $lines = explode("\n", $content);
        $data = array_map('str_getcsv', $lines);

        return view('autoDailerByProvider.ProviderFeed.show', compact('file', 'data'));
    }


}
