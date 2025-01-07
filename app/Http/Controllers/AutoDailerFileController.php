<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\AutoDailerUploadedData;
use App\Models\AutoDailerFile;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class AutoDailerFileController extends Controller
{
    public function uploadCsv(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:csv,txt',
        ]);

        $file = $request->file('file');
        $fileName = time() . '_' . $file->getClientOriginalName();
        $file->storeAs('uploads', $fileName);

        $uploadedFile = AutoDailerFile::create([
            'file_name' => $fileName,
            'uploaded_by' => Auth::id(),
        ]);

        $path = $file->getRealPath();
        $data = array_map('str_getcsv', file($path));





        foreach ($data as $row) {
            // Debugging: Check what the row values look like
            // dd($row[3], $row[4]);

            // Assuming the times are in 24-hour format (H:i)

            $localTime_form = Carbon::createFromFormat('H:i A', $row[3], $request->timezone);
            $localTime_to = Carbon::createFromFormat('H:i A', $row[4], $request->timezone);


            // Subtract the offset to align with UTC
            $offsetInHours = $localTime_form->offsetHours;
            $utcTime_from = $localTime_form->subHours($offsetInHours);
            $utcTime_to = $localTime_to->subHours($offsetInHours);

            // Format the UTC time to store in the database (24-hour format)
            $formattedTime_from = $utcTime_from->format('H:i:s');
            $formattedTime_to = $utcTime_to->format('H:i:s');


            Log::info("Time From: ".$formattedTime_from." | Time To: ".$formattedTime_to);


            // Insert the data into the AutoDailerUploadedData table
            AutoDailerUploadedData::create([
                'mobile' => $row[0],
                'provider' => $row[1],
                'extension' => $row[2],
                'from' => $formattedTime_from,
                'to' => $formattedTime_to,
                'date' => $row[5],
                'uploaded_by' => Auth::id(),
                'file_id' => $uploadedFile->id,
            ]);
        }



        return back()->with('success', 'File uploaded and processed successfully');
    }

    public function updateAllowStatus(Request $request, $slug)
    {
        $file = AutoDailerFile::where('slug', $slug)->firstOrFail();

        // Handle the 'allow' checkbox as a boolean
        $file->allow = $request->has('allow') ? (bool) $request->allow : false; // Ensure that allow is properly set as a boolean
        $file->save();

        return redirect('/auto-dailer/files');
    }




    public function index()
    {
        $files = AutoDailerFile::paginate(20);
        return view('autodailers.index', compact('files'));
    }

    public function show($slug)
    {
        $file = AutoDailerFile::where('slug', $slug)->firstOrFail();

        // Check if the file exists
        if (!Storage::exists('uploads/' . $file->file_name)) {
            abort(404, 'File not found.');
        }

        // Get the file contents using the Storage facade
        $fileContents = Storage::get('uploads/' . $file->file_name);
        $data = array_map('str_getcsv', explode("\n", $fileContents));

        // Fetch the uploaded data with pagination
        $uploadedData = AutoDailerUploadedData::where('file_id', $file->id)->paginate(500);  // Adjust number of items per page

        return view('autodailers.show', compact('data', 'file', 'uploadedData'));
    }


    public function deleteFile($slug)
    {
        $file = AutoDailerFile::where('slug', $slug)->firstOrFail();

        // Optionally delete the file from storage
        Storage::delete('uploads/' . $file->file_name);

        // Delete the record from the database
        $file->delete();

        return redirect()->route('autodailers.files.index')->with('success', 'File deleted successfully.');
    }


    public function downloadExampleCsv()
    {
        $filePath = 'app/private/uploads/example.csv';

        if (Storage::exists($filePath)) {
            return Storage::download($filePath, 'example.csv');
        } else {
            abort(404, 'File not found');
        }
    }

}
