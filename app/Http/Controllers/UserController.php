<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use App\Models\UserActivityLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class UserController extends Controller
{

    /**
     *  @OA\Get(
     *       path="/users",
     *       tags={"Users"},
     *       summary="Get all Users",
     *       description="Get list of all Users",
     *       @OA\Response(response=200, description="Users retrieved successfully")
     *   )
     */

    /**
     * Display a list of users
     */
    public function index()
    {
        $users = User::all();
        return view('users.index', compact('users'));
    }

    /**
     * Reset Password
     */
    public function resetPassword(Request $request)
    {
        // Validate the new password
        $validator = Validator::make($request->all(), [
            'new_password' => 'required|min:8',
        ]);

        

        // Find the user by the ID
        $user = User::findOrFail($request->user_id);

        // Update the user's password
        $user->password = Hash::make($request->new_password);
        $user->save();
        UserActivityLog::create([
            'user_id' => Auth::id(),
            'opreation' => 'Reset User Password',
            'user_role' => $user->role,
            'user_name' => $user->name,
            'user_email' => $user->email

        ]);
        return response()->json(['success' => true, 'message' => 'Password reset successfully.']);
    }

    /**
     * Show the form to create a new user
     */
    public function create()
    {
        return view('users.create');
    }

    /**
     * Store the new user
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6|confirmed',
            'role' => 'required',
        ]);


        // Create the user
        User::create([
            'name' => $request->input('name'),
            'email' => $request->input('email'),
            'password' => Hash::make($request->input('password')),
            'role' => $request->input('role'),
        ]);


        // Active Log Report...............................
        UserActivityLog::create([
            'user_id' => Auth::id(),
            'opreation' => 'Add User',
            'user_role' => $request->role ? $request->role : '',
            'user_name' => $request->name,
            'user_email' => $request->email

        ]);
        return redirect()->route('users.system.index')->with('success', 'User created successfully.');
    }

    /**
     * Show the form to edit a user
     */
    public function edit(User $user)
    {
        return view('users.edit', compact('user'));
    }

     /**
     * Update user details
     */
    public function update(Request $request, User $user)
    {

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $user->id,
            'role' => 'required',
        ]);


        // dd($request->role);
        $user->update([
            'name' => $request->name,
            'email' => $request->email,
            'role' => $request->role,
        ]);

        // Active Log Report...............................
        UserActivityLog::create([
            'user_id' => Auth::id(),
            'opreation' => 'Update User',
            'user_role' => $request->role ? $request->role : '',
            'user_name' => $request->name,
            'user_email' => $request->email

        ]);
        return redirect()->route('users.system.index')->with('success', 'User updated successfully.');
    }

    /**
     * Delete a user
     */
    public function destroy(User $user)
    {
        Log::info("User Deleted");

        if ($user->id === auth()->id()) {
            return redirect()->route('users.system.index')->with('wrong', 'You cannot delete your own account.');
        }

        $user->delete();
        // // Active Log Report...............................
        // dd($user->name);
        // Active Log Report...............................
        UserActivityLog::create([
            'user_id' => Auth::id(),
            'opreation' => 'Delete User',
            'user_role' => $user->role,
            'user_name' => $user->name,
            'user_email' => $user->email

        ]);

        return redirect()->route('users.system.index')->with('success', 'User deleted successfully.');
    }


}
