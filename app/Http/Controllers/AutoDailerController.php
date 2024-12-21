<?php

namespace App\Http\Controllers;

use App\Models\AutoDailer;
use App\Models\AutoDailerData;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AutoDailerController extends Controller
{

    /**
     *  @OA\Get(
     *       path="/auto-dailer",
     *       tags={"AutoDailer"},
     *       summary="Get all AutoDailers",
     *       description="Get list of all AutoDailers",
     *       @OA\Response(response=200, description="AutoDailers retrieved successfully")
     *   )
     */



    // Display list of uploaded files...........................................................................................................
    public function index()
    {
        $files = AutoDailer::with('user')->latest()->get();
        return view('autodailers.index', compact('files'));
    }

    // Show form to upload CSV..................................................................................................................
    public function create()
    {
        return view('autodailers.create');
    }


    // Store Auto Dailer file & data............................................................................................................
    public function store(Request $request)
    {
        $request->validate([
            'file_name' => 'required|string|max:255',
            'file' => 'required|mimes:csv,txt',
        ]);

        $file = $request->file('file');
        $randomFileName = Str::random(40) . '.' . $file->getClientOriginalExtension();

        // Check if the file is empty
        if ($file->getSize() == 0) {
            return redirect()->route('autodailers.index')->with('error', 'The uploaded file is empty.');
        }

        // $fileContent = file($file->getRealPath());
        // $fileRows = array_map('str_getcsv', $fileContent);

        // Validate headers
        // $headers = $fileRows[0] ?? [];
        // if ($headers !== ['mobile', 'provider_name', 'extension']) {
        //     return redirect()->route('autodailers.index')->with('error', 'Invalid CSV structure. Headers must be: mobile, provider_name, extension.');
        // }

        // // Validate content rows
        // $isValidStructure = true;
        // foreach (array_slice($fileRows, 1) as $index => $row) {
        //     if (count($row) !== 3 || !preg_match('/^\d+$/', $row[0]) || !is_string($row[1]) || !preg_match('/^\d{3,4}$/', $row[2])) {
        //         $isValidStructure = false;
        //         $errorRow = $index + 2; // Account for header and 0-based index
        //         break;
        //     }
        // }

        // if (!$isValidStructure) {
        //     return redirect()->route('autodailers.index')->with('error', "Invalid data in CSV at row {$errorRow}. Ensure the mobile is numeric, provider_name is a string, and extension is a 3-4 digit number.");
        // }

        // Create AutoDailer record
        $autoDailer = AutoDailer::create([
            'file_name' => $request->input('file_name'),
            'uploaded_by' => Auth::id(),
        ]);

        // Log activity
        ActivityLog::create([
            'user_id' => Auth::id(),
            'operation' => 'create',
            'file_type' => 'AutoDailer',
            'file_name' => $request->input('file_name'),
            'operation_time' => now(),
        ]);

        // Store file and update file path
        $filePath = $file->storeAs('csv_files', $randomFileName, 'public');
        $autoDailer->update(['file_path' => $filePath]);

        // Save valid rows to database
        // foreach (array_slice($fileRows, 1) as $row) {
        //     AutoDailerData::create([
        //         'auto_dailer_id' => $autoDailer->id,
        //         'mobile' => $row[0],
        //         'provider_name' => $row[1],
        //         'extension' => $row[2],
        //     ]);
        // }

        // if (!$isValidStructure) {
        //     Storage::disk('public')->delete($filePath);
        //     return redirect()->route('autodailers.index')->with('error', 'File structure is not correct. Please ensure each row has 3 columns. The File is Empty, please delete it and upload file in correct structure');
        // }

        return redirect('/auto-dailer-call');

        // return redirect()->route('autodailers.index')->with('success', 'File uploaded successfully.');
    }


    // Edit file name...............................................................................................................................
    public function edit($id)
    {
        $file = AutoDailer::findOrFail($id);
        return view('autodailers.edit', compact('file'));
    }

    // Update file name.............................................................................................................................
    public function update(Request $request, $id)
    {
        $request->validate([
            'file_name' => 'required|string|max:255',
        ]);

        $file = AutoDailer::findOrFail($id);
        $file->update(['file_name' => $request->file_name]);

        // Active Log Report...............................
        ActivityLog::create([
            'user_id' => Auth::id(),
            'operation' => 'update',
            'file_type' => 'AutoDailer',
            'file_name' => $request->input('file_name'),
            'operation_time' => now(),
        ]);

        return redirect()->route('autodailers.index')->with('success', 'File name updated successfully.');
    }


    // Show details of a specific uploaded file.....................................................................................................
    public function show($id)
    {
        $file = AutoDailer::findOrFail($id);
        $autodailerData = $file->autodailerData()->paginate(1000);

        return view('autodailers.show', compact('file', 'autodailerData'));
    }


    // Delete a file................................................................................................................................
    public function destroy($id)
    {
        $autoDailer = AutoDailer::find($id);
        if (!$autoDailer) {
            return back()->with('error', 'File not found.');
        }

        $fileName = $autoDailer->file_name;
        $autoDailer->delete();

        // Log the operation in the ActivityLog
        ActivityLog::create([
            'user_id' => Auth::id(),
            'operation' => 'delete',
            'file_type' => 'AutoDailer',
            'file_name' => $fileName,
            'operation_time' => now(),
        ]);
        return back()->with('success', 'File deleted.');
    }
    // Download File.................................................................................................................................
    public function download($id)
    {
        $file = AutoDailer::findOrFail($id);
        $filePath = $file->file_path;
        if (!Storage::disk('public')->exists($filePath)) {
            return redirect()->route('autodailers.index')->with('error', 'File not found.');
        }

        // Active Log Report...............................
        ActivityLog::create([
            'user_id' => Auth::id(),
            'operation' => 'download',
            'file_type' => 'AutoDailer',
            'file_name' => $file->file_name,
            'operation_time' => now(),
        ]);
        return Storage::disk('public')->download($filePath);
    }
}
