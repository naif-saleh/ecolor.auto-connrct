<?php

namespace App\Http\Controllers\AutoDistributerByUser;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\AutoDistributerExtensionFeed;
use App\Models\AutoDistributerFeedFile;
use App\Models\AutoDistributererExtension;
use Carbon\Carbon;
use App\Models\AutoDailerProviderFeed;

class UserFeedController extends Controller
{
    public function createFeed($id)
    {
        $provider = AutoDistributererExtension::findOrFail($id);
        return view('autoDistributerByUser.UserFeed.create', compact('provider'));
    }


    public function storeFeed(Request $request, $id)
    {

        $provider = AutoDistributererExtension::findOrFail($id);

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
            $feedFile = AutoDistributerFeedFile::create([
                'user_ext_id' => $provider->id,
                'file_name' => $file->getClientOriginalName(),
                'extension' => $provider->extension,
                'from' => $formattedTime_from,
                'to' => $formattedTime_to,
                'date' => $request->input('date'),
                'on' => $request->input('on'),
                'off' => $request->input('off'),

            ]);


            foreach ($csvData as $row) {
                AutoDistributerExtensionFeed::create([
                    'user_ext_id' => $provider->id,
                    'mobile' => $row[0],
                    'user_ext_id' => $feedFile->id,
                ]);
            }
        }

        return redirect()->route('autoDistributers.index')->with('success', 'Feed added successfully!');
    }


    public function show($id)
    {
        $feedFile = AutoDistributerFeedFile::with('user_ext', 'feeds')->findOrFail($id);
        $feeds = AutoDistributerExtensionFeed::where('auto_dist_feed_file_id', $id)->get();
        return view('autoDistributerByUser.UserFeed.show', compact('feedFile', 'feeds'));
    }


    public function showFeed($id)
    {
        dd('ds');
        // Fetch the feed by its ID
        $feed = AutoDistributerExtensionFeed::findOrFail($id);

        // Return view with feed data
        return view('autoDistributerByUser.UserFeed.show', compact('feed'));
    }
}
