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
        // Validate file input
        $request->validate([
            'file' => 'required|mimes:csv,txt',
        ]);

        $file = $request->file('file');
        $fileName = time() . '_' . $file->getClientOriginalName();
        $file->storeAs('uploads', $fileName);

        $path = $file->getRealPath();

        try {
            // Open the file for reading
            if (($handle = fopen($path, 'r')) === false) {
                return back()->withErrors(['error' => 'Unable to open the file for reading.']);
            }

            // Read the header row
            $header = fgetcsv($handle);
            if ($header === false || count($header) < 6) {
                fclose($handle);
                return back()->withErrors(['error' => 'The file is missing required columns (from, to, date).']);
            }

            // Read the first data row to extract `from`, `to`, and `date`
            $firstDataRow = fgetcsv($handle);
            if ($firstDataRow === false || count($firstDataRow) < 6) {
                fclose($handle);
                return back()->withErrors(['error' => 'The file does not contain valid data rows.']);
            }

            // Convert time fields
            try {
                $utcTime_from = Carbon::createFromFormat('h:i:s A', $firstDataRow[3])->format('H:i:s');
                $utcTime_to = Carbon::createFromFormat('h:i:s A', $firstDataRow[4])->format('H:i:s');
                $formattedDate = Carbon::parse($firstDataRow[5])->format('Y-m-d');
            } catch (\Exception $e) {
                fclose($handle);
                return back()->withErrors(['error' => 'Invalid date or time format in the file.']);
            }

            // Create a SINGLE `AutoDailerFile` entry for this file upload
            $uploadedFile = AutoDailerFile::create([
                'file_name' => $fileName,
                'from' => $utcTime_from,
                'to' => $utcTime_to,
                'date' => $formattedDate,
                'uploaded_by' => Auth::id(),
            ]);

            Log::info('AutoDailerFile created with ID: ' . $uploadedFile->id);

            // Process the remaining rows
            $insertData = [];
            while (($row = fgetcsv($handle)) !== false) {
                // Skip rows with insufficient columns
                if (count($row) < 3) {
                    Log::warning('Skipping row due to missing columns: ' . json_encode($row));
                    continue;
                }

                // Add data to the batch insert array
                $insertData[] = [
                    'mobile' => $row[0],
                    'provider' => $row[1],
                    'extension' => $row[2],
                    'uploaded_by' => Auth::id(),
                    'file_id' => $uploadedFile->id,
                ];

                // Insert in batches of 1000 for memory efficiency
                if (count($insertData) >= 1000) {
                    AutoDailerUploadedData::insert($insertData);
                    Log::info('Inserted 1000 records successfully.');
                    $insertData = []; // Clear the batch array
                }
            }

            // Insert any remaining rows
            if (!empty($insertData)) {
                AutoDailerUploadedData::insert($insertData);
                Log::info('Inserted remaining ' . count($insertData) . ' records successfully.');
            }

            fclose($handle);

            return back()->with('success', 'File uploaded and processed successfully.');
        } catch (\Exception $e) {
            Log::error("Error processing file: " . $e->getMessage());
            Log::error("Error Details: " . $e->getTraceAsString());
            return back()->withErrors(['error' => 'There was an error processing the file.']);
        }
    }




    public function updateAutoDailer(Request $request, $slug)
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
        Log::info("Updating Auto Dialer File: slug=$slug, from=$from, to=$to, date=$date");
        Log::info($slug);

        // Now proceed with the update
        $file = AutoDailerFile::where('slug',$slug);
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
        // if (!Storage::exists('uploads/' . $file->file_name)) {
        //     abort(404, 'File not found.');
        // }

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
