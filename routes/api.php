<?php

use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\BiometricController;
use App\Http\Controllers\BiometricPushController;
use App\Http\Controllers\CampusController;
use App\Http\Controllers\DtrPrintConsentController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\TrackingController;
use App\Http\Controllers\HolidayController;
use App\Http\Controllers\ShiftController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\AuditController;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\SystemHealthController;
use App\Http\Controllers\AlertController;
use App\Http\Controllers\SystemSettingController;
use App\Http\Controllers\FeaturePermissionController;
use App\Http\Controllers\MyAttendanceController;
use Illuminate\Support\Facades\Route;

// Public — rate-limited to 10 attempts per minute to prevent brute-force
Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

// Public — the login page renders the current branding before anyone is authenticated
Route::get('/system-settings', [SystemSettingController::class, 'show']);

// ── GRANDING/ZKTeco ADMS push receiver (no auth — device hits this directly) ──
Route::get('/iclock/cdata',      [BiometricPushController::class, 'init']);
Route::post('/iclock/cdata',     [BiometricPushController::class, 'push']);
Route::get('/iclock/getrequest', [BiometricPushController::class, 'heartbeat']);
Route::post('/iclock/devicecmd', [BiometricPushController::class, 'ack']);

// Biometric bridge punch — LAN-restricted
Route::post('/attendance/punch', [AttendanceController::class, 'punch'])
    ->middleware(['auth:sanctum', 'lan']);

// Network status check (used by frontend to show LAN badge)
Route::get('/network/status', function (\Illuminate\Http\Request $req) {
    $allowed = array_filter(array_map(
        'trim',
        explode(',', env('LAN_ALLOWED_IPS', '127.0.0.1,::1'))
    ));
    $ip = $req->ip();
    $onLan = false;
    foreach ($allowed as $range) {
        if ($ip === $range || str_starts_with($ip, rtrim($range, '*'))) {
            $onLan = true; break;
        }
    }
    return response()->json(['on_lan' => $onLan, 'ip' => $ip]);
})->middleware('auth:sanctum');

