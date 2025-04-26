<?php

namespace App\Http\Middleware;

use App\Models\ADistAgent;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ActiveAgents
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Get agent ID from route parameter or request
        $agentId = $request->route('agent') ?? $request->input('agent');

        // Remove the dd($agentId); line in production code
        // dd($agentId);

        // Check if the agent exists and is active
        $agent = ADistAgent::find($agentId->id);
         // First check if agent exists, then check if it's inactive
        if (! $agent || $agent->is_active == 0) {
            return redirect()->back()->with('wrong', 'Agent is Inactive, Please Activate Agent');
        }

        // Agent exists and is active, proceed
        return $next($request);
    }
}
