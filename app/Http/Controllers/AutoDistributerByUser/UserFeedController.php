<?php

namespace App\Http\Controllers\AutoDistributerByUser;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\AutoDistributerExtensionFeed;
use App\Models\TrheeCxUserStatus;
use App\Models\AutoDistributererExtension;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use App\Services\TokenService;
use Illuminate\Support\Facades\Http;

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


}
