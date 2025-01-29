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
            // Open the file
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


            $firstDataRow = fgetcsv($handle);
            if ($firstDataRow === false || count($firstDataRow) < 6) {
                fclose($handle);
                return back()->with(['wrong' => 'Cannot extract time-from, time-to, and date.']);
            }

            // Time Format Handling
            $timeFormats = ['h:i:s A', 'h:i A', 'H:i:s', 'H:i'];
            $utcTime_from = null;
            $utcTime_to = null;

            foreach ($timeFormats as $format) {
                try {
                    $utcTime_from = Carbon::createFromFormat($format, $firstDataRow[3])->format('H:i:s');
                    break;
                } catch (\Exception $e) {
                }
            }

            foreach ($timeFormats as $format) {
                try {
                    $utcTime_to = Carbon::createFromFormat($format, $firstDataRow[4])->format('H:i:s');
                    break;
                } catch (\Exception $e) {
                }
            }

            if (!$utcTime_from || !$utcTime_to) {
                return back()->with('wrong', 'Invalid time format in the file. Please use one of these: { 08:00:00 AM, 08:00 AM, 08:00:00, 08:00, 08:00AM, 8:00AM }');
            }

            // Date Format Handling
            $dateFormats = ['Y-m-d', 'Y/m/d', 'd/m/Y', 'm/d/Y', 'd-m-Y', 'm-d-Y', 'd.m.Y', 'd M Y', 'd F Y'];
            $formattedDate = null;

            foreach ($dateFormats as $format) {
                try {
                    $formattedDate = Carbon::createFromFormat($format, $firstDataRow[5])->format('Y-m-d');
                    break;
                } catch (\Exception $e) {
                }
            }


            $uploadedFile = AutoDailerFile::create([
                'file_name' => $fileName,
                'from' => $utcTime_from,
                'to' => $utcTime_to,
                'date' => $formattedDate,
                'uploaded_by' => Auth::id(),
            ]);

            // Process the first data row manually
            $insertData = [];
            $seenMobiles = [];
            $duplicateMobiles = [];

            // Add the first data row to the insert array
            $insertData[] = [
                'mobile' => $firstDataRow[0],
                'provider' => $firstDataRow[1],
                'extension' => $firstDataRow[2],
                'uploaded_by' => Auth::id(),
                'file_id' => $uploadedFile->id,
            ];
            $seenMobiles[$firstDataRow[0]] = true;


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
                if (count($row) < 6) {
                    Log::warning('Skipping row due to missing columns: ' . json_encode($row));
                    $uploadedFile->delete();
                    return back()->with(['wrong' => 'Ensure that all rows are entered.']);
                }

                // Validate mobile number format
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

                $seenMobiles[$row[0]] = true;

                // Add to batch insert array
                $insertData[] = [
                    'mobile' => $row[0],
                    'provider' => $row[1],
                    'extension' => $row[2],
                    'uploaded_by' => Auth::id(),
                    'file_id' => $uploadedFile->id,
                ];

                // Insert in batches of 1000
                if (count($insertData) >= 1000) {
                    AutoDailerUploadedData::insert($insertData);
                    Log::info('Inserted 1000 records successfully.');
                    $insertData = [];
                }
            }


            if (!empty($insertData)) {
                AutoDailerUploadedData::insert($insertData);
                Log::info('Inserted remaining ' . count($insertData) . ' records successfully.');
            }

            fclose($handle);

            // If there are duplicate numbers, return them as an error message
            if (!empty($duplicateMobiles)) {
                $uploadedFile->delete();
                return back()->with(['wrong' => 'Duplicate mobile number /s found: ' . implode(', ', $duplicateMobiles)]);
            }

            return back()->with('success', 'File uploaded and processed successfully.');
        } catch (\Exception $e) {
            Log::error("Error processing file: " . $e->getMessage());
            Log::error("Error Details: " . $e->getTraceAsString());
            return back()->withErrors(['error' => 'There was an error processing the file.']);
        }
    }






    public function updateAutoDailer(Request $request, $slug)

    {


        $request->validate([
            'file_name' => 'required',
            'from' => 'required',
            'to' => 'required',
            'date' => 'required',
        ]);

        // Find the file by slug


        // Update all records for the given file ID
        AutoDailerFile::where('id', $id)->update([
            'file_name' => $request->file_name,
            'from' => $request->from,
            'to' => $request->to,
            'date' => $request->date,
        ]);

        return redirect()->back()->with('success', 'Time and Date updated successfully for all records.');
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
