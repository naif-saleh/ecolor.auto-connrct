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
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ADialProviderFeedController extends Controller
{

    //Auto Dialer Index
    public function index()
    {
        $providers = ADialProvider::paginate(10);
        return view('autoDailerByProvider.Provider.index', compact('providers'));
    }

    //Create Provider
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


    //Update Provider
    public function updateProvider(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:a_dial_providers,name,' . $id,
            'extension' => 'nullable|string|max:50',
        ]);

        try {
            $provider = ADialProvider::findOrFail($id);
            $provider->update([
                'name' => $request->name,
                'extension' => $request->extension,
            ]);

            return response()->json(['success' => 'Provider Updated Successfully']);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Something went wrong: ' . $e->getMessage()], 500);
        }
    }



    //Create File For a Provider
    public function createFile(ADialProvider $provider)
    {
        $providers = ADialProvider::find($provider);
        return view('autoDailerByProvider.ProviderFeed.create', compact('provider'));
    }


    //Store New File For a Provider
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

    //Proccessing CSV File Data
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
                // If mobile is valid, add to batch data
                $batchData[] = [
                    'feed_id' => $fileId,
                    'mobile' => $mobile,
                    'state' => 'new', // Default state
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            } else {
                // Log invalid number and continue to the next one
                Log::info('This mobile not valid ' . $mobile);
                continue; // Skip invalid mobile and move to the next line
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

    //Validate If Mobile Number is_KSA
    private function isValidMobile($mobile)
    {
        return preg_match('/^9665[0-9]{8}$/', $mobile); // Validates Saudi mobile numbers
    }

    // Display all files for a provider
    public function files(ADialProvider $provider)
    {
        $files = $provider->files()->orderBy('date', 'desc')->paginate(5); // Change 'created_at' to the correct column if needed
        // Relationship defined in the provider model
        return view('autoDailerByProvider.ProviderFeed.feed', compact('provider', 'files'));
    }


    //Show Data Inside File
    public function showFileContent($slug)
    {
        // Find the file by slug instead of using route model binding
        $file = ADialFeed::where('slug', $slug)->firstOrFail();
        $numbers = ADialData::where('feed_id', $file->id)->count();
        $data = ADialData::where('feed_id', $file->id)->paginate(400);

        return view('autoDailerByProvider.ProviderFeed.show', compact('file', 'data', 'numbers'));
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

        $file = ADialFeed::where('slug', $slug)->firstOrFail();

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
            $file = ADialFeed::where('slug', $slug)->firstOrFail();
            $file->delete();

            return back()->with('success', 'File deleted successfully');
        } catch (\Exception $e) {
            return response()->json(['message' => 'There was an error deleting the file', 'error' => $e->getMessage()], 500);
        }
    }



    // Delete Provider with All Files
    public function destroyProvider($id)
    {
        $provider = ADialProvider::where('id', $id)->first();
        $provider->delete();
        return back()->with('success', 'Provider Deleted Successfully');
    }

    // Active and Inactine File
    public function updateAllowStatus(Request $request, $slug)
    {
        $file = ADialFeed::where('slug', $slug)->firstOrFail();

        // Handle the 'allow' checkbox as a boolean
        $file->allow = $request->has('allow') ? (bool) $request->allow : false; // Ensure that allow is properly set as a boolean
        $file->save();
        Log::info('done');

        // Active Log Report...............................
        ActivityLog::create([
            'user_id' => Auth::id(),
            'operation' => $file->allow ? 'Active' : "Inactive",
            'file_id' => $file->id,
            'file_type' => 'Auto-Dailer',
            'file_name' => $file->file_name,
            'operation_time' => now(),
        ]);

        if ($file->allow === false) {
            return back()->with('inactive', 'File is Disactivited ⚠️');
        }
        return back()->with('active', 'File is Activited ✅');
    }

    public function importCsvData(Request $request)
    {
        $errors = [];
        $successCount = 0;
        $batchSize = 1000; // Reduced batch size for efficiency
        $dataBatch = [];
        $providerCache = [];
        $feedCache = [];

        if (($handle = fopen($request->file, 'r')) !== false) {
            $header = fgetcsv($handle);

            if (!$header) {
                return response()->json(['errors' => ["Failed to read the CSV header."]], 422);
            }

            DB::disableQueryLog();
            Log::info('CSV Import Started', ['file' => $request->file->getClientOriginalName()]);

            return response()->stream(function () use ($handle, &$successCount, &$errors, &$dataBatch, $batchSize, &$providerCache, &$feedCache) {
                echo '{"message": "Processing started", "records": [';
                $firstRecord = true;

                while (($data = fgetcsv($handle)) !== false) {
                    if ($data === false) continue;

                    list($mobile, $name, $extension, $from, $to, $date) = $data;

                    // Cache provider lookup (avoid duplicate queries)
                    $providerKey = $name . '-' . $extension;
                    if (!isset($providerCache[$providerKey])) {
                        $providerCache[$providerKey] = ADialProvider::firstOrCreate(
                            ['name' => $name, 'extension' => $extension],
                            ['user_id' => auth()->id()]
                        );
                    }
                    $provider = $providerCache[$providerKey];

                    if (!$provider) {
                        $errors[] = "Failed to find or create provider for extension: $extension";
                        continue;
                    }

                    // Cache feed lookup (avoid duplicate queries)
                    $feedKey = $provider->id . '-' . $from . '-' . $to . '-' . $date;
                    if (!isset($feedCache[$feedKey])) {
                        $feedCache[$feedKey] = ADialFeed::firstOrCreate(
                            [
                                'provider_id' => $provider->id,
                                'from' => Carbon::createFromFormat('h:i A', $from)->format('H:i:s'),
                                'to' => Carbon::createFromFormat('h:i A', $to)->format('H:i:s'),
                                'date' => Carbon::parse($date)->format('Y-m-d'),
                            ],
                            [
                                'file_name' => $name,
                                'slug' => Str::uuid(),
                                'uploaded_by' => auth()->id()
                            ]
                        );
                    }
                    $feed = $feedCache[$feedKey];

                    // Skip duplicates directly without extra queries
                    $dataBatch[] = [
                        'feed_id' => $feed->id,
                        'mobile' => $mobile,
                        'state' => 'new',
                        'created_at' => now(),
                        'updated_at' => now()
                    ];

                    $successCount++;

                    if (!$firstRecord) echo ",";
                    echo json_encode(["mobile" => $mobile, "status" => "imported"]);
                    flush();
                    $firstRecord = false;

                    // Batch insert every $batchSize records
                    if (count($dataBatch) >= $batchSize) {
                        DB::transaction(function () use (&$dataBatch) {
                            ADialData::insert($dataBatch);
                        });
                        $dataBatch = []; // Clear batch
                    }
                }

                // Insert any remaining records
                if (!empty($dataBatch)) {
                    DB::transaction(function () use (&$dataBatch) {
                        ADialData::insert($dataBatch);
                    });
                }

                fclose($handle);

                echo '], "summary": {"success": ' . $successCount . ', "errors": ' . json_encode($errors) . '}}';
                flush();

                Log::info('CSV Import Completed Successfully', ['file' => request()->file->getClientOriginalName()]);
            }, 200, ['Content-Type' => 'application/json']);
        }

        return response()->json(['errors' => ["Failed to open CSV file."]], 422);
    }
}
