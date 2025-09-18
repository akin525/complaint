<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        if (!$request->user()) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized'
            ], 401);
        }

        if (empty($roles)) {
            return $next($request);
        }

        foreach ($roles as $role) {
            // Check if user has the required role
            if ($request->user()->role === $role) {
                return $next($request);
            }
        }

        return response()->json([
            'status' => false,
            'message' => 'You do not have permission to access this resource'
        ], 403);
    }
}