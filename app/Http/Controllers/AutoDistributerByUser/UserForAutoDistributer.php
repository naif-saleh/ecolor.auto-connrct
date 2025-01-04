<?php

namespace App\Http\Controllers\AutoDistributerByUser;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\AutoDistributererExtension;
use App\Models\ActivityLog;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Auth;
use App\Services\ThreeCXTokenService;


class UserForAutoDistributer extends Controller
{
    protected $threeCXTokenService;

    public function __construct(ThreeCXTokenService $threeCXTokenService)
    {
        $this->threeCXTokenService = $threeCXTokenService;
    }

    public function index()
    {
        // dd(config('services.three_cx.client_id'), config('services.three_cx.client_secret'));

        $extensions = AutoDistributererExtension::all();
        return view('autoDistributerByUser.User.index', compact('extensions'));
    }

    public function import()
    {
       // $token = Cache::get('three_cx_token');
        $token = $this->threeCXTokenService->fetchToken();


        try {
            $responseState = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token,
            ])->get("https://ecolor.3cx.agency/xapi/v1/Users");

            if ($responseState->successful()) {
                $responseData = $responseState->json();

                // Check if the response contains the 'value' key and it's an array
                if (isset($responseData['value']) && is_array($responseData['value'])) {
                    foreach ($responseData['value'] as $data) {
                        AutoDistributererExtension::firstOrCreate([
                            "user_id" => auth()->id(),
                            "name" => $data['FirstName'] ?? null,
                            "lastName" => $data['LastName'] ?? null,
                            "extension" => $data['Number'] ?? null,
                            "userStatus" => $data['CurrentProfileName'] ?? null,
                            "three_cx_user_id" => $data['Id'] ?? null,
                        ]);
                    }

                    // Active Log Report...............................
                    ActivityLog::create([
                        'user_id' => Auth::id(),
                        'operation' => 'import',
                        'file_type' => '3cx all users',
                        'file_name' => 'import users',
                        'operation_time' => now(),
                    ]);

                    // Redirect after successful import
                    return redirect()->route('auto_distributerer_extensions.index')->with('success', 'Your 3cx Users imported successfully');
                } else {
                    // Log and redirect if 'value' key is missing or not an array
                    Log::info("No users found in the response.");
                    return redirect()->route('auto_distributerer_extensions.index')->with('warning', 'No users found in the response.');
                }
            } else {
                // Log and redirect if the API response is unsuccessful
                Log::info("Users cannot be imported!!");
                return redirect()->route('auto_distributerer_extensions.index')->with('warning', 'Users cannot be imported!!');
            }
        } catch (\Exception $e) {
            // Log the error and redirect
            Log::error('import: An error occurred: ' . $e->getMessage());
            return redirect()->route('auto_distributerer_extensions.index')->with('error', 'An error occurred while importing users.');
        }
    }




    public function create()
    {
        $users = \App\Models\User::all();
        return view('autoDistributerByUser.User.create', compact('users'));
    }


    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'extension' => 'required|string|max:20',

        ]);

        AutoDistributererExtension::create($request->all());

        return redirect()->route('auto_distributerer_extensions.index')
            ->with('success', 'Extension created successfully.');
    }


    public function show(AutoDistributererExtension $autoDistributererExtension)
    {
        return view('autoDistributerByUser.User.show', compact('autoDistributererExtension'));
    }


    public function edit(AutoDistributererExtension $autoDistributererExtension)
    {
        $users = \App\Models\User::all();
        return view('autoDistributerByUser.User.edit', compact('autoDistributererExtension', 'users'));
    }


    public function update(Request $request, AutoDistributererExtension $autoDistributererExtension)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'extension' => 'required|string|max:20',
            'user_id' => 'required|exists:users,id',
        ]);

        $autoDistributererExtension->update($request->all());

        return redirect()->route('auto_distributerer_extensions.index')
            ->with('success', 'Extension updated successfully.');
    }


    public function destroy(AutoDistributererExtension $autoDistributererExtension)
    {
        $autoDistributererExtension->delete();
         // Active Log Report...............................
         ActivityLog::create([
            'user_id' => Auth::id(),
            'operation' => 'delete',
            'file_type' => 'delete user',
            'file_name' => 'delete user',
            'operation_time' => now(),
        ]);

        return redirect()->route('auto_distributerer_extensions.index')
            ->with('success', 'Extension deleted successfully.');
    }
    public function destroyAllUsers()
    {
        AutoDistributererExtension::query()->delete();
         // Active Log Report...............................
         ActivityLog::create([
            'user_id' => Auth::id(),
            'operation' => 'delete',
            'file_type' => 'All Users',
            'file_name' => 'All Users',
            'operation_time' => now(),
        ]);
        return redirect()->route('auto_distributerer_extensions.index')
            ->with('success', 'All users have been deleted successfully.');
    }
}
