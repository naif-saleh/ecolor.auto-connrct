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
            'allow' => true,
            'from' => $request->from,
            'to' => $request->to,
            'date' => $request->date,
            'uploaded_by' => auth()->id(),
            'provider_id' => $provider->id,
        ]);

        // Process CSV file for mobile numbers
        $this->processCsvFile($filePath, $provider, $file->id);

        return redirect()->route('provider.ProviderFeed.feed', $provider)->with('success', 'File added and data imported successfully!');
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
                    'state' => 'pending', // Default state
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
        return view('providers.files.index', compact('provider', 'files'));
    }
























    // public function createFeed($id)
    // {
    //     $provider = AutoDialerProvider::findOrFail($id);
    //     return view('autoDailerByProvider.ProviderFeed.create', compact('provider'));
    // }

    // // Store the feed data after submission
    // public function storeFeed(Request $request, $id)
    // {
    //     // Fetch the provider by its ID
    //     $provider = AutoDialerProvider::findOrFail($id);

    //     // Validate the form inputs (other than extension, which will come from provider)
    //     $request->validate([
    //         'from' => 'required|date_format:H:i',
    //         'to' => 'required|date_format:H:i',
    //         'date' => 'required|date',
    //         'on' => 'required|boolean',
    //         'csv_file' => 'required|file|mimes:csv,txt',
    //     ]);


    //     $localTime_form = Carbon::createFromFormat('H:i', $request->from, $request->timezone);
    //     $localTime_to = Carbon::createFromFormat('H:i', $request->to, $request->timezone);
    //     // Subtract the offset to align with UTC
    //     $offsetInHours = $localTime_form->offsetHours;
    //     $utcTime_from = $localTime_form->subHours($offsetInHours);
    //     $utcTime_to = $localTime_to->subHours($offsetInHours);

    //     // Format the UTC time to store in the database
    //     $formattedTime_from = $utcTime_from->format('H:i:s');
    //     $formattedTime_to = $utcTime_to->format('H:i:s');




    //     // Handle CSV upload and processing
    //     if ($request->hasFile('csv_file')) {
    //         $file = $request->file('csv_file');
    //         $csvData = array_map('str_getcsv', file($file->getRealPath()));

    //         // Create a FeedFile entry to store the metadata of the uploaded file
    //         $feedFile = AutoDailerFeedFile::create([
    //             'provider_id' => $provider->id,
    //             'file_name' => $file->getClientOriginalName(),
    //             'extension' => $provider->extension,
    //             'from' => $formattedTime_from,
    //             'to' => $formattedTime_to,
    //             'date' => $request->input('date'),
    //             'on' => $request->input('on'),
    //             'off' => $request->input('off'),
    //         ]);


    //         foreach ($csvData as $row) {
    //             AutoDailerProviderFeed::create([
    //                 'provider_id' => $provider->id,
    //                 'mobile' => $row[0],
    //                 'auto_dailer_feed_file_id' => $feedFile->id,
    //             ]);
    //         }
    //     }

    //     return redirect('/auto-dialer-providers')->with('success', 'Feed added successfully!');
    // }




    // // public function show($id)
    // // {
    // //     // Fetch the provider by its ID
    // //     $provider = AutoDialerProvider::findOrFail($id);

    // //     // Fetch all the feeds related to this provider
    // //     $feeds = AutoDailerProviderFeed::where('provider_id', $id)->get();

    // //     // Return the view with provider and feeds data
    // //     return view('autoDailerByProvider.ProviderFeed.show', compact('provider', 'feeds'));
    // // }
    // public function show($id)
    // {
    //     $feedFile = AutoDailerFeedFile::with('provider', 'feeds')->findOrFail($id);
    //     $feeds = AutoDailerProviderFeed::where('auto_dailer_feed_file_id', $id)->get();
    //     return view('autoDailerByProvider.ProviderFeed.show', compact('feedFile', 'feeds'));
    // }


    // public function showFeed($id)
    // {
    //     // Fetch the feed by its ID
    //     $feed = AutoDailerProviderFeed::findOrFail($id);

    //     // Return view with feed data
    //     return view('autoDailerByProvider.ProviderFeed.feed', compact('feed'));
    // }
}
