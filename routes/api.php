<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ClientController;
use App\Http\Controllers\Api\AppointmentController;
use App\Http\Controllers\Api\ReminderController;
use App\Http\Controllers\Api\AnalyticsController;
use App\Http\Controllers\Api\Admin\AdminAppointmentController;
use App\Http\Controllers\Api\Admin\AdminUserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Public authentication routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Protected authentication routes
Route::middleware('auth:sanctum')->group(function () {
    // Authentication
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);
    
    // Client Management (Users manage their own clients)
    Route::apiResource('clients', ClientController::class);
    
    // Appointment Management (Users manage their own appointments)
    Route::apiResource('appointments', AppointmentController::class);
    
    // Appointment Status Management (Bonus Feature)
    Route::patch('/appointments/{appointment}/status', [AppointmentController::class, 'updateStatus']);
    
    // Reminder Viewing
    Route::get('/reminders', [ReminderController::class, 'index']);
    Route::get('/appointments/{appointment}/reminders', [ReminderController::class, 'appointmentReminders']);
    
    // Analytics (Bonus Feature)
    Route::get('/analytics', [AnalyticsController::class, 'index']);
    Route::get('/analytics/reminders', [AnalyticsController::class, 'reminderStats']);
});

// Admin Panel Routes (Bonus Feature)
Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')->group(function () {
    // Admin Appointment Management
    Route::get('/appointments', [AdminAppointmentController::class, 'index']);
    Route::get('/appointments/stats', [AdminAppointmentController::class, 'stats']);
    Route::get('/appointments/{appointment}', [AdminAppointmentController::class, 'show']);
    Route::patch('/appointments/{appointment}/status', [AdminAppointmentController::class, 'updateStatus']);
    Route::delete('/appointments/{appointment}', [AdminAppointmentController::class, 'destroy']);
    
    // Admin User Management
    Route::get('/users', [AdminUserController::class, 'index']);
    Route::get('/users/stats', [AdminUserController::class, 'stats']);
    Route::get('/users/{user}', [AdminUserController::class, 'show']);
    Route::put('/users/{user}', [AdminUserController::class, 'update']);
    Route::patch('/users/{user}/toggle-admin', [AdminUserController::class, 'toggleAdmin']);
    Route::delete('/users/{user}', [AdminUserController::class, 'destroy']);
});

// Example protected route for testing
Route::middleware('auth:sanctum')->get('/test', function (Request $request) {
    return response()->json([
        'success' => true,
        'message' => 'API is working',
        'user' => $request->user()->name,
        'is_admin' => $request->user()->isAdmin()
    ]);
}); 