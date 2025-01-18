<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\AutoDailerUploadedData;
use App\Models\AutoDailerFile;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use App\Models\ActivityLog;
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

        // Active Log Report
        ActivityLog::create([
            'user_id' => Auth::id(),
            'operation' => 'import File',
            'file_id' => $uploadedFile->id,
            'file_type' => 'Auto-Dailer',
            'file_name' => $uploadedFile->file_name,
            'operation_time' => now(),
        ]);

        $path = $file->getRealPath();
        $fileContents = file_get_contents($path);
        // Normalize line endings to UNIX format
        $fileContents = str_replace("\r\n", "\n", $fileContents);

        // Convert the file contents to an array of lines
        $lines = explode("\n", $fileContents);
        $data = array_map('str_getcsv', $lines);

        foreach ($data as $row) {
            try {
                // Trim all fields to avoid issues with extra spaces
                $row = array_map('trim', $row);

                // Convert time fields
                $localTime_form = Carbon::createFromFormat('h:i:s A', $row[3], $request->timezone);
                $localTime_to = Carbon::createFromFormat('h:i:s A', $row[4], $request->timezone);

                // Subtract the offset to align with UTC
                $offsetInHours = $localTime_form->offsetHours;
                $utcTime_from = $localTime_form->subHours($offsetInHours);
                $utcTime_to = $localTime_to->subHours($offsetInHours);

                // Format the UTC time to store in the database
                $formattedTime_from = $utcTime_from->format('H:i:s');
                $formattedTime_to = $utcTime_to->format('H:i:s');

                // Convert date field to Y-m-d format
                $formattedDate = Carbon::parse($row[5])->format('Y-m-d');

                Log::info("Time From: " . $formattedTime_from . " | Time To: " . $formattedTime_to);

                $csv_file =  AutoDailerUploadedData::create([
                    'mobile' => $row[0],
                    'provider' => $row[1],
                    'extension' => $row[2],
                    'from' => $formattedTime_from,
                    'to' => $formattedTime_to,
                    'date' => $formattedDate,
                    'uploaded_by' => Auth::id(),
                    'file_id' => $uploadedFile->id,
                ]);

                $csv_file->save();
            } catch (\Exception $e) {
                Log::error('Error processing row: ' . $e->getMessage());
                return back()->withErrors(['error' => 'There was an error processing the file.']);
            }
        }

        return back()->with('success', 'File uploaded and processed successfully');
    }

    // public function edit($slug)
    // {
    //     $file = AutoDailerFile::where('slug', $slug)->firstOrFail();
    //     return view('autodailers.edit', compact('file'));
    // }


    public function updateAutoDailer(Request $request, $id)
    {
        $request->validate([
            'from' => 'required',
            'to' => 'required',
            'date' => 'required',
        ]);

        // Get the update values once
        $updateData = [
            'from' => $request->from,
            'to' => $request->to,
            'date' => $request->date,
        ];

        // Process the update in smaller chunks (5000 records per batch)
        AutoDailerUploadedData::where('file_id', $id)->chunkById(5000, function ($records) use ($updateData) {
            // Get the IDs of the records in the chunk
            $ids = $records->pluck('id')->toArray();

            // Bulk update all records in the current chunk
            AutoDailerUploadedData::whereIn('id', $ids)->update($updateData);
        });

        return redirect()->back()->with('success', 'Time and Date update is processing in batches.');
    }





    // public function uploadCsv(Request $request)
    // {
    //     $request->validate([
    //         'file' => 'required|mimes:csv,txt',
    //     ]);

    //     $file = $request->file('file');
    //     $fileName = time() . '_' . $file->getClientOriginalName();
    //     $file->storeAs('uploads', $fileName);

    //     $uploadedFile = AutoDailerFile::create([
    //         'file_name' => $fileName,
    //         'uploaded_by' => Auth::id(),
    //     ]);

    //     // Active Log Report
    //     ActivityLog::create([
    //         'user_id' => Auth::id(),
    //         'operation' => 'import File',
    //         'file_id' => $uploadedFile->id,
    //         'file_type' => 'Auto-Dailer',
    //         'file_name' => $uploadedFile->file_name,
    //         'operation_time' => now(),
    //     ]);

    //     $path = $file->getRealPath();
    //     $data = array_map('str_getcsv', file($path));

    //     foreach ($data as $row) {
    //         try {
    //             // Convert time fields, now including seconds in the format
    //             $localTime_form = Carbon::createFromFormat('h:i:s A', $row[3], $request->timezone);
    //             $localTime_to = Carbon::createFromFormat('h:i:s A', $row[4], $request->timezone);

    //             // Subtract the offset to align with UTC
    //             $offsetInHours = $localTime_form->offsetHours;
    //             $utcTime_from = $localTime_form->subHours($offsetInHours);
    //             $utcTime_to = $localTime_to->subHours($offsetInHours);

    //             // Format the UTC time to store in the database
    //             $formattedTime_from = $utcTime_from->format('H:i:s');
    //             $formattedTime_to = $utcTime_to->format('H:i:s');

    //             // Convert date field to Y-m-d format
    //             $formattedDate = Carbon::parse($row[5])->format('Y-m-d');

    //             // Insert the data into the AutoDailerUploadedData table
    //             AutoDailerUploadedData::create([
    //                 'mobile' => $row[0],
    //                 'provider' => $row[1],
    //                 'extension' => $row[2],
    //                 'from' => $formattedTime_from,
    //                 'to' => $formattedTime_to,
    //                 'date' => $formattedDate,
    //                 'uploaded_by' => Auth::id(),
    //                 'file_id' => $uploadedFile->id,
    //             ]);
    //         } catch (\Exception $e) {
    //             return back()->withErrors(['error' => 'Error processing row: ' . $e->getMessage()]);
    //         }
    //     }

    //     return back()->with('success', 'File uploaded and processed successfully');
    // }



    public function updateAllowStatus(Request $request, $slug)
    {
        $file = AutoDailerFile::where('slug', $slug)->firstOrFail();

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

        // Active Log Report...............................
        ActivityLog::create([
            'user_id' => Auth::id(),
            'operation' => 'delete',
            'file_id' => $file->id,
            'file_type' => 'Auto-Dailer',
            'file_name' => $file->file_name,
            'operation_time' => now(),
        ]);
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


    public function downloadUploadedFile($fileId)
    {
        $uploadedFile = AutoDailerFile::findOrFail($fileId);

        // Join AutoDailerUploadedData with users to get the uploader's name
        $data = AutoDailerUploadedData::where('file_id', $fileId)
            ->join('users', 'users.id', '=', 'auto_dailer_uploaded_data.uploaded_by')
            ->select('auto_dailer_uploaded_data.*', 'users.name as uploader_name')
            ->get();

        $fileName = 'uploaded_data_' . $uploadedFile->file_name . '.csv';
        $headers = [
            "Content-type" => "text/csv",
            "Content-Disposition" => "attachment; filename=$fileName",
            "Pragma" => "no-cache",
            "Cache-Control" => "must-revalidate, post-check=0, pre-check=0",
            "Expires" => "0"
        ];

        $columns = ['Mobile', 'Provider', 'Extension', 'From', 'To', 'Date', 'Uploader Name'];

        $callback = function () use ($data, $columns) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);

            foreach ($data as $row) {
                fputcsv($file, [
                    $row->mobile,
                    $row->provider,
                    $row->extension,
                    $row->from,
                    $row->to,
                    $row->date,
                    $row->uploader_name // Add the uploader's name
                ]);
            }

            fclose($file);
        };

        // Active Log Report...............................
        ActivityLog::create([
            'user_id' => Auth::id(),
            'operation' => 'Download',
            'file_id' => $fileId,
            'file_type' => 'Auto-Dailer',
            'file_name' => $fileName,
            'operation_time' => now(),
        ]);

        return response()->stream($callback, 200, $headers);
    }
}
