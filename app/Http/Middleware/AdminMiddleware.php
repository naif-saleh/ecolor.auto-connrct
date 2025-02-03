<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminMiddleware
{
    
    /**
     * Permission Ability For Each User
     */
    public function handle(Request $request, Closure $next)
    {
        if (Auth::check() && (Auth::user()->role === 'admin'|| Auth::user()->role === 'superuser' || Auth::user()->role === 'user' || Auth::user()->role === 'manager' )) {
            return $next($request);
        }

        // Redirect back with an error if not an admin
        return redirect()->route('home')->with('error', 'You do not have permission to access this page.');
    }
}
