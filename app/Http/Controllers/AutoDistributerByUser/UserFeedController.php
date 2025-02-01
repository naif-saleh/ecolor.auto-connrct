<?php

namespace App\Http\Controllers\AutoDistributerByUser;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\AutoDistributorFile;
use App\Models\TrheeCxUserStatus;
use App\Models\AutoDirtibuterData;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use App\Services\TokenService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class UserFeedController extends Controller
{


    protected $tokenService;

    public function __construct(TokenService $tokenService)
    {

        $this->tokenService = $tokenService;
    }


    public function index()
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
        $users = TrheeCxUserStatus::all();
        return view('autoDistributerByUser.User.index', compact('users')) ;

    }

    public function createFile(TrheeCxUserStatus $user)
    {
        $users = TrheeCxUserStatus::find($user);
        return view('autoDistributerByUser.UserFeed.create', compact('user'));
    }


    public function storeFile(Request $request, TrheeCxUserStatus $user)
    {
        $request->validate([
            'file_name' => 'required|string|max:255',
            'file_upload' => 'required|file|mimes:csv,txt,xlsx|max:5120', // Increased file size limit
            'from' => 'nullable|date_format:H:i',
            'to' => 'nullable|date_format:H:i',
            'date' => 'required|date',
        ]);

        // Store the uploaded file
        $filePath = $request->file('file_upload')->store('user_files');

        // Create a record for the file
        $file = AutoDistributorFile::create([
            'file_name' => $request->file_name,
            'slug' => Str::slug($request->file_name . '-' . time()),
            'is_done' => false,
            'allow' => false,
            'from' => $request->from,
            'to' => $request->to,
            'date' => $request->date,
            'uploaded_by' => auth()->id(),
            'provider_id' => $user->id,
        ]);

        // Process CSV file for mobile numbers
        $this->processCsvFile($filePath, $user, $file->id);

        return redirect()->route('provider.files.index', $user)->with('success', 'File added and data imported successfully!');
    }

    private function processCsvFile($filePath, $provider, $fileId)
    {
        $file = Storage::get($filePath);
        $lines = explode("\n", $file);
        $batchData = [];
        $batchSize = 1000; // Process in chunks

        foreach ($lines as $line) {
            $mobile = trim($line); // Assuming each line contains only a mobile number

            if ($this->isValidMobile($mobile)) {
                $batchData[] = [
                    'auto_dailer_file_id' => $fileId,
                    'mobile' => $mobile,
                    'provider_name' => $provider->name,
                    'extension' => $provider->extension,
                    'state' => 'new', // Default state
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            // Insert in batches to improve performance
            if (count($batchData) >= $batchSize) {
                AutoDirtibuterData::insert($batchData);
                $batchData = []; // Reset batch
            }
        }

        // Insert remaining records
        if (!empty($batchData)) {
            AutoDirtibuterData::insert($batchData);
        }
    }

    private function isValidMobile($mobile)
    {
        return preg_match('/^\+?[1-9]\d{7,14}$/', $mobile); // Supports international numbers
    }

    // Display all files for a provider
    public function files(TrheeCxUserStatus $user)
    {
        $files = $user->files; // Relationship defined in the provider model
        return view('autoDailerByProvider.ProviderFeed.feed', compact('user', 'files'));
    }


}
