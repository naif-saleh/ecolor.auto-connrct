<?php

namespace App\Http\Controllers\AutoDistributerByUser;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\AutoDistributererExtension;

class UserForAutoDistributer extends Controller
{
    public function index()
    {
        $providers = AutoDistributererExtension::all();
        return view('autoDistributerByUser.User.index', compact('providers'));
    }

    public function create()
    {
        return view('autoDistributerByUser.User.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'extension' => 'required|string|max:50',
            'user_id' => 'required|exists:users,id',
        ]);

        AutoDistributererExtension::create($validated);

        return redirect()->route('autoDistributers.index')->with('success', 'User created successfully!');
    }


    public function show($id)
    {
        $provider = AutoDistributererExtension::with('feedFiles')->findOrFail($id);
        return view('autoDistributerByUser.User.show', compact('provider'));
    }


    public function edit($id)
    {
        $provider = AutoDistributererExtension::findOrFail($id);
        return view('autoDistributerByUser.User.edit', compact('provider'));
    }


    public function update(Request $request, $id)
    {
        $provider = AutoDistributererExtension::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'extension' => 'sometimes|string|max:50',
            'user_id' => 'sometimes|exists:users,id',
        ]);


        $provider->update($validated);

        return redirect()->route('autoDistributers.index')->with('success', 'User updated successfully!');
    }


    public function destroy($id)
    {
        $provider = AutoDistributererExtension::findOrFail($id);
        $provider->delete();

        return redirect()->route('autoDistributers.index')->with('success', 'User deleted successfully!');
    }



}
