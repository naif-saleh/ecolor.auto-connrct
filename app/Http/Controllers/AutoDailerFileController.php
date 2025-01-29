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
                return back()->with(['wrong' => 'Unable to open the file for reading.']);
            }

            // Read the header row
            $header = fgetcsv($handle);
            if ($header === false || count($header) < 6) {
                fclose($handle);
                return back()->with([
                    'wrong' => 'Ensure that you entered all rows and followed the format in 📁 Auto Dailer - Demo 📁 file.',
                ]);
            }

            // Process the first data row to extract `from`, `to`, and `date`
            $firstDataRow = fgetcsv($handle);
            if ($firstDataRow === false || count($firstDataRow) < 6) {
                fclose($handle);
                return back()->with(['wrong' => 'Can not extract time-from , time-to and date']);
            }

            // Time Format Handling
            Log::info('Converting time fields...');
            try {
                // Possible time formats
                $timeFormats = [
                    'h:i:s A',   // 08:00:00 AM
                    'h:i A',     // 08:00 AM
                    'H:i:s',     // 08:00:00
                    'H:i',       // 08:00
                ];

                Log::info('Converting time fields...');
                $utcTime_from = null;
                $utcTime_to = null;

                // Attempt to parse `from` time
                foreach ($timeFormats as $format) {
                    try {
                        $utcTime_from = Carbon::createFromFormat($format, $firstDataRow[3])->format('H:i:s');
                        break; // Stop once a valid format is found
                    } catch (\Exception $e) {
                        // Continue trying the next format
                    }
                }

                // Attempt to parse `to` time
                foreach ($timeFormats as $format) {
                    try {
                        $utcTime_to = Carbon::createFromFormat($format, $firstDataRow[4])->format('H:i:s');
                        break; // Stop once a valid format is found
                    } catch (\Exception $e) {
                        // Continue trying the next format
                    }
                }

                if (!$utcTime_from || !$utcTime_to) {
                    return back()->with(
                        'wrong',
                        'Invalid time format in the file. Please try one of these time formats:{ 08:00:00 AM, 08:00 AM, 08:00:00, 08:00, 08:00AM, 8:00AM }'
                    );
                }
            } catch (\Exception $e) {
                Log::error('Time format conversion error: ' . $e->getMessage());
                fclose($handle);
            }


            // Date Format Handling
            try {
                // Possible date formats
                $dateFormats = [
                    'Y-m-d',       // 2025-01-19
                    'Y/m/d',       // 2025/01/19
                    'd/m/Y',       // 19/01/2025
                    'm/d/Y',       // 01/19/2025
                    'd-m-Y',       // 19-01-2025
                    'm-d-Y',       // 01-19-2025
                    'd.m.Y',       // 19.01.2025
                    'd M Y',       // 19 Jan 2025
                    'd F Y',       // 19 January 2025
                ];

                $formattedDate = null;

                foreach ($dateFormats as $format) {
                    try {
                        $formattedDate = Carbon::createFromFormat($format, $firstDataRow[5])->format('Y-m-d');
                        break; // Stop checking once a valid format is found
                    } catch (\Exception $e) {
                        // Continue trying the next format
                    }
                }

                if (!$formattedDate) {
                    // If no format matches, return the error message to the user
                    return back()->with(
                        'wrong',
                        'Invalid date format in the file. Please try one of these date formats:{ 2025-01-19, 2025/01/19, 19/01/2025, 01/19/2025, 19-01-2025, 01-19-2025, 19.01.2025, 19 Jan 2025, 19 January 2025 }'
                    );
                }
            } catch (\Exception $e) {
                Log::error('Date format conversion error: ' . $e->getMessage());
                fclose($handle);

                // Return a generic error message
                return back()->with(['wrong' => 'Failed to process the date. Please check the file.']);
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

            // Process the remaining rows (skip the header)
            $insertData = [];
            $seenMobiles = []; // Array to track seen mobile numbers

            // Add the first data row to the insert data
            $insertData[] = [
                'mobile' => $firstDataRow[0],
                'provider' => $firstDataRow[1],
                'extension' => $firstDataRow[2],
                'uploaded_by' => Auth::id(),
                'file_id' => $uploadedFile->id,
            ];

            // Process the rest of the rows
            while (($row = fgetcsv($handle)) !== false) {
                // Skip rows with insufficient columns
                if (count($row) < 3) {
                    Log::warning('Skipping row due to missing columns: ' . json_encode($row));
                    $uploadedFile->delete();
                    return back()->with(['wrong' => 'Ensure That all rows are entered']);
                }

                // Check if $row[0] contains only numbers
                if (!ctype_digit($row[0])) {
                    Log::warning('Skipping row due to non-numeric mobile number: ' . json_encode($row));
                    $uploadedFile->delete();
                    return back()->with(['wrong' => 'Mobile should only be numbers: ' . $row[0]]);
                }

                // Check for duplicate mobile number
                if (in_array($row[0], $seenMobiles)) {
                    Log::warning('Skipping row due to duplicate mobile number: ' . json_encode($row));
                    $uploadedFile->delete();
                    return back()->with(['wrong' => 'Mobile is duplicated: ' . $row[0]]);
                    continue; // Skip this row
                }

                // Add the mobile number to the seen array
                $seenMobiles[] = $row[0];

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
        $file = AutoDailerFile::where('slug', $slug);
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
