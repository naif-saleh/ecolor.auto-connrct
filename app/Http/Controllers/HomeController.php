<?php

namespace App\Http\Controllers;

use App\Models\AutoDirtibuter;
use App\Models\AutoDailer;
use App\Models\Provider;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    // Show the homepage
    public function index()
    {
        // Fetch the latest files uploaded (You can modify this query as needed)
        $files_dis = AutoDirtibuter::latest()->take(5)->get();
        $files_dil = AutoDailer::latest()->take(5)->get();
        $providers = Provider::latest()->take(5)->get();

        return view('welcome', compact('files_dis','files_dil','providers'));
    }
}
