<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\ComplaintStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class StatusController extends Controller
{
    /**
     * Display a listing of the statuses.
     */
    public function index(Request $request): JsonResponse
    {
        $query = ComplaintStatus::query();

        // Filter active statuses only if requested
        if ($request->has('active_only') && $request->active_only) {
            $query->where('is_active', true);
        }

        $statuses = $query->orderBy('name')->get();

        return response()->json([
            'status' => true,
            'message' => 'Statuses retrieved successfully',
            'data' => $statuses
        ]);
    }

    /**
     * Store a newly created status.
     */
    public function store(Request $request): JsonResponse
    {
        // Only admins can create statuses
        if (!$request->user()->isAdmin()) {
            return response()->json([
                'status' => false,
                'message' => 'You do not have permission to create statuses'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:complaint_statuses',
            'description' => 'nullable|string',
            'color' => 'nullable|string|max:7',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        $status = ComplaintStatus::create([
            'name' => $request->name,
            'description' => $request->description,
            'color' => $request->color ?? '#3498db',
            'is_active' => $request->is_active ?? true,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Status created successfully',
            'data' => $status
        ], 201);
    }

    /**
     * Display the specified status.
     */
    public function show(string $id): JsonResponse
    {
        $status = ComplaintStatus::findOrFail($id);

        return response()->json([
            'status' => true,
            'message' => 'Status retrieved successfully',
            'data' => $status
        ]);
    }

    /**
     * Update the specified status.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        // Only admins can update statuses
        if (!$request->user()->isAdmin()) {
            return response()->json([
                'status' => false,
                'message' => 'You do not have permission to update statuses'
            ], 403);
        }

        $status = ComplaintStatus::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255|unique:complaint_statuses,name,' . $id,
            'description' => 'nullable|string',
            'color' => 'nullable|string|max:7',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        $status->update($request->all());

        return response()->json([
            'status' => true,
            'message' => 'Status updated successfully',
            'data' => $status
        ]);
    }

    /**
     * Remove the specified status.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        // Only admins can delete statuses
        if (!$request->user()->isAdmin()) {
            return response()->json([
                'status' => false,
                'message' => 'You do not have permission to delete statuses'
            ], 403);
        }

        $status = ComplaintStatus::findOrFail($id);

        // Check if status has associated complaints
        if ($status->complaints()->count() > 0) {
            return response()->json([
                'status' => false,
                'message' => 'Cannot delete status with associated complaints. Deactivate it instead.'
            ], 422);
        }

        $status->delete();

        return response()->json([
            'status' => true,
            'message' => 'Status deleted successfully'
        ]);
    }
}