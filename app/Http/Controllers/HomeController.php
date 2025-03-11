<?php

namespace App\Http\Controllers;

use App\Models\ADialFeed;
use App\Models\ADialProvider;
use App\Models\ADistFeed;
use App\Models\ADistAgent;
use App\Models\ADistData;
use App\Models\ADialData;
use App\Models\General_Setting;

class HomeController extends Controller
{
    // Show the homepage
    public function index()
    {
        $logo = General_Setting::get('logo', 'logos/default.png');

        $providers = ADialProvider::count();
        $agents = ADistAgent::where('status', 'Available')->count();
        $agents_not_working = ADistAgent::where('status', '!=', 'Available')->count();
        $dialFeeds = ADialFeed::count();
        $distFeeds = ADistFeed::count();

        return view('welcome', compact('providers', 'agents', 'dialFeeds', 'distFeeds', 'agents_not_working', 'logo'));
    }
}
