<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\AutoDistributorUploadedData;
use App\Models\AutoDistributorFile;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use App\Models\ActivityLog;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use App\Models\TrheeCxUserStatus;
use Illuminate\Support\Facades\Http;
use App\Services\TokenService;
use Illuminate\Support\Facades\DB;




class AutoDistributorFileController extends Controller
{
    protected $tokenService;

    public function __construct(TokenService $tokenService)
    {

        $this->tokenService = $tokenService;
    }



    public function index()
    {
        $token = $this->tokenService->getToken();
        $files = AutoDistributorFile::paginate(20);
        $threeCxUsers = TrheeCxUserStatus::all();
        $responseState = Http::withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->get(config('services.three_cx.api_url') . "/xapi/v1/Users");

        if ($responseState->successful()) {
            $data = $responseState->json();
            $threeCxLiveUsers = $data['value'] ?? []; // Extract 'value' safely
        } else {
            $threeCxLiveUsers = []; // Default empty array if request fails
        }

        // Log::info('Live Users: ' . print_r($threeCxLiveUsers, true));
        // $numbersCount =  AutoDistributorUploadedData::where('file_id', $files->file->id)->where('state', 'new')->count();
        return view('autodisributers.index', compact('files', 'threeCxUsers', 'threeCxLiveUsers'));
    }



    public function importAllUsers()
    {
        $token = $this->tokenService->getToken();
        Log::info('Token retrieved successfully.');

        try {
            Log::info('Sending request to fetch users from the API.');
            $responseState = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token,
            ])->get(config('services.three_cx.api_url') . "/xapi/v1/Users");

            Log::info('Request sent, awaiting response.');

