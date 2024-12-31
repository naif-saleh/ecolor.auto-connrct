<?php

namespace App\Http\Controllers\AutoDistributerByUser;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\AutoDistributererExtension;

class UserForAutoDistributer extends Controller
{

    public function index()
    {
        $extensions = AutoDistributererExtension::all();
        return view('autoDistributerByUser.User.index', compact('extensions'));
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
        return view('autoDistributerByUser.User.edit', compact('autoDistributererExtension','users'));
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

        return redirect()->route('auto_distributerer_extensions.index')
                         ->with('success', 'Extension deleted successfully.');
    }


}
