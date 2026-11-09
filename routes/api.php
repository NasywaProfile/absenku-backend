<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\LeaveController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\EmailController;
use App\Http\Controllers\OfficeConfigController;
use Illuminate\Support\Facades\Route;

// Public Routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::get('/office-config', [OfficeConfigController::class, 'show']);
Route::get('/user-status', [AuthController::class, 'checkUserStatus']);
Route::match(['put', 'post'], '/profile/direct', [AuthController::class, 'updateProfile']);
Route::get('/users', [AuthController::class, 'getUsers']);
Route::match(['put', 'post'], '/users/{id}/approve', [AuthController::class, 'approveUser']);
Route::match(['put', 'post'], '/users/{id}/approve/direct', [AuthController::class, 'approveUser']);
Route::match(['put', 'post'], '/users/{id}/role', [AuthController::class, 'updateUserRole']);
Route::get('/repair-superadmin', [AuthController::class, 'repairSuperAdmin']);
Route::post('/send-email', [EmailController::class, 'sendEmail']);
Route::get('/test-email', [EmailController::class, 'testEmail']);

// Authenticated Routes (Sanctum)
Route::middleware('auth:sanctum')->group(function () {
    // Auth & Profile
    Route::get('/me', [AuthController::class, 'me']);
    Route::match(['put', 'post'], '/profile', [AuthController::class, 'updateProfile']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // User Management (Admin / Team)
    Route::get('/users', [AuthController::class, 'getUsers']);
    Route::post('/users/{id}/approve', [AuthController::class, 'approveUser']);
    Route::match(['put', 'post'], '/users/{id}/role', [AuthController::class, 'updateUserRole']);
    Route::post('/users/{id}/reject', [AuthController::class, 'rejectUser']);
    Route::delete('/users/{id}', [AuthController::class, 'deleteUser']);

    // Office Configuration
    Route::put('/office-config', [OfficeConfigController::class, 'update']);

    // Attendance
    Route::get('/attendance', [AttendanceController::class, 'index']);
    Route::get('/attendance/today', [AttendanceController::class, 'today']);
    Route::post('/attendance', [AttendanceController::class, 'store']);

    // Leave / Cuti / Izin / WFH / Dinas
    Route::get('/leaves', [LeaveController::class, 'index']);
    Route::post('/leaves', [LeaveController::class, 'store']);
    Route::post('/leaves/{id}/approve', [LeaveController::class, 'approve']);
    Route::post('/leaves/{id}/reject', [LeaveController::class, 'reject']);
    Route::delete('/leaves/{id}', [LeaveController::class, 'destroy']);

    // Daily Task Logs
    Route::get('/daily-logs', [TaskController::class, 'getDailyLogs']);
    Route::post('/daily-logs', [TaskController::class, 'saveDailyLog']);

    // Kanban Tasks
    Route::get('/kanban-tasks', [TaskController::class, 'getKanbanTasks']);
    Route::post('/kanban-tasks', [TaskController::class, 'storeKanbanTask']);
    Route::put('/kanban-tasks/{id}', [TaskController::class, 'updateKanbanTask']);
    Route::delete('/kanban-tasks/{id}', [TaskController::class, 'deleteKanbanTask']);

    // Chat Rooms & Topics
    Route::get('/chat/topics', [ChatController::class, 'getTopics']);
    Route::post('/chat/topics', [ChatController::class, 'storeTopic']);
    Route::put('/chat/topics/{id}', [ChatController::class, 'updateTopic']);
    Route::delete('/chat/topics/{id}', [ChatController::class, 'deleteTopic']);
    Route::get('/chat/messages', [ChatController::class, 'getMessages']);
    Route::post('/chat/messages', [ChatController::class, 'sendMessage']);
    Route::delete('/chat/messages/{id}', [ChatController::class, 'deleteMessage']);
});