            if ($responseState->successful()) {
                Log::info('API response received successfully.');

                $responseData = $responseState->json();
                Log::info('Response data decoded.', ['responseData' => $responseData]);

                if (isset($responseData['value']) && is_array($responseData['value'])) {
                    $apiUserIds = [];
                    Log::info('Processing user data.');

                    foreach ($responseData['value'] as $data) {
                        $userId = $data['Id'] ?? null;
                        $apiUserIds[] = $userId;

                        Log::info('Updating or creating user.', ['userId' => $userId, 'userData' => $data]);

                        TrheeCxUserStatus::updateOrCreate(
                            ['user_id' => $userId],
                            [
                                "firstName" => $data['FirstName'] ?? null,
                                "lastName" => $data['LastName'] ?? null,
                                "displayName" => $data['DisplayName'] ?? null,
                                "email" => $data['EmailAddress'] ?? null,
                                "isRegistred" => $data['IsRegistered'] ?? null,
                                "QueueStatus" => $data['QueueStatus'] ?? null,
                                "extension" => $data['Number'] ?? null,
                                "status" => $data['CurrentProfileName'] ?? null,
                            ]
                        );
                    }

                    Log::info('Finished processing users, deleting users not in the API response.');
                    TrheeCxUserStatus::whereNotIn('user_id', $apiUserIds)->delete();
                    Log::info('Unused users deleted successfully.');
                } else {
                    Log::warning("No users found in the response or response data format is incorrect.");
                }
            } else {
                Log::error("Failed to import users, response was not successful.", ['statusCode' => $responseState->status(), 'responseBody' => $responseState->body()]);
            }
        } catch (\Exception $e) {
            Log::error('An error occurred during user import.', ['error' => $e->getMessage()]);
        }

        return back()->with('success', 'Users Synchronized Successfully');
    }


    public function updateMultipleStatus(Request $request)
    {
        $token = $this->tokenService->getToken();
        $users = $request->input('users');
        $responses = [];

        foreach ($users as $userId => $newStatus) {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
            ])->patch(config('services.three_cx.api_url') . "/xapi/v1/Users({$userId})", [
                'CurrentProfileName' => $newStatus
            ]);

            // Log the response status code and body for debugging
            Log::info("Updating User {$userId} to {$newStatus}", [
                'response_status' => $response->status(),
                'response_body' => $response->body(),
            ]);

            // Check if the response is successful
            if ($response->successful()) {
                $responses[$userId] = true;
            } else {
                Log::error("Error updating User {$userId}", [
                    'response_status' => $response->status(),
                    'response_body' => $response->body(),
                ]);
                $responses[$userId] = false;
            }
        }

        return response()->json([
            'success' => true,
            'responses' => $responses
        ]);
    }





    public function uploadCsv(Request $request)
    {
        // Validate file input
        Log::info('File upload started. Validating input...');
        $request->validate([
            'file' => 'required|mimes:csv,txt',
        ]);
        Log::info('File validation passed.');

        // Handle file upload
        $file = $request->file('file');
        $fileName = time() . '_' . $file->getClientOriginalName();
        Log::info('Storing file: ' . $fileName);
        $file->storeAs('uploads', $fileName);

        // Open file for reading
        $path = $file->getRealPath();
        Log::info('Reading file content from: ' . $path);

        $handle = fopen($path, 'r');
        if (!$handle) {
            Log::error('Failed to open file.');
            return back()->withErrors(['error' => 'Failed to open the file.']);
        }

        // Read header
        $header = fgetcsv($handle);
        if (!$header || count($header) < 6) {
            Log::error('The file does not contain valid headers.');
            fclose($handle);
            return back()->withErrors(['error' => 'Invalid file headers.']);
        }

        // Extract the first data row to get date, from, and to values
        $firstDataRow = fgetcsv($handle); // Read the first data row
        if (count($firstDataRow) < 6) {
            Log::error('Missing required columns (from, to, date).');
            fclose($handle);
            return back()->withErrors(['error' => 'Missing required columns (from, to, date).']);
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
            return back()->withErrors(['error' => 'Invalid time format in the file.']);
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
                'M d, Y',      // Jan 19, 2025
                'd M Y',       // 19 Jan 2025
                'F d, Y',      // January 19, 2025
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
                return back()->with(
                    'wrong',
                    'Invalid date format in the file. Please try one of these date formats:{ 2025-01-19, 2025/01/19, 19/01/2025, 01/19/2025, 19-01-2025, 01-19-2025, 19.01.2025, 19 Jan 2025, 19 January 2025 }'
                );
            }
        } catch (\Exception $e) {
            Log::error('Date format conversion error: ' . $e->getMessage());
            fclose($handle);
            return back()->withErrors(['wrong' => 'Invalid date format in the file.']);
        }



        // Create a SINGLE AutoDistributorFile entry for this upload
        Log::info('Creating AutoDistributorFile entry...');
        $uploadedFile = AutoDistributorFile::create([
            'file_name' => $fileName,
            'from' => $utcTime_from,
            'to' => $utcTime_to,
            'date' => $formattedDate,
            'uploaded_by' => Auth::id(),
        ]);
        Log::info('AutoDistributorFile entry created with ID: ' . $uploadedFile->id);

        // Prepare batch insert array
        $seenMobiles = [];
        $insertData = [];
        $rowCount = 0;
        $extensions = [];
        rewind($handle); // Rewind the file pointer to the beginning of the file
        fgetcsv($handle); // Skip the header row

        // Extract all extensions into an array for bulk fetching
        while (($row = fgetcsv($handle)) !== false) {
            if (isset($row[2])) {
                $extensions[] = $row[2]; // Collect extensions
            }
        }

        // Remove duplicates to reduce unnecessary queries
        $extensions = array_unique($extensions);

        // Fetch all user statuses in bulk
        Log::info('Fetching user statuses for extensions...');
        $userStatuses = TrheeCxUserStatus::whereIn('extension', $extensions)->get()->keyBy('extension');

        if ($userStatuses->isEmpty()) {
            Log::error('No valid user statuses found for provided extensions.');
            $uploadedFile->delete();
            fclose($handle);
            return back()->with(['wrong' => 'No valid user statuses found for the provided extensions.']);
        }

        // Reset the file pointer again to process rows
        rewind($handle);
        fgetcsv($handle); // Skip the header row
        // Process the CSV rows (starting from second row since first is header)
        while (($row = fgetcsv($handle)) !== false) {
            $rowCount++;

            // Check if the extension exists in the user statuses
            $userStatus = $userStatuses[$row[2]] ?? null;

            if (!$userStatus) {
                Log::warning('User status not found for extension: ' . $row[2]);
                $uploadedFile->delete(); // Rollback file upload on error
                fclose($handle);
                return back()->with(['wrong' => 'No user with extension ' . $row[2] . ' in your system.']);
            }

            // Ensure row has sufficient columns (at least 3: mobile, user, extension)
            if (count($row) < 3) {
                Log::warning('Skipping row due to insufficient columns: ' . json_encode($row));
                $uploadedFile->delete(); // Rollback file upload on error
                fclose($handle);
                return back()->with(['wrong' => 'Ensure that no row is empty in the file.']);
            }

            // Validate that the required columns have no empty values
            if (empty($row[0]) || empty($row[1]) || empty($row[2])) {
                $uploadedFile->delete(); // Rollback file upload on error
                fclose($handle);
                return back()->with(['wrong' => 'Ensure that no row is empty in the file.']);
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

            // Add to batch insert array
            $insertData[] = [
                'mobile' => $row[0],
                'user' => $row[1],
                'extension' => $row[2],
                'userStatus' => $userStatus->status,
                'three_cx_user_id' => $userStatus->user_id,
                'uploaded_by' => Auth::id(),
                'file_id' => $uploadedFile->id,
            ];

            Log::info('Prepared data for mobile: ' . $row[0] . ' extension: ' . $row[2]);

            // Batch insert every 1000 rows to prevent memory overflow
            if (count($insertData) >= 1000) {
                DB::beginTransaction();

                try {
                    Log::info('Inserting the following data: ' . json_encode($insertData));
                    AutoDistributorUploadedData::insert($insertData);
                    DB::commit();
                    Log::info('Inserted ' . count($insertData) . ' records successfully.');
                    $insertData = []; // Reset batch data
                } catch (\Exception $e) {
                    DB::rollBack();
                    Log::error("Error inserting records: " . $e->getMessage());
                    fclose($handle);
                    return back()->with(['wrong' => 'Failed to insert records into the database.']);
                }
            }
        }

        // Insert remaining rows if any
        if (!empty($insertData)) {
            DB::beginTransaction();

            try {
                AutoDistributorUploadedData::insert($insertData);
                DB::commit();
                Log::info('Inserted ' . count($insertData) . ' records successfully.');
            } catch (\Exception $e) {
                DB::rollBack();
                Log::error("Error inserting records: " . $e->getMessage());
                fclose($handle);
                return back()->with(['wrong' => 'Failed to insert remaining records into the database.']);
            }
        }

        fclose($handle);

        Log::info('File uploaded and processed successfully.');
        return back()->with('success', 'File uploaded and processed successfully.');

        // Insert remaining rows if any
        if (!empty($insertData)) {
            DB::beginTransaction();

            try {
                AutoDistributorUploadedData::insert($insertData);
                DB::commit();
                Log::info('Inserted ' . count($insertData) . ' records successfully.');
            } catch (\Exception $e) {
                DB::rollBack();
                Log::error("Error inserting records: " . $e->getMessage());
                fclose($handle);
                return back()->withErrors(['wrong' => 'Failed to insert remaining records into the database.']);
            }
        }

        fclose($handle);

        Log::info('File uploaded and processed successfully.');
        return back()->with('success', 'File uploaded and processed successfully.');
    }






    public function updateAutoDistributor(Request $request, $id)
    {
        $request->validate([
            'file_name' => 'required',
            'from' => 'required',
            'to' => 'required',
            'date' => 'required',
        ]);

        // Find the file by slug


        // Update all records for the given file ID
        AutoDistributorFile::where('id', $id)->update([
            'file_name' => $request->file_name,
            'from' => $request->from,
            'to' => $request->to,
            'date' => $request->date,
        ]);

        return redirect()->back()->with('success', 'Time and Date updated successfully for all records.');
    }



    public function updateAllowStatus(Request $request, $slug)
    {
        $file = AutoDistributorFile::where('slug', $slug)->firstOrFail();

        // Handle the 'allow' checkbox as a boolean
        $file->allow = $request->has('allow') ? (bool) $request->allow : false; // Ensure that allow is properly set as a boolean
        $file->save();

        // Active Log Report...............................
        ActivityLog::create([
            'user_id' => Auth::id(),
            'operation' => $file->allow ? 'Active' : "Inactive",
            'file_id' => $file->id,
            'file_type' => 'Auto-Distributer',
            'file_name' => $file->file_name,
            'operation_time' => now(),
        ]);

        return redirect('/auto-distributor/files');
    }




    public function show($slug)
    {
        $file = AutoDistributorFile::where('slug', $slug)->firstOrFail();

        // Check if the file exists
        // if (!Storage::exists('uploads/' . $file->file_name)) {
        //     abort(404, 'File not found.');
        // }

        // Get the file contents using the Storage facade
        $fileContents = Storage::get('uploads/' . $file->file_name);
        $data = array_map('str_getcsv', explode("\n", $fileContents));

        // Fetch the uploaded data with pagination
        $uploadedData = AutoDistributorUploadedData::where('file_id', $file->id)->paginate(500);  // Adjust number of items per page

        return view('autodisributers.show', compact('data', 'file', 'uploadedData'));
    }


    public function deleteFile($slug)
    {
        $file = AutoDistributorFile::where('slug', $slug)->firstOrFail();

        // Optionally delete the file from storage
        Storage::delete('uploads/' . $file->file_name);

        // Delete the record from the database
        $file->delete();

        // Active Log Report...............................
        ActivityLog::create([
            'user_id' => Auth::id(),
            'operation' => 'delete',
            'file_id' => $file->id,
            'file_type' => 'Auto-Distributer',
            'file_name' => $file->file_name,
            'operation_time' => now(),
        ]);
        return redirect()->route('distributor.files.index')->with('success', 'File deleted successfully.');
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
        $uploadedFile = AutoDistributorFile::findOrFail($fileId);

        // Join AutoDistributorUploadedData with users to get the uploader's name
        $data = AutoDistributorUploadedData::where('file_id', $fileId)
            ->join('users', 'users.id', '=', 'auto_distributor_uploaded_data.uploaded_by')
            ->select('auto_distributor_uploaded_data.*', 'users.name as uploader_name')
            ->get();

        $fileName = 'uploaded_data_' . $uploadedFile->file_name . '.csv';
        $headers = [
            "Content-type" => "text/csv",
            "Content-Disposition" => "attachment; filename=$fileName",
            "Pragma" => "no-cache",
            "Cache-Control" => "must-revalidate, post-check=0, pre-check=0",
            "Expires" => "0"
        ];

        $columns = ['Mobile', 'User', 'Extension', 'From', 'To', 'Date', 'Uploader Name'];

        $callback = function () use ($data, $columns) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);

            foreach ($data as $row) {
                fputcsv($file, [
                    $row->mobile,
                    $row->user,
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
            'file_id' => $fileName,
            'file_type' => 'Auto-Distributer',
            'file_name' => $fileId,
            'operation_time' => now(),
        ]);
        return response()->stream($callback, 200, $headers);
    }
}
