<?php

use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CorrectionRequestController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DemographicsController;
use App\Http\Controllers\ExaminationController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\SchoolController;
use App\Http\Controllers\ScreeningScheduleController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\TestStripStockController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public (no auth)
|--------------------------------------------------------------------------
*/
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');

/*
|--------------------------------------------------------------------------
| Authenticated API
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {
    // Auth
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // Dashboard & analisis (role: dinas_provinsi, agregat saja — spec 6.3 & 6.4)
    Route::get('/dashboard/summary', [DashboardController::class, 'summary']);
    Route::get('/dashboard/reactive-by-region', [DashboardController::class, 'reactiveByRegion']);
    Route::get('/dashboard/reactive-by-parameter', [DashboardController::class, 'reactiveByParameter']);
    Route::get('/dashboard/top-schools', [DashboardController::class, 'topSchools']);
    Route::get('/demographics/by-gender', [DemographicsController::class, 'byGender']);
    Route::get('/demographics/by-grade', [DemographicsController::class, 'byGrade']);
    Route::get('/demographics/cross-table', [DemographicsController::class, 'crossTable']);

    // Ekspor data agregat CSV (role: dinas_provinsi)
    Route::get('/export/{type}', [ExportController::class, 'export']);

    // Sekolah & siswa (lookup NISN untuk auto-fill)
    // Daftar sebelum resource agar /schools/status tidak tertangkap {school}
    Route::get('/schools/status', [SchoolController::class, 'status']);
    Route::apiResource('schools', SchoolController::class)->except(['create', 'edit']);
    Route::get('/schools/{school}/students', [StudentController::class, 'lookup']);

    // Pemeriksaan
    Route::apiResource('examinations', ExaminationController::class)->except(['create', 'edit']);
    Route::post('/examinations/{examination}/correction-request', [ExaminationController::class, 'requestCorrection']);

    // Koreksi (pengawas wilayah)
    Route::apiResource('correction-requests', CorrectionRequestController::class)->except(['create', 'edit']);
    Route::post('/correction-requests/{correction_request}/approve', [CorrectionRequestController::class, 'approve']);
    Route::post('/correction-requests/{correction_request}/reject', [CorrectionRequestController::class, 'reject']);

    // Siswa (selain lookup) — CRUD di bawah scope petugas/pengawas
    Route::apiResource('students', StudentController::class)->except(['create', 'edit']);

    // Jadwal skrining (CRUD Dinas, list petugas)
    Route::apiResource('schedules', ScreeningScheduleController::class)->except(['create', 'edit']);

    // Pengguna & akses (khusus Dinas Provinsi)
    Route::apiResource('users', UserController::class)->except(['create', 'edit']);

    // Stok alat uji
    Route::apiResource('test-strip-stock', TestStripStockController::class)->except(['create', 'edit']);

    // Audit log (khusus Dinas Provinsi)
    Route::apiResource('audit-logs', AuditLogController::class)->only(['index', 'show']);
});
