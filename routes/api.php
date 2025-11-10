<?php

use App\Http\Controllers\API\AdminController;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\CategoryController;
use App\Http\Controllers\API\ComplaintController;
use App\Http\Controllers\API\ComplaintResponseController;
use App\Http\Controllers\API\StatusController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Public routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    // User routes
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('complaint', [ComplaintController::class, 'index']);
    // Complaint routes
    Route::apiResource('complaints', ComplaintController::class);

    // Complaint responses routes
    Route::apiResource('complaints.responses', ComplaintResponseController::class);

    // Categories routes (read-only for non-admins)
    Route::get('/categories', [CategoryController::class, 'index']);
    Route::get('/categories/{id}', [CategoryController::class, 'show']);

    // Statuses routes (read-only for non-admins)
    Route::get('/statuses', [StatusController::class, 'index']);
    Route::get('/statuses/{id}', [StatusController::class, 'show']);

    // Admin routes
    Route::middleware('role:admin,superadmin')->prefix('admin')->group(function () {
        // Dashboard
        Route::get('/dashboard', [AdminController::class, 'dashboard']);

        // User management
        Route::get('/users', [AdminController::class, 'users']);
        Route::post('/users', [AdminController::class, 'createUser']);
        Route::put('/users/{id}', [AdminController::class, 'updateUser']);
        Route::delete('/users/{id}', [AdminController::class, 'deleteUser']);

        // Reports
        Route::get('/reports', [AdminController::class, 'reports']);

        // Category management (create, update, delete)
        Route::post('/categories', [CategoryController::class, 'store']);
        Route::put('/categories/{id}', [CategoryController::class, 'update']);
        Route::delete('/categories/{id}', [CategoryController::class, 'destroy']);

        // Status management (create, update, delete)
        Route::post('/statuses', [StatusController::class, 'store']);
        Route::put('/statuses/{id}', [StatusController::class, 'update']);
        Route::delete('/statuses/{id}', [StatusController::class, 'destroy']);
    });

    // SuperAdmin routes
    Route::middleware('role:superadmin')->prefix('superadmin')->group(function () {
        // System statistics
        Route::get('/stats', [\App\Http\Controllers\API\SuperAdminController::class, 'systemStats']);

        // System settings
        Route::get('/settings', [\App\Http\Controllers\API\SuperAdminController::class, 'getSettings']);
        Route::post('/settings', [\App\Http\Controllers\API\SuperAdminController::class, 'updateSettings']);

        // Maintenance mode
        Route::post('/maintenance', [\App\Http\Controllers\API\SuperAdminController::class, 'toggleMaintenanceMode']);

        // System logs
        Route::get('/logs', [\App\Http\Controllers\API\SuperAdminController::class, 'getLogs']);
        Route::delete('/logs', [\App\Http\Controllers\API\SuperAdminController::class, 'clearLogs']);

        // Advanced user management
        Route::put('/users/{id}/role', [\App\Http\Controllers\API\SuperAdminController::class, 'updateUserRole']);
        Route::post('/users/bulk-delete', [\App\Http\Controllers\API\SuperAdminController::class, 'bulkDeleteUsers']);

        // Data export
        Route::post('/export', [\App\Http\Controllers\API\SuperAdminController::class, 'exportData']);
    });

    // Staff routes
    Route::middleware('role:admin,staff,superadmin')->prefix('staff')->group(function () {
        // Staff-specific routes can be added here
    });
});

