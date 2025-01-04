<?php

namespace App\Http\Controllers\AutoDistributerByUser;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\AutoDistributerExtensionFeed;
use App\Models\AutoDistributerFeedFile;
use App\Models\AutoDistributererExtension;
use Carbon\Carbon;


class UserFeedController extends Controller
{
    public function show($id)
    {
        $extension = AutoDistributererExtension::with('feedFiles', 'extensionFeeds')->findOrFail($id);
        return view('autoDistributerByUser.User.show', compact('extension'));
    }

    public function viewFeedData($extensionId, $feedFileId)
    {

        $extension = AutoDistributererExtension::findOrFail($extensionId);
        $feedFile = AutoDistributerFeedFile::findOrFail($feedFileId);
        $extensionFeeds = AutoDistributerExtensionFeed::where('auto_dist_feed_file_id', $feedFileId)->get();

        return view('autoDistributerByUser.UserFeed.show', compact('extension', 'feedFile', 'extensionFeeds'));
    }

    public function createFeed($id)
    {
        $extension = AutoDistributererExtension::findOrFail($id);
        return view('autoDistributerByUser.UserFeed.create', compact('extension'));
    }

    // Handle the CSV file upload and processing
    public function store(Request $request, $id)
    {
        $extension = AutoDistributererExtension::findOrFail($id);

        // Validate the incoming request
        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt|max:10240',
            'from' => 'required|date_format:H:i',
            'to' => 'required|date_format:H:i',
            'date' => 'required|date',
            'on' => 'required|boolean',
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

        // Handle the CSV upload
        $file = $request->file('csv_file');
        $csvData = array_map('str_getcsv', file($file->getRealPath()));

        // Create FeedFile metadata entry
        $feedFile = AutoDistributerFeedFile::create([
            'user_ext_id' => $extension->id,
            'extension' => $extension->extension,
            'userStatus' => $extension->userStatus,
            "three_cx_user_id" => $extension->three_cx_user_id,
            'from' => $formattedTime_from,
            'to' => $formattedTime_to,
            'date' => $request->input('date'),
            'on' => $request->input('on'),
            'file_name' => $file->getClientOriginalName(),
        ]);

        // Process CSV rows
        foreach ($csvData as $row) {
            $mobileNumber = $row[0];  // Assuming the mobile number is in the first column of the CSV

            AutoDistributerExtensionFeed::create([
                'user_ext_id' => $extension->id,
                'mobile' => $mobileNumber,
                'state' => 'new',  // Default state
                'auto_dist_feed_file_id' => $feedFile->id,
            ]);
        }

        return redirect()->route('auto_distributerer_extensions.show', $id)
            ->with('success', 'CSV file uploaded and processed successfully.');
    }
}
