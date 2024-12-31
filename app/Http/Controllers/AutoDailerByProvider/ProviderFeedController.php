<?php

namespace App\Http\Controllers\AutoDailerByProvider;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\AutoDialerProvider;
use App\Models\AutoDailerProviderFeed;
use App\Models\AutoDailerFeedFile;
use Carbon\Carbon;

class ProviderFeedController extends Controller
{
    public function createFeed($id)
    {
        $provider = AutoDialerProvider::findOrFail($id);
        return view('autoDailerByProvider.ProviderFeed.create', compact('provider'));
    }

    // Store the feed data after submission
    public function storeFeed(Request $request, $id)
    {
        // Fetch the provider by its ID
        $provider = AutoDialerProvider::findOrFail($id);

        // Validate the form inputs (other than extension, which will come from provider)
        $request->validate([
            'from' => 'required|date_format:H:i',
            'to' => 'required|date_format:H:i',
            'date' => 'required|date',
            'on' => 'required|boolean',
            'csv_file' => 'required|file|mimes:csv,txt',
        ]);


        $localTime_form = Carbon::createFromFormat('H:i', $request->from, $request->timezone);
        $localTime_to = Carbon::createFromFormat('H:i', $request->to, $request->timezone);
        // Subtract the offset to align with UTC
        $offsetInHours = $localTime_form->offsetHours;
        $utcTime_from = $localTime_form->subHours($offsetInHours);
        $utcTime_to = $localTime_to->subHours($offsetInHours);

        // Format the UTC time to store in the database
        $formattedTime_from = $utcTime_from->format('H:i:s');
        $formattedTime_to = $utcTime_to->format('H:i:s');




        // Handle CSV upload and processing
        if ($request->hasFile('csv_file')) {
            $file = $request->file('csv_file');
            $csvData = array_map('str_getcsv', file($file->getRealPath()));

            // Create a FeedFile entry to store the metadata of the uploaded file
            $feedFile = AutoDailerFeedFile::create([
                'provider_id' => $provider->id,
                'file_name' => $file->getClientOriginalName(),
                'extension' => $provider->extension,
                'from' => $formattedTime_from,
                'to' => $formattedTime_to,
                'date' => $request->input('date'),
                'on' => $request->input('on'),
                'off' => $request->input('off'),
            ]);


            foreach ($csvData as $row) {
                AutoDailerProviderFeed::create([
                    'provider_id' => $provider->id,
                    'mobile' => $row[0],
                    'auto_dailer_feed_file_id' => $feedFile->id,
                ]);
            }
        }

        return redirect('/auto-dialer-providers')->with('success', 'Feed added successfully!');
    }




    // public function show($id)
    // {
    //     // Fetch the provider by its ID
    //     $provider = AutoDialerProvider::findOrFail($id);

    //     // Fetch all the feeds related to this provider
    //     $feeds = AutoDailerProviderFeed::where('provider_id', $id)->get();

    //     // Return the view with provider and feeds data
    //     return view('autoDailerByProvider.ProviderFeed.show', compact('provider', 'feeds'));
    // }
    public function show($id)
    {
        $feedFile = AutoDailerFeedFile::with('provider', 'feeds')->findOrFail($id);
        $feeds = AutoDailerProviderFeed::where('auto_dailer_feed_file_id', $id)->get();
        return view('autoDailerByProvider.ProviderFeed.show', compact('feedFile', 'feeds'));
    }


    public function showFeed($id)
    {
        // Fetch the feed by its ID
        $feed = AutoDailerProviderFeed::findOrFail($id);

        // Return view with feed data
        return view('autoDailerByProvider.ProviderFeed.feed', compact('feed'));
    }
}
