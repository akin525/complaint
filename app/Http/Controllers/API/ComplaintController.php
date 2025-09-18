<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Complaint;
use App\Models\ComplaintCategory;
use App\Models\ComplaintStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class ComplaintController extends Controller
{
    /**
     * Display a listing of the complaints.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = Complaint::with(['category', 'status', 'user:id,name,email,student_id,department']);

        // Filter complaints based on user role
        if ($user->isStudent()) {
            // Students can only see their own complaints
            $query->where('user_id', $user->id);
        }

        // Apply filters if provided
        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->has('status_id')) {
            $query->where('status_id', $request->status_id);
        }

        if ($request->has('is_resolved')) {
            $query->where('is_resolved', $request->is_resolved);
        }

        // Apply search if provided
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('subject', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Apply sorting
        $sortField = $request->input('sort_field', 'created_at');
        $sortDirection = $request->input('sort_direction', 'desc');
        $query->orderBy($sortField, $sortDirection);

        // Paginate results
        $perPage = $request->input('per_page', 10);
        $complaints = $query->paginate($perPage);

        return response()->json([
            'status' => true,
            'message' => 'Complaints retrieved successfully',
            'data' => $complaints
        ]);
    }

    /**
     * Store a newly created complaint.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'category_id' => 'required|exists:complaint_categories,id',
            'subject' => 'required|string|max:255',
            'description' => 'required|string',
            'is_anonymous' => 'boolean',
            'attachments.*' => 'nullable|file|mimes:jpeg,png,jpg,pdf,doc,docx|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        // Get default status (e.g., "Pending" or "New")
        $defaultStatus = ComplaintStatus::where('name', 'Pending')
            ->orWhere('name', 'New')
            ->first();

        if (!$defaultStatus) {
            $defaultStatus = ComplaintStatus::first();
        }

        // Handle file uploads
        $attachments = [];
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $path = $file->store('complaint_attachments', 'public');
                $attachments[] = $path;
            }
        }

        // Create the complaint
        $complaint = Complaint::create([
            'user_id' => Auth::id(),
            'category_id' => $request->category_id,
            'status_id' => $defaultStatus->id,
            'subject' => $request->subject,
            'description' => $request->description,
            'attachments' => $attachments,
            'is_anonymous' => $request->is_anonymous ?? false,
            'is_resolved' => false,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Complaint created successfully',
            'data' => $complaint->load(['category', 'status'])
        ], 201);
    }

    /**
     * Display the specified complaint.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $complaint = Complaint::with([
            'category', 
            'status', 
            'user:id,name,email,student_id,department',
            'responses' => function ($query) use ($user) {
                // If user is a student, only show non-private responses
                if ($user->isStudent()) {
                    $query->where('is_private', false);
                }
                $query->with('user:id,name,email,role');
            }
        ])->findOrFail($id);

        // Check if user has permission to view this complaint
        if ($user->isStudent() && $complaint->user_id !== $user->id) {
            return response()->json([
                'status' => false,
                'message' => 'You do not have permission to view this complaint'
            ], 403);
        }

        return response()->json([
            'status' => true,
            'message' => 'Complaint retrieved successfully',
            'data' => $complaint
        ]);
    }

    /**
     * Update the specified complaint.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $complaint = Complaint::findOrFail($id);

        // Check if user has permission to update this complaint
        if ($user->isStudent() && $complaint->user_id !== $user->id) {
            return response()->json([
                'status' => false,
                'message' => 'You do not have permission to update this complaint'
            ], 403);
        }

        // Students can only update their own complaints and only if not resolved
        if ($user->isStudent() && $complaint->is_resolved) {
            return response()->json([
                'status' => false,
                'message' => 'Cannot update a resolved complaint'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'category_id' => 'sometimes|exists:complaint_categories,id',
            'status_id' => 'sometimes|exists:complaint_statuses,id',
            'subject' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'is_anonymous' => 'sometimes|boolean',
            'is_resolved' => 'sometimes|boolean',
            'attachments.*' => 'nullable|file|mimes:jpeg,png,jpg,pdf,doc,docx|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        // Students can't change status or resolution status
        if ($user->isStudent()) {
            $request->request->remove('status_id');
            $request->request->remove('is_resolved');
        }

        // Handle file uploads if any
        if ($request->hasFile('attachments')) {
            $attachments = $complaint->attachments ?? [];
            foreach ($request->file('attachments') as $file) {
                $path = $file->store('complaint_attachments', 'public');
                $attachments[] = $path;
            }
            $complaint->attachments = $attachments;
        }

        // Update resolution timestamp if being marked as resolved
        if ($request->has('is_resolved') && $request->is_resolved && !$complaint->is_resolved) {
            $complaint->resolved_at = now();
        }

        $complaint->update($request->except(['attachments']));

        return response()->json([
            'status' => true,
            'message' => 'Complaint updated successfully',
            'data' => $complaint->load(['category', 'status'])
        ]);
    }

    /**
     * Remove the specified complaint.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $complaint = Complaint::findOrFail($id);

        // Only admins or the complaint owner can delete a complaint
        if (!$user->isAdmin() && $complaint->user_id !== $user->id) {
            return response()->json([
                'status' => false,
                'message' => 'You do not have permission to delete this complaint'
            ], 403);
        }

        // Delete associated attachments
        if (!empty($complaint->attachments)) {
            foreach ($complaint->attachments as $attachment) {
                Storage::disk('public')->delete($attachment);
            }
        }

        $complaint->delete();

        return response()->json([
            'status' => true,
            'message' => 'Complaint deleted successfully'
        ]);
    }
}