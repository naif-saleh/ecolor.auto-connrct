<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class FilesController extends Controller
{
    public function index()
    {
        return response()->json(['message' => 'Files endpoint working!']);
    }
}
