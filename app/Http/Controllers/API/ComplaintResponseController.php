<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Complaint;
use App\Models\ComplaintResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class ComplaintResponseController extends Controller
{
    /**
     * Display a listing of the responses for a specific complaint.
     */
    public function index(Request $request, string $complaintId): JsonResponse
    {
        $user = $request->user();
        $complaint = Complaint::findOrFail($complaintId);

        // Check if user has permission to view responses for this complaint
        if ($user->isStudent() && $complaint->user_id !== $user->id) {
            return response()->json([
                'status' => false,
                'message' => 'You do not have permission to view responses for this complaint'
            ], 403);
        }

        $query = ComplaintResponse::with('user:id,name,email,role')
            ->where('complaint_id', $complaintId);

        // If user is a student, only show non-private responses
        if ($user->isStudent()) {
            $query->where('is_private', false);
        }

        $responses = $query->orderBy('created_at', 'asc')->get();

        return response()->json([
            'status' => true,
            'message' => 'Responses retrieved successfully',
            'data' => $responses
        ]);
    }

    /**
     * Store a newly created response.
     */
    public function store(Request $request, string $complaintId): JsonResponse
    {
        $user = $request->user();
        $complaint = Complaint::findOrFail($complaintId);

        // Check if user has permission to respond to this complaint
        if ($user->isStudent() && $complaint->user_id !== $user->id) {
            return response()->json([
                'status' => false,
                'message' => 'You do not have permission to respond to this complaint'
            ], 403);
        }

        // Students can't create private responses
        if ($user->isStudent() && $request->input('is_private', false)) {
            return response()->json([
                'status' => false,
                'message' => 'Students cannot create private responses'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'response' => 'required|string',
            'is_private' => 'boolean',
            'attachments.*' => 'nullable|file|mimes:jpeg,png,jpg,pdf,doc,docx|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        // Handle file uploads
        $attachments = [];
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $path = $file->store('response_attachments', 'public');
                $attachments[] = $path;
            }
        }

        // Create the response
        $response = ComplaintResponse::create([
            'complaint_id' => $complaintId,
            'user_id' => $user->id,
            'response' => $request->response,
            'attachments' => $attachments,
            'is_private' => $user->isStudent() ? false : $request->input('is_private', false),
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Response created successfully',
            'data' => $response->load('user:id,name,email,role')
        ], 201);
    }

    /**
     * Display the specified response.
     */
    public function show(Request $request, string $complaintId, string $responseId): JsonResponse
    {
        $user = $request->user();
        $complaint = Complaint::findOrFail($complaintId);
        $response = ComplaintResponse::with('user:id,name,email,role')
            ->where('complaint_id', $complaintId)
            ->findOrFail($responseId);

        // Check if user has permission to view this response
        if ($user->isStudent()) {
            if ($complaint->user_id !== $user->id) {
                return response()->json([
                    'status' => false,
                    'message' => 'You do not have permission to view this response'
                ], 403);
            }

            if ($response->is_private) {
                return response()->json([
                    'status' => false,
                    'message' => 'You do not have permission to view this private response'
                ], 403);
            }
        }

        return response()->json([
            'status' => true,
            'message' => 'Response retrieved successfully',
            'data' => $response
        ]);
    }

    /**
     * Update the specified response.
     */
    public function update(Request $request, string $complaintId, string $responseId): JsonResponse
    {
        $user = $request->user();
        $response = ComplaintResponse::where('complaint_id', $complaintId)
            ->findOrFail($responseId);

        // Only the response creator can update it
        if ($response->user_id !== $user->id) {
            return response()->json([
                'status' => false,
                'message' => 'You do not have permission to update this response'
            ], 403);
        }

        // Students can't make responses private
        if ($user->isStudent() && $request->input('is_private', false)) {
            return response()->json([
                'status' => false,
                'message' => 'Students cannot create private responses'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'response' => 'sometimes|string',
            'is_private' => 'sometimes|boolean',
            'attachments.*' => 'nullable|file|mimes:jpeg,png,jpg,pdf,doc,docx|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        // Handle file uploads if any
        if ($request->hasFile('attachments')) {
            $attachments = $response->attachments ?? [];
            foreach ($request->file('attachments') as $file) {
                $path = $file->store('response_attachments', 'public');
                $attachments[] = $path;
            }
            $response->attachments = $attachments;
        }

        // Update the response
        $response->update([
            'response' => $request->input('response', $response->response),
            'is_private' => $user->isStudent() ? false : $request->input('is_private', $response->is_private),
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Response updated successfully',
            'data' => $response->load('user:id,name,email,role')
        ]);
    }

    /**
     * Remove the specified response.
     */
    public function destroy(Request $request, string $complaintId, string $responseId): JsonResponse
    {
        $user = $request->user();
        $response = ComplaintResponse::where('complaint_id', $complaintId)
            ->findOrFail($responseId);

        // Only the response creator or an admin can delete it
        if (!$user->isAdmin() && $response->user_id !== $user->id) {
            return response()->json([
                'status' => false,
                'message' => 'You do not have permission to delete this response'
            ], 403);
        }

        // Delete associated attachments
        if (!empty($response->attachments)) {
            foreach ($response->attachments as $attachment) {
                Storage::disk('public')->delete($attachment);
            }
        }

        $response->delete();

        return response()->json([
            'status' => true,
            'message' => 'Response deleted successfully'
        ]);
    }
}