// Protected
Route::middleware('auth:sanctum')->group(function () {

    // Auth
    Route::get('/auth/me',               [AuthController::class, 'me']);
    Route::post('/auth/logout',          [AuthController::class, 'logout']);
    // Throttled — a leaked/stolen token shouldn't let someone brute-force the current password
    Route::post('/auth/change-password', [AuthController::class, 'changePassword'])->middleware('throttle:5,1');
    Route::get('/auth/sessions',                 [AuthController::class, 'sessions']);
    Route::delete('/auth/sessions/{tokenId}',    [AuthController::class, 'revokeSession']);
    Route::post('/auth/sessions/revoke-others',  [AuthController::class, 'revokeOtherSessions']);

    // Attendance
    Route::get('/attendance/today',              [AttendanceController::class, 'today']);
    Route::get('/attendance/history/{employee}', [AttendanceController::class, 'history']);
    Route::get('/attendance/logs',               [AttendanceController::class, 'logs']);
    Route::post('/attendance/logs',              [AttendanceController::class, 'storeLog']);
    Route::delete('/attendance/logs/{log}',      [AttendanceController::class, 'destroyLog']);
    Route::get('/attendance/daily',              [AttendanceController::class, 'daily']);

    // Self-service "Mark as On Leave" — any authenticated user, own record only
    Route::post('/attendance/on-leave',          [AttendanceController::class, 'markOnLeave']);
    Route::post('/attendance/on-leave/unmark',   [AttendanceController::class, 'unmarkOnLeave']);

    // Personal dashboard analytics — always the signed-in user's own records
    Route::get('/me/attendance-summary',         [MyAttendanceController::class, 'summary']);

    // Employees — viewing the full roster is a distinct, revocable permission
    // from managing it (store/update/destroy already require Super Admin,
    // Admin, or HR Officer via requirePrivileged() below).
    Route::middleware('feature:dtr_records.view_all')->group(function () {
        Route::get('/employees',               [EmployeeController::class, 'index']);
        Route::get('/employees/export/excel',  [EmployeeController::class, 'exportExcel']);
        Route::get('/employees/export/pdf',    [EmployeeController::class, 'exportPdf']);
    });
    Route::get('/employees/import/template',          [EmployeeController::class, 'importTemplate']);
    Route::post('/employees/import/preview',          [EmployeeController::class, 'importPreview']);
    Route::post('/employees/import',                  [EmployeeController::class, 'import']);
    Route::post('/employees',                        [EmployeeController::class, 'store']);
    Route::put('/employees/{employee}',              [EmployeeController::class, 'update']);
    Route::delete('/employees/{employee}',           [EmployeeController::class, 'destroy']);
    Route::post('/employees/{employee}/photo',       [EmployeeController::class, 'uploadPhoto']);

    // Shifts — GET lists for dropdowns; POST/PUT/DELETE for CRUD management
    Route::get('/shifts', [EmployeeController::class, 'shifts']);
    Route::middleware('feature:shift_management')->group(function () {
        Route::post('/shifts',           [ShiftController::class, 'store']);
        Route::put('/shifts/{shift}',    [ShiftController::class, 'update']);
        Route::delete('/shifts/{shift}', [ShiftController::class, 'destroy']);
    });

    // Departments — full CRUD
    Route::get('/departments',                 [DepartmentController::class, 'index']);
    Route::post('/departments',                [DepartmentController::class, 'store']);
    Route::put('/departments/{department}',    [DepartmentController::class, 'update']);
    Route::delete('/departments/{department}', [DepartmentController::class, 'destroy']);

    // Campuses — full CRUD (super_admin only for write)
    Route::get('/campuses',              [CampusController::class, 'index']);
    Route::post('/campuses',             [CampusController::class, 'store']);
    Route::put('/campuses/{campus}',     [CampusController::class, 'update']);
    Route::delete('/campuses/{campus}',  [CampusController::class, 'destroy']);

    // Holidays
    Route::get('/holidays', [HolidayController::class, 'index']);
    Route::middleware('feature:holiday_calendar')->group(function () {
        Route::post('/holidays',             [HolidayController::class, 'store']);
        Route::post('/holidays/autofill',    [HolidayController::class, 'autofill']);
        Route::put('/holidays/{holiday}',    [HolidayController::class, 'update']);
        Route::delete('/holidays/{holiday}', [HolidayController::class, 'destroy']);
    });

    // Reports
    Route::get('/reports/form48/{employee}',   [ReportController::class, 'form48']);
    Route::get('/reports/preview/{employee}',  [ReportController::class, 'preview']);
    Route::get('/reports/export/{employee}',   [ReportController::class, 'exportCsv']);
    Route::middleware('feature:dtr_generation.export')->group(function () {
        Route::get('/reports/export-bulk', [ReportController::class, 'exportBulk']);
        Route::get('/reports/deductions',  [ReportController::class, 'exportDeductions']);
    });

    // DTR Print Consent — an employee requests HR/Admin sign-off before they
    // can download an official, printable copy of their own DTR
    Route::get('/dtr-print-consents',                [DtrPrintConsentController::class, 'index']);
    Route::post('/dtr-print-consents',               [DtrPrintConsentController::class, 'store']);
    Route::patch('/dtr-print-consents/{consent}',    [DtrPrintConsentController::class, 'review']);

    // Tracking & analytics — granted by role default (off for employees) and
    // overridable per employee by the Super Admin in Access Control.
    Route::middleware('feature:tracking_analytics')->group(function () {
        Route::get('/tracking/tardiness',       [TrackingController::class, 'tardiness']);
        Route::get('/tracking/bradford-factor', [TrackingController::class, 'bradfordFactor']);
    });

    // Analytics (dashboard charts)
    Route::get('/analytics/trend',       [AnalyticsController::class, 'trend']);
    Route::get('/analytics/departments', [AnalyticsController::class, 'departments']);
    Route::get('/analytics/staff-types', [AnalyticsController::class, 'staffTypes']);
    Route::get('/analytics/campus-comparison', [AnalyticsController::class, 'campusComparison']);

    // Header notification bell — aggregated "needs attention" feed
    Route::get('/alerts', [AlertController::class, 'index']);

    // Audit Trail
    Route::get('/audit-logs', [AuditController::class, 'index']);

    // Feature Permissions — Super Admin: per-employee overrides of role-level feature access
    Route::get('/feature-permissions/catalog',                 [FeaturePermissionController::class, 'catalog']);
    // Bulk (all-employees) routes registered before the {user} wildcard below,
    // since routes match in order and "bulk" would otherwise be swallowed as
    // a {user} id/slug.
    Route::post('/feature-permissions/bulk',                   [FeaturePermissionController::class, 'bulkUpdate']);
    Route::delete('/feature-permissions/bulk/{featureKey}',    [FeaturePermissionController::class, 'bulkReset']);
    Route::get('/feature-permissions/{user}',                  [FeaturePermissionController::class, 'show']);
    Route::post('/feature-permissions',                        [FeaturePermissionController::class, 'update']);
    Route::delete('/feature-permissions/{user}/{featureKey}',  [FeaturePermissionController::class, 'reset']);

    // System Settings (branding) — Super Admin & Admin can update
    Route::post('/system-settings',            [SystemSettingController::class, 'update']);
    Route::post('/system-settings/reset-logo', [SystemSettingController::class, 'resetLogo']);

    // System Health — Super Admin only
    Route::prefix('system-health')->middleware('feature:system_health')->group(function () {
        Route::get('/',                    [SystemHealthController::class, 'index']);
        Route::post('/scan',               [SystemHealthController::class, 'scan']);
        Route::post('/clear-resolved',     [SystemHealthController::class, 'clearResolved']);
        Route::delete('/',                 [SystemHealthController::class, 'clearAll']);
        Route::post('/{log}/acknowledge',  [SystemHealthController::class, 'acknowledge']);
        Route::post('/{log}/resolve',      [SystemHealthController::class, 'resolve']);
        Route::post('/{log}/reopen',       [SystemHealthController::class, 'reopen']);
        Route::delete('/{log}',            [SystemHealthController::class, 'destroy']);
    });

    // Database Backups
    Route::get('/backups',                          [BackupController::class, 'index']);
    Route::post('/backups',                         [BackupController::class, 'create']);
    Route::get('/backups/{filename}/download',      [BackupController::class, 'download'])->where('filename', '.+');
    Route::post('/backups/{filename}/restore',      [BackupController::class, 'restore'])->where('filename', '.+');
    Route::delete('/backups/{filename}',            [BackupController::class, 'destroy'])->where('filename', '.+');

    // Biometric device — admin & HR only
    Route::prefix('biometric')->group(function () {
        Route::get('/status',           [BiometricController::class, 'status']);
        Route::post('/test-connection', [BiometricController::class, 'testConnection']);
        Route::post('/sync',            [BiometricController::class, 'sync']);
        Route::post('/import',          [BiometricController::class, 'import']);
        Route::get('/debug',            [BiometricController::class, 'debug']);
    });
});
