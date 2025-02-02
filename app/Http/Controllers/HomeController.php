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
        return view('welcome');
    }
}
