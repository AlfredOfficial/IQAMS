<?php

use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\AdminLeaveRequestController;
use App\Http\Controllers\AttendanceLogController;
use App\Http\Controllers\AttendanceScannerController;
use App\Http\Controllers\CourseController;
use App\Http\Controllers\DailyPersonnelAttendanceReportController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\IdCardController;
use App\Http\Controllers\InstructorAttendanceController;
use App\Http\Controllers\InstructorController;
use App\Http\Controllers\InstructorDashboardController;
use App\Http\Controllers\LeaveNotificationController;
use App\Http\Controllers\LeaveRequestController;
use App\Http\Controllers\MyProfileController;
use App\Http\Controllers\NonTeachingStaffController;
use App\Http\Controllers\OfficeUnitController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\ScannerSecurityController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\SchoolEventController;
use App\Http\Controllers\SectionController;
use App\Http\Controllers\StaffDashboardController;
use App\Http\Controllers\StaffAttendanceController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\StudentDashboardController;
use App\Http\Controllers\StudentProfileController;
use App\Http\Controllers\StudentRecordsController;
use App\Http\Controllers\SubjectController;
use App\Http\Controllers\UserAccountStatusController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::get('/dashboard', function () {
    return match (request()->user()->getRoleNames()->first()) {
        'admin' => redirect()->route('admin.dashboard'),
        'instructor' => redirect()->route('instructor.dashboard'),
        'student' => redirect()->route('student.dashboard'),
        'staff' => redirect()->route('staff.dashboard'),
        default => abort(403, 'No valid role assigned to this account.'),
    };
})->middleware(['auth', 'active'])->name('dashboard');

