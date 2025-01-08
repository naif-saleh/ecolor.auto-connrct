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
        try {
            $responseState = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token,
            ])->get("https://ecolor.3cx.agency/xapi/v1/Users");

            if ($responseState->successful()) {
                $responseData = $responseState->json();

                // Check if the response contains the 'value' key and it's an array
                if (isset($responseData['value']) && is_array($responseData['value'])) {
                    foreach ($responseData['value'] as $data) {
                        TrheeCxUserStatus::firstOrCreate([
                            "user_id" => $data['Id'] ?? null,
                            "firstName" => $data['FirstName'] ?? null,
                            "lastName" => $data['LastName'] ?? null,
                            "displayName" => $data['DisplayName'] ?? null,
                            "email" => $data['EmailAddress'] ?? null,
                            "isRegistred" => $data['IsRegistered'] ?? null,
                            "QueueStatus" => $data['QueueStatus'] ?? null,
                            "extension" => $data['Number'] ?? null,
                            "status" => $data['CurrentProfileName'] ?? null,

                        ]);
                    }


                    // Redirect after successful import

                } else {
                    // Log and redirect if 'value' key is missing or not an array
                    Log::info("No users found in the response.");
                }
            } else {
                // Log and redirect if the API response is unsuccessful
                Log::info("Users cannot be imported!!");
            }
        } catch (\Exception $e) {
            // Log the error and redirect
            Log::error('import: An error occurred: ' . $e->getMessage());
        }

        return back()->with('success', 'All Users Imported Successfully');
    }



    public function uploadCsv(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:csv,txt',
        ]);

        $file = $request->file('file');
        $fileName = time() . '_' . $file->getClientOriginalName();
        $file->storeAs('uploads', $fileName);

        $uploadedFile = AutoDistributorFile::create([
            'file_name' => $fileName,
            'uploaded_by' => Auth::id(),
        ]);

        // Active Log Report
        ActivityLog::create([
            'user_id' => Auth::id(),
            'operation' => 'import File',
            'file_id' => $uploadedFile->id,
            'file_type' => 'Auto-Distributer',
            'file_name' => $uploadedFile->file_name,
            'operation_time' => now(),
        ]);

        $path = $file->getRealPath();
        $data = array_map('str_getcsv', file($path));

        foreach ($data as $row) {
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

            Log::info("Time From: " . $formattedTime_from . " | Time To: " . $formattedTime_to);

            // Check if extension exists in ThreeCxUserStatus table
            $userStatus = TrheeCxUserStatus::where('extension', $row[2])->first();

            // If the user extension exists, store it in AutoDistributorUploadedData
            if ($userStatus) {
                $csv_file = AutoDistributorUploadedData::create([

                    'mobile' => $row[0],
                    'user' => $row[1],
                    'extension' => $row[2],
                    'from' => $formattedTime_from,
                    'to' => $formattedTime_to,
                    'date' => $row[5],
                    'userStatus' => $userStatus->status,
                    'three_cx_user_id' => $userStatus->user_id,
                    'uploaded_by' => Auth::id(),
                    'file_id' => $uploadedFile->id,
                ]);

                // Active Log Report
                ActivityLog::create([
                    'user_id' => Auth::id(),
                    'operation' => 'import',
                    'file_type' => '3cx all users',
                    'file_name' => 'import users',
                    'operation_time' => now(),
                ]);

                $csv_file->save();
            } else {
                Log::warning('No user found with extension ' . $row[2]);
            }
        }

        return back()->with('success', 'File uploaded and processed successfully');
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
            'file_id' => $fileName,
            'file_type' => 'Auto-Distributer',
            'file_name' => $fileId,
            'operation_time' => now(),
        ]);
        return response()->stream($callback, 200, $headers);
    }
}
