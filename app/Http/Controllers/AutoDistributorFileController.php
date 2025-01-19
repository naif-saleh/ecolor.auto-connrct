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




class AutoDistributorFileController extends Controller
{
    protected $tokenService;

    public function __construct(TokenService $tokenService)
    {

        $this->tokenService = $tokenService;
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

        // Read and process file content
        $path = $file->getRealPath();
        Log::info('Reading file content from: ' . $path);
        $fileContents = file_get_contents($path);
        $fileContents = str_replace("\r\n", "\n", $fileContents); // Normalize line endings

        // Split content into rows
        $lines = explode("\n", $fileContents);
        $data = array_map('str_getcsv', $lines);

        // Check for valid file contents
        if (count($data) < 2) {
            Log::error('The file appears to be empty or has no valid rows.');
            return back()->withErrors(['error' => 'The file appears to be empty or has no valid rows.']);
        }
        Log::info('File contains data. Processing the file...');

        // Extract the first data row to get date, from, and to values
        $firstDataRow = $data[1]; // Assuming row 0 is headers, row 1 is first data row

        // Ensure required columns exist in the first row
        if (count($firstDataRow) < 6) {
            Log::error('Missing required columns (from, to, date).');
            return back()->withErrors(['error' => 'Missing required columns (from, to, date).']);
        }

        try {
            // Convert time fields safely, assuming 12-hour format in CSV
            Log::info('Converting time fields...');
            $utcTime_from = Carbon::createFromFormat('h:i:s A', $firstDataRow[3])->format('H:i:s');
            $utcTime_to = Carbon::createFromFormat('h:i:s A', $firstDataRow[4])->format('H:i:s');
            $formattedDate = Carbon::parse($firstDataRow[5])->format('Y-m-d');
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

            // Get all extensions from the CSV to fetch user statuses in bulk
            $extensions = array_column(array_slice($data, 1), 2);
            $userStatuses = TrheeCxUserStatus::whereIn('extension', $extensions)->get()->keyBy('extension');

            // Prepare batch insert array
            $insertData = [];

            // Process ALL rows (skipping the header)
            foreach ($data as $index => $row) {
                if ($index == 0) continue; // Skip header row

                // Ensure row has sufficient columns
                if (count($row) < 3) {
                    Log::warning('Skipping row due to missing columns: ' . json_encode($row));
                    continue;
                }

                // Find user status using preloaded data
                $userStatus = $userStatuses[$row[2]] ?? null;

                // Ensure user status exists
                if (!$userStatus) {
                    Log::warning('User status not found for extension: ' . $row[2]);
                    continue;
                }

                // Add to batch insert array
                $insertData[] = [
                    'mobile' => $row[0],
                    'user' => $row[1],
                    'extension' => $row[2],
                    'userStatus' => $userStatus->status,
                    'three_cx_user_id' => $userStatus->user_id,
                    'uploaded_by' => Auth::id(),
                    'file_id' => $uploadedFile->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                Log::info('Prepared data for mobile: ' . $row[0] . ' extension: ' . $row[2]);
            }

            // Batch insert for better performance
            if (!empty($insertData)) {
                AutoDistributorUploadedData::insert($insertData);
                Log::info('Inserted ' . count($insertData) . ' records successfully.');
            } else {
                Log::warning('No valid records found for insertion.');
            }

            Log::info('File uploaded and processed successfully.');
            return back()->with('success', 'File uploaded and processed successfully.');
        } catch (\Exception $e) {
            // Log the error with exception details
            Log::error("Error processing file: " . $e->getMessage());
            Log::error("Error Details: " . $e->getTraceAsString());
            return back()->withErrors(['error' => 'There was an error processing the file.']);
        }
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




    public function index()
    {
        $files = AutoDistributorFile::paginate(20);
        $threeCxUsers = TrheeCxUserStatus::all();
        // $numbersCount =  AutoDistributorUploadedData::where('file_id', $files->file->id)->where('state', 'new')->count();
        return view('autodisributers.index', compact('files', 'threeCxUsers'));
    }

    public function show($slug)
    {
        $file = AutoDistributorFile::where('slug', $slug)->firstOrFail();

        // Check if the file exists
        if (!Storage::exists('uploads/' . $file->file_name)) {
            abort(404, 'File not found.');
        }

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
