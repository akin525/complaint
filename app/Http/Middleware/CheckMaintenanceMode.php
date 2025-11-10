<?php

namespace App\Http\Middleware;

use App\Models\SystemSetting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckMaintenanceMode
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if maintenance mode is enabled
        if (SystemSetting::isMaintenanceMode()) {
            // Allow superadmins to bypass maintenance mode
            if ($request->user() && $request->user()->isSuperAdmin()) {
                return $next($request);
            }

            return response()->json([
                'status' => false,
                'message' => SystemSetting::getMaintenanceMessage(),
                'maintenance_mode' => true
            ], 503);
        }

        return $next($request);
    }
}