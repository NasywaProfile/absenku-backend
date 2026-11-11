<?php

use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\EmailController;
use App\Http\Controllers\LeaveController;
use App\Http\Controllers\OfficeConfigController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\UploadController;
use App\Http\Middleware\EnsureUserIsActive;
use Illuminate\Support\Facades\Route;

// Public Authentication Routes (dilengkapi rate-limiting anti brute-force)
Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:10,1');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:15,1');
Route::get('/login', function () {
    return response()->json(['message' => 'Unauthenticated.'], 401);
})->name('login');

// Authenticated Routes (diizinkan juga untuk akun PENDING_APPROVAL saat onboarding & cek status)
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::get('/user-status', [AuthController::class, 'checkUserStatus']);
    Route::match(['put', 'post'], '/profile', [AuthController::class, 'updateProfile']);
    Route::post('/logout', [AuthController::class, 'logout']);
});

// Authenticated + Active Account Routes (hanya untuk pengguna dengan status ACTIVE)
Route::middleware(['auth:sanctum', EnsureUserIsActive::class])->group(function () {
    // Upload Berkas (terautentikasi & tervalidasi MIME/ekstensi/ukuran)
    Route::post('/upload', [UploadController::class, 'upload'])->middleware('throttle:30,1');
    Route::post('/upload/document', [UploadController::class, 'upload'])->middleware('throttle:30,1');

    // Pengiriman Email Notifikasi (terautentikasi, rate-limited, & anti open-relay)
    Route::post('/send-email', [EmailController::class, 'sendEmail'])->middleware('throttle:20,1');

    // Manajemen Pengguna & RBAC (otorisasi peran diverifikasi di AuthController)
    Route::get('/users', [AuthController::class, 'getUsers']);
    Route::match(['put', 'post'], '/users/{id}/approve', [AuthController::class, 'approveUser']);
    Route::match(['put', 'post'], '/users/{id}/role', [AuthController::class, 'updateUserRole']);
    Route::match(['put', 'post'], '/users/{id}/employment-type', [AuthController::class, 'updateEmploymentType']);
    Route::post('/users/{id}/reject', [AuthController::class, 'rejectUser']);
    Route::delete('/users/{id}', [AuthController::class, 'deleteUser']);

    // Konfigurasi Kantor & Geofence
    Route::get('/office-config', [OfficeConfigController::class, 'show']);
    Route::put('/office-config', [OfficeConfigController::class, 'update']);

    // Presensi (perhitungan Haversine & keterlambatan otoritatif di server)
    Route::get('/attendance', [AttendanceController::class, 'index']);
    Route::get('/attendance/today', [AttendanceController::class, 'today']);
    Route::post('/attendance', [AttendanceController::class, 'store']);
    Route::delete('/attendance', [AttendanceController::class, 'destroy']);
    Route::delete('/attendance/today', [AttendanceController::class, 'destroy']);

    // Pengajuan Cuti / Izin / WFH / Dinas Luar
    Route::get('/leaves', [LeaveController::class, 'index']);
    Route::post('/leaves', [LeaveController::class, 'store']);
    Route::post('/leaves/{id}/approve', [LeaveController::class, 'approve']);
    Route::post('/leaves/{id}/reject', [LeaveController::class, 'reject']);
    Route::delete('/leaves/{id}', [LeaveController::class, 'destroy']);

    // Logbook Harian & Tugas Proyek (Kanban)
    Route::get('/daily-logs', [TaskController::class, 'getDailyLogs']);
    Route::post('/daily-logs', [TaskController::class, 'saveDailyLog']);
    Route::get('/kanban-tasks', [TaskController::class, 'getKanbanTasks']);
    Route::post('/kanban-tasks', [TaskController::class, 'storeKanbanTask']);
    Route::match(['put', 'post'], '/kanban-tasks/{id}', [TaskController::class, 'updateKanbanTask']);
    Route::delete('/kanban-tasks/{id}', [TaskController::class, 'deleteKanbanTask']);

    // Ruang Diskusi & Obrolan Tim
    Route::get('/chat/topics', [ChatController::class, 'getTopics']);
    Route::post('/chat/topics', [ChatController::class, 'storeTopic']);
    Route::match(['put', 'post'], '/chat/topics/{id}', [ChatController::class, 'updateTopic']);
    Route::delete('/chat/topics/{id}', [ChatController::class, 'deleteTopic']);
    Route::get('/chat/messages', [ChatController::class, 'getMessages']);
    Route::post('/chat/messages', [ChatController::class, 'sendMessage']);
    Route::delete('/chat/messages/{id}', [ChatController::class, 'deleteMessage']);
});
