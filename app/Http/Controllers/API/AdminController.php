<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Complaint;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AdminController extends Controller
{
    /**
     * Display dashboard statistics
     */
    public function dashboard(): JsonResponse
    {
        // Count total complaints
        $totalComplaints = Complaint::count();
        
        // Count resolved complaints
        $resolvedComplaints = Complaint::where('is_resolved', true)->count();
        
        // Count pending complaints
        $pendingComplaints = $totalComplaints - $resolvedComplaints;
        
        // Count complaints by category
        $complaintsByCategory = Complaint::selectRaw('category_id, count(*) as count')
            ->with('category:id,name')
            ->groupBy('category_id')
            ->get()
            ->map(function ($item) {
                return [
                    'category' => $item->category->name,
                    'count' => $item->count
                ];
            });
        
        // Count complaints by status
        $complaintsByStatus = Complaint::selectRaw('status_id, count(*) as count')
            ->with('status:id,name,color')
            ->groupBy('status_id')
            ->get()
            ->map(function ($item) {
                return [
                    'status' => $item->status->name,
                    'color' => $item->status->color,
                    'count' => $item->count
                ];
            });
        
        // Count users by role
        $usersByRole = User::selectRaw('role, count(*) as count')
            ->groupBy('role')
            ->get();
        
        // Recent complaints
        $recentComplaints = Complaint::with(['category', 'status', 'user:id,name,email'])
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        return response()->json([
            'status' => true,
            'message' => 'Dashboard statistics retrieved successfully',
            'data' => [
                'total_complaints' => $totalComplaints,
                'resolved_complaints' => $resolvedComplaints,
                'pending_complaints' => $pendingComplaints,
                'resolution_rate' => $totalComplaints > 0 ? round(($resolvedComplaints / $totalComplaints) * 100, 2) : 0,
                'complaints_by_category' => $complaintsByCategory,
                'complaints_by_status' => $complaintsByStatus,
                'users_by_role' => $usersByRole,
                'recent_complaints' => $recentComplaints
            ]
        ]);
    }

    /**
     * List all users
     */
    public function users(Request $request): JsonResponse
    {
        $query = User::query();

        // Filter by role if provided
        if ($request->has('role')) {
            $query->where('role', $request->role);
        }

        // Apply search if provided
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('student_id', 'like', "%{$search}%");
            });
        }

        // Apply sorting
        $sortField = $request->input('sort_field', 'created_at');
        $sortDirection = $request->input('sort_direction', 'desc');
        $query->orderBy($sortField, $sortDirection);

        // Paginate results
        $perPage = $request->input('per_page', 10);
        $users = $query->paginate($perPage);

        return response()->json([
            'status' => true,
            'message' => 'Users retrieved successfully',
            'data' => $users
        ]);
    }

    /**
     * Create a new user
     */
    public function createUser(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'role' => 'required|in:student,staff,admin',
            'student_id' => 'nullable|string|max:50',
            'department' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => $request->role,
            'student_id' => $request->student_id,
            'department' => $request->department,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'User created successfully',
            'data' => $user
        ], 201);
    }

    /**
     * Update a user
     */
    public function updateUser(Request $request, string $id): JsonResponse
    {
        $user = User::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|string|email|max:255|unique:users,email,' . $id,
            'password' => 'nullable|string|min:8',
            'role' => 'sometimes|in:student,staff,admin',
            'student_id' => 'nullable|string|max:50',
            'department' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        // Update user data
        $userData = $request->except('password');
        
        // Update password if provided
        if ($request->has('password')) {
            $userData['password'] = Hash::make($request->password);
        }

        $user->update($userData);

        return response()->json([
            'status' => true,
            'message' => 'User updated successfully',
            'data' => $user
        ]);
    }

    /**
     * Delete a user
     */
    public function deleteUser(string $id): JsonResponse
    {
        $user = User::findOrFail($id);

        // Prevent deleting yourself
        if (auth()->id() === $user->id) {
            return response()->json([
                'status' => false,
                'message' => 'You cannot delete your own account'
            ], 422);
        }

        $user->delete();

        return response()->json([
            'status' => true,
            'message' => 'User deleted successfully'
        ]);
    }

    /**
     * Generate system reports
     */
    public function reports(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'report_type' => 'required|in:complaints,users,categories,statuses',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'category_id' => 'nullable|exists:complaint_categories,id',
            'status_id' => 'nullable|exists:complaint_statuses,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        $dateFrom = $request->input('date_from', now()->subMonths(1)->toDateString());
        $dateTo = $request->input('date_to', now()->toDateString());

        switch ($request->report_type) {
            case 'complaints':
                $query = Complaint::with(['category', 'status', 'user:id,name,email,role'])
                    ->whereBetween('created_at', [$dateFrom, $dateTo . ' 23:59:59']);

                if ($request->has('category_id')) {
                    $query->where('category_id', $request->category_id);
                }

                if ($request->has('status_id')) {
                    $query->where('status_id', $request->status_id);
                }

                $data = $query->get();
                break;

            case 'users':
                $query = User::withCount('complaints')
                    ->whereBetween('created_at', [$dateFrom, $dateTo . ' 23:59:59']);

                if ($request->has('role')) {
                    $query->where('role', $request->role);
                }

                $data = $query->get();
                break;

            case 'categories':
                $data = Complaint::selectRaw('category_id, count(*) as count')
                    ->with('category:id,name')
                    ->whereBetween('created_at', [$dateFrom, $dateTo . ' 23:59:59'])
                    ->groupBy('category_id')
                    ->get()
                    ->map(function ($item) {
                        return [
                            'category' => $item->category->name,
                            'count' => $item->count
                        ];
                    });
                break;

            case 'statuses':
                $data = Complaint::selectRaw('status_id, count(*) as count')
                    ->with('status:id,name,color')
                    ->whereBetween('created_at', [$dateFrom, $dateTo . ' 23:59:59'])
                    ->groupBy('status_id')
                    ->get()
                    ->map(function ($item) {
                        return [
                            'status' => $item->status->name,
                            'color' => $item->status->color,
                            'count' => $item->count
                        ];
                    });
                break;
        }

        return response()->json([
            'status' => true,
            'message' => 'Report generated successfully',
            'report_type' => $request->report_type,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'data' => $data
        ]);
    }
}