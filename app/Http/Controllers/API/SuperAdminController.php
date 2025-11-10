<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Complaint;
use App\Models\ComplaintCategory;
use App\Models\ComplaintStatus;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class SuperAdminController extends Controller
{
    /**
     * Get comprehensive system statistics
     */
    public function systemStats(): JsonResponse
    {
        $stats = [
            'users' => [
                'total' => User::count(),
                'students' => User::where('role', 'student')->count(),
                'staff' => User::where('role', 'staff')->count(),
                'admins' => User::where('role', 'admin')->count(),
                'superadmins' => User::where('role', 'superadmin')->count(),
                'recent' => User::where('created_at', '>=', now()->subDays(7))->count(),
            ],
            'complaints' => [
                'total' => Complaint::count(),
                'pending' => Complaint::where('is_resolved', false)->count(),
                'resolved' => Complaint::where('is_resolved', true)->count(),
                'today' => Complaint::whereDate('created_at', today())->count(),
                'this_week' => Complaint::where('created_at', '>=', now()->subDays(7))->count(),
                'this_month' => Complaint::where('created_at', '>=', now()->subMonth())->count(),
            ],
            'categories' => ComplaintCategory::count(),
            'statuses' => ComplaintStatus::count(),
            'system' => [
                'maintenance_mode' => SystemSetting::isMaintenanceMode(),
                'database_size' => $this->getDatabaseSize(),
            ],
        ];

        return response()->json([
            'status' => true,
            'message' => 'System statistics retrieved successfully',
            'data' => $stats
        ]);
    }

    /**
     * Get all system settings
     */
    public function getSettings(): JsonResponse
    {
        $settings = SystemSetting::all();

        return response()->json([
            'status' => true,
            'message' => 'Settings retrieved successfully',
            'data' => $settings
        ]);
    }

    /**
     * Update system settings
     */
    public function updateSettings(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'settings' => 'required|array',
            'settings.*.key' => 'required|string',
            'settings.*.value' => 'required',
            'settings.*.type' => 'required|in:string,boolean,integer,json',
            'settings.*.description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        foreach ($request->settings as $setting) {
            SystemSetting::set(
                $setting['key'],
                $setting['value'],
                $setting['type'],
                $setting['description'] ?? null
            );
        }

        return response()->json([
            'status' => true,
            'message' => 'Settings updated successfully'
        ]);
    }

    /**
     * Toggle maintenance mode
     */
    public function toggleMaintenanceMode(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'enabled' => 'required|boolean',
            'message' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        SystemSetting::set('maintenance_mode', $request->enabled, 'boolean', 'System maintenance mode');
        
        if ($request->has('message')) {
            SystemSetting::set('maintenance_message', $request->message, 'string', 'Maintenance mode message');
        }

        return response()->json([
            'status' => true,
            'message' => 'Maintenance mode ' . ($request->enabled ? 'enabled' : 'disabled'),
            'data' => [
                'maintenance_mode' => $request->enabled,
                'message' => $request->message ?? SystemSetting::getMaintenanceMessage()
            ]
        ]);
    }

    /**
     * Get system logs
     */
    public function getLogs(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'type' => 'nullable|in:all,errors,info,warnings',
            'date' => 'nullable|date',
            'lines' => 'nullable|integer|min:10|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        $logFile = storage_path('logs/laravel.log');
        $lines = $request->input('lines', 100);

        if (!file_exists($logFile)) {
            return response()->json([
                'status' => true,
                'message' => 'No logs found',
                'data' => []
            ]);
        }

        $logs = $this->readLastLines($logFile, $lines);

        return response()->json([
            'status' => true,
            'message' => 'Logs retrieved successfully',
            'data' => $logs
        ]);
    }

    /**
     * Clear system logs
     */
    public function clearLogs(): JsonResponse
    {
        $logFile = storage_path('logs/laravel.log');

        if (file_exists($logFile)) {
            file_put_contents($logFile, '');
        }

        return response()->json([
            'status' => true,
            'message' => 'Logs cleared successfully'
        ]);
    }

    /**
     * Manage user roles (promote/demote)
     */
    public function updateUserRole(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'role' => 'required|in:student,staff,admin,superadmin',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::findOrFail($id);

        // Prevent changing own role
        if (auth()->id() === $user->id) {
            return response()->json([
                'status' => false,
                'message' => 'You cannot change your own role'
            ], 422);
        }

        $user->update(['role' => $request->role]);

        return response()->json([
            'status' => true,
            'message' => 'User role updated successfully',
            'data' => $user
        ]);
    }

    /**
     * Bulk delete users
     */
    public function bulkDeleteUsers(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_ids' => 'required|array',
            'user_ids.*' => 'required|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        // Prevent deleting yourself
        if (in_array(auth()->id(), $request->user_ids)) {
            return response()->json([
                'status' => false,
                'message' => 'You cannot delete your own account'
            ], 422);
        }

        $deleted = User::whereIn('id', $request->user_ids)->delete();

        return response()->json([
            'status' => true,
            'message' => "{$deleted} users deleted successfully"
        ]);
    }

    /**
     * Get database size
     */
    private function getDatabaseSize(): string
    {
        try {
            $database = config('database.connections.mysql.database');
            $result = DB::select("
                SELECT 
                    ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb
                FROM information_schema.TABLES 
                WHERE table_schema = ?
            ", [$database]);

            return ($result[0]->size_mb ?? 0) . ' MB';
        } catch (\Exception $e) {
            return 'N/A';
        }
    }

    /**
     * Read last N lines from a file
     */
    private function readLastLines(string $file, int $lines): array
    {
        $handle = fopen($file, 'r');
        $linecounter = $lines;
        $pos = -2;
        $beginning = false;
        $text = [];

        while ($linecounter > 0) {
            $t = ' ';
            while ($t != "\n") {
                if (fseek($handle, $pos, SEEK_END) == -1) {
                    $beginning = true;
                    break;
                }
                $t = fgetc($handle);
                $pos--;
            }
            $linecounter--;
            if ($beginning) {
                rewind($handle);
            }
            $text[$lines - $linecounter - 1] = fgets($handle);
            if ($beginning) break;
        }
        fclose($handle);

        return array_reverse($text);
    }

    /**
     * Export system data
     */
    public function exportData(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'type' => 'required|in:users,complaints,all',
            'format' => 'required|in:json,csv',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        $data = [];

        switch ($request->type) {
            case 'users':
                $data = User::all()->toArray();
                break;
            case 'complaints':
                $data = Complaint::with(['category', 'status', 'user'])->get()->toArray();
                break;
            case 'all':
                $data = [
                    'users' => User::all()->toArray(),
                    'complaints' => Complaint::with(['category', 'status', 'user'])->get()->toArray(),
                    'categories' => ComplaintCategory::all()->toArray(),
                    'statuses' => ComplaintStatus::all()->toArray(),
                ];
                break;
        }

        return response()->json([
            'status' => true,
            'message' => 'Data exported successfully',
            'data' => $data
        ]);
    }
}