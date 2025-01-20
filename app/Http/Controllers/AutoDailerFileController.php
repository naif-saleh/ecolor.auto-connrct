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

        $path = $file->getRealPath();
        $fileContents = file_get_contents($path);
        $fileContents = str_replace("\r\n", "\n", $fileContents); // Normalize line endings

        $lines = explode("\n", $fileContents);
        $data = array_map('str_getcsv', $lines);

        if (count($data) < 2) {
            return back()->withErrors(['error' => 'The file appears to be empty or has no valid rows.']);
        }

        try {
            // Extract `from`, `to`, and `date` from the SECOND row (first data row)
            $firstDataRow = $data[1]; // Assuming row 0 is headers, row 1 is first data row

            if (count($firstDataRow) < 6) {
                return back()->withErrors(['error' => 'Missing required columns (from, to, date).']);
            }

            // Convert time fields
            $utcTime_from = Carbon::createFromFormat('h:i:s A', $firstDataRow[3])->format('H:i:s');
            $utcTime_to = Carbon::createFromFormat('h:i:s A', $firstDataRow[4])->format('H:i:s');
            $formattedDate = Carbon::parse($firstDataRow[5])->format('Y-m-d');

            // Create a SINGLE `AutoDailerFile` entry for this file upload
            $uploadedFile = AutoDailerFile::create([
                'file_name' => $fileName,
                'from' => $utcTime_from,
                'to' => $utcTime_to,
                'date' => $formattedDate,
                'uploaded_by' => Auth::id(),
            ]);

            Log::info('AutoDailerFile created with ID: ' . $uploadedFile->id);

            // Process ALL rows (skipping the header)
            foreach ($data as $index => $row) {
                if ($index == 0) continue; // Skip header row

                if (count($row) < 3) {
                    Log::warning('Skipping row due to missing columns: ' . json_encode($row));
                    continue;
                }

                AutoDailerUploadedData::create([
                    'mobile' => $row[0],
                    'provider' => $row[1],
                    'extension' => $row[2],
                    'uploaded_by' => Auth::id(),
                    'file_id' => $uploadedFile->id,
                ]);

                Log::info('Uploaded data for mobile: ' . $row[0] . ', extension: ' . $row[2]);
            }

            return back()->with('success', 'File uploaded and processed successfully.');
        } catch (\Exception $e) {
            Log::error("Error processing file: " . $e->getMessage());
            Log::error("Error Details: " . $e->getTraceAsString());
            return back()->withErrors(['error' => 'There was an error processing the file.']);
        }
    }



    // public function providers()
    // {
    //     // Retrieve all uploaded data with the associated file information
    //     $uploadedData = AutoDailerUploadedData::with('file')->get();


    //     return view('autodailers.providers', compact('uploadedData'));
    // }




    public function updateAutoDailer(Request $request, $id)
    {
        // Validate the request inputs
        $request->validate([
            'file_name' => 'required',
            'from' => 'required|date_format:H:i',
            'to' => 'required|date_format:H:i',
            'date' => 'required|date_format:Y-m-d',
        ]);

        // Check the input data before updating
        $file_name = $request->input('file_name');
        $from = $request->input('from');
        $to = $request->input('to');
        $date = $request->input('date');

        // Debugging: Log the input values to see what you're getting
        Log::info("Updating Auto Dialer File: ID=$id, from=$from, to=$to, date=$date");
        Log::info($id);

        // Now proceed with the update
        $file = AutoDailerFile::find($id);
        if ($file) {
            $file->update([
                'file_name' => $file_name,
                'from' => $from,
                'to' => $to,
                'date' => $date,
            ]);
        }

        return redirect()->back()->with('success', 'Time and Date updated successfully.');
    }







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
                    $row->file->from,
                    $row->file->to,
                    $row->file->date,
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