Route::middleware(['auth', 'active', 'redirect.non-admin.profile', 'role:admin'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::middleware(['auth', 'active', 'role:instructor|staff|student'])->group(function () {
    Route::get('/my-id-card', [IdCardController::class, 'show'])->name('id-card.show');
    Route::get('/my-profile', [MyProfileController::class, 'edit'])->name('my-profile.edit');
    Route::patch('/my-profile', [MyProfileController::class, 'update'])->name('my-profile.update');
    Route::get('/leave-requests', [LeaveRequestController::class, 'index'])->name('leave-requests.index');
    Route::post('/leave-requests', [LeaveRequestController::class, 'store'])->name('leave-requests.store');
    Route::patch('/leave-requests/{leaveRequest}/cancel', [LeaveRequestController::class, 'cancel'])->name('leave-requests.cancel');
});

Route::post('/leave-notifications/read', [LeaveNotificationController::class, 'read'])
    ->middleware(['auth', 'active'])->name('leave-notifications.read');

Route::middleware(['auth', 'active', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('dashboard', [AdminDashboardController::class, 'index'])->middleware('permission:view-reports')->name('dashboard');
    Route::get('dashboard/realtime', [AdminDashboardController::class, 'realtime'])->middleware('permission:view-reports')->name('dashboard.realtime');
    Route::middleware('permission:view-reports')->prefix('reports/daily-personnel')->name('reports.daily-personnel.')->group(function () {
        Route::get('/', [DailyPersonnelAttendanceReportController::class, 'index'])->name('index');
        Route::get('/pdf', [DailyPersonnelAttendanceReportController::class, 'pdf'])->name('pdf');
        Route::get('/excel', [DailyPersonnelAttendanceReportController::class, 'excel'])->name('excel');
    });
    Route::get('leave-requests', [AdminLeaveRequestController::class, 'index'])->middleware('permission:review-leave-requests')->name('leave-requests.index');
    Route::get('leave-requests/{leaveRequest}/attachment', [AdminLeaveRequestController::class, 'attachment'])->middleware('permission:review-leave-requests')->name('leave-requests.attachment');
    Route::patch('leave-requests/{leaveRequest}', [AdminLeaveRequestController::class, 'update'])->middleware('permission:review-leave-requests')->name('leave-requests.update');
});

Route::middleware(['auth', 'active', 'role:admin'])->group(function () {
    Route::get('attendance-scanner', [AttendanceScannerController::class, 'index'])->middleware('permission:operate-scanner')->name('attendance-scanner.index');
    Route::post('attendance-scanner/terminal', [AttendanceScannerController::class, 'selectTerminal'])->middleware('permission:operate-scanner')->name('attendance-scanner.terminal');
    Route::middleware(['scanner.terminal', 'permission:operate-scanner'])->group(function () {
        Route::post('attendance-scanner/scan', [AttendanceScannerController::class, 'scan'])->middleware('scanner.throttle:scan')->name('attendance-scanner.scan');
    });
    Route::middleware('permission:manage-scanner-security')->group(function () {
        Route::get('scanner-security', [ScannerSecurityController::class, 'index'])->name('scanner-security.index');
        Route::post('scanner-security/terminals', [ScannerSecurityController::class, 'storeTerminal'])->name('scanner-security.terminals.store');
        Route::patch('scanner-security/terminals/{terminal}', [ScannerSecurityController::class, 'updateTerminal'])->name('scanner-security.terminals.update');
        Route::post('scanner-security/users/{user}/qr/regenerate', [ScannerSecurityController::class, 'regenerate'])->name('scanner-security.qr.regenerate');
        Route::patch('scanner-security/flags/{flag}', [ScannerSecurityController::class, 'reviewFlag'])->name('scanner-security.flags.update');
    });
    Route::resource('departments', DepartmentController::class)->middleware('permission:manage-academic-structure');
    Route::resource('courses', CourseController::class)->middleware('permission:manage-academic-structure');
    Route::resource('instructors', InstructorController::class)->except(['create', 'edit', 'show'])->middleware('permission:manage-users');
    Route::get('roles', [RoleController::class, 'index'])->middleware('permission:manage-role-assignments')->name('roles.index');
    Route::patch('roles/users/{user}', [RoleController::class, 'assign'])->middleware('permission:manage-role-assignments')->name('roles.assign');
    Route::resource('non-teaching-staff', NonTeachingStaffController::class)->except(['create', 'edit', 'show'])->middleware('permission:manage-users');
    Route::resource('office-units', OfficeUnitController::class)->only(['index', 'store', 'update', 'destroy'])->middleware('permission:manage-office-units');
    Route::resource('subjects', SubjectController::class)->except('create', 'edit', 'show')->middleware('permission:manage-academic-structure');
    Route::resource('sections', SectionController::class)->except(['create', 'edit', 'show'])->middleware('permission:manage-academic-structure');
    Route::resource('students', StudentController::class)->except(['create', 'edit', 'show'])->middleware('permission:manage-users');
    Route::resource('schedules', ScheduleController::class)->except(['create', 'edit', 'show'])->middleware('permission:manage-schedules');
    Route::resource('attendance-logs', AttendanceLogController::class)->except(['create', 'edit', 'show'])->middleware('permission:manage-attendance');
    Route::resource('school-events', SchoolEventController::class)
        ->parameters(['school-events' => 'schoolEvent'])->except(['show', 'create', 'edit'])->middleware('permission:manage-school-events');
    Route::patch('school-events/{schoolEvent}/publish', [SchoolEventController::class, 'publish'])->middleware('permission:manage-school-events')->name('school-events.publish');
    Route::patch('school-events/{schoolEvent}/cancel', [SchoolEventController::class, 'cancel'])->middleware('permission:manage-school-events')->name('school-events.cancel');
    Route::patch('users/{user}/status', [UserAccountStatusController::class, 'update'])->middleware('permission:manage-users')->name('users.status.update');
});

Route::middleware(['auth', 'active', 'role:student'])->prefix('student')->name('student.')->group(function () {
    Route::get('dashboard', [StudentDashboardController::class, 'index'])->name('dashboard');
    Route::get('dashboard/realtime', [StudentDashboardController::class, 'realtime'])->name('dashboard.realtime');
    Route::get('profile', [StudentProfileController::class, 'show'])->name('profile');
    Route::patch('profile/contact', [StudentProfileController::class, 'updateContact'])->name('profile.contact');
    Route::put('profile/photo', [StudentProfileController::class, 'updatePhoto'])->name('profile.photo');
    Route::delete('profile/photo', [StudentProfileController::class, 'removePhoto'])->name('profile.photo.destroy');
    Route::get('attendance', [StudentRecordsController::class, 'attendance'])->name('attendance');
    Route::get('qr-code', [StudentRecordsController::class, 'qr'])->name('qr');
    Route::get('settings', [StudentProfileController::class, 'settings'])->name('settings');
    Route::put('settings/password', [StudentProfileController::class, 'updatePassword'])->name('password');
});

Route::middleware(['auth', 'active', 'role:staff'])->prefix('staff')->name('staff.')->group(function () {
    Route::get('dashboard', [StaffDashboardController::class, 'index'])->name('dashboard');
    Route::get('dashboard/realtime', [StaffDashboardController::class, 'realtime'])->name('dashboard.realtime');
    Route::get('attendance/history', [StaffAttendanceController::class, 'history'])->name('attendance.history');
    Route::get('attendance/summary', [StaffAttendanceController::class, 'summary'])->name('attendance.summary');
    Route::get('attendance/issues', [StaffAttendanceController::class, 'issues'])->name('attendance.issues');
    Route::get('profile', [MyProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('profile', [MyProfileController::class, 'update'])->name('profile.update');
    Route::get('leave-requests', [LeaveRequestController::class, 'index'])->name('leave-requests.index');
    Route::post('leave-requests', [LeaveRequestController::class, 'store'])->name('leave-requests.store');
    Route::patch('leave-requests/{leaveRequest}/cancel', [LeaveRequestController::class, 'cancel'])->name('leave-requests.cancel');
});

Route::middleware(['auth', 'active', 'role:instructor'])->prefix('instructor')->name('instructor.')->group(function () {
    Route::get('dashboard', [InstructorDashboardController::class, 'index'])->name('dashboard');
    Route::get('dashboard/realtime', [InstructorDashboardController::class, 'realtime'])->name('dashboard.realtime');
    Route::get('attendance', [InstructorAttendanceController::class, 'history'])->name('attendance');
    Route::get('history', [InstructorAttendanceController::class, 'history'])->name('history');
    Route::get('summary', [InstructorAttendanceController::class, 'summary'])->name('summary');
    Route::get('issues', [InstructorAttendanceController::class, 'issues'])->name('issues');
    Route::get('schedule', [InstructorAttendanceController::class, 'schedule'])->name('schedule');
    Route::get('schedule/{schedule}/attendance', [InstructorAttendanceController::class, 'classAttendance'])->name('schedule.attendance');
});

require __DIR__.'/auth.php';
