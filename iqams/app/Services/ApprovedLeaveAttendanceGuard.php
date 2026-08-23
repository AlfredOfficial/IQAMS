<?php

namespace App\Services;

use App\Models\LeaveRequest;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class ApprovedLeaveAttendanceGuard
{
    public const DENIED_MESSAGE = 'Attendance cannot be recorded because this person is on approved leave for the selected date.';

    public function ensureAttendanceIsAllowed(User $user, Carbon $attendanceAt, string $errorKey = 'attendance'): void
    {
        $role = strtolower($user->role?->role_name ?? '');

        if (! in_array($role, ['instructor', 'staff'], true)) {
            return;
        }

        $date = $attendanceAt->copy()->timezone(config('app.timezone'))->toDateString();
        $hasApprovedLeave = LeaveRequest::where('user_id', $user->id)
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->exists();

        if ($hasApprovedLeave) {
            throw ValidationException::withMessages([$errorKey => self::DENIED_MESSAGE]);
        }
    }
}
