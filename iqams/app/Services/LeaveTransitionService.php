<?php

namespace App\Services;

use App\Models\AttendanceLog;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Notifications\LeaveRequestNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

class LeaveTransitionService
{
    public function transition(LeaveRequest $target, string $status, User $actor, ?string $notes = null, ?Request $request = null): LeaveRequest
    {
        abort_unless(in_array($status, ['approved', 'rejected', 'cancelled'], true), 422);

        return DB::transaction(function () use ($target, $status, $actor, $notes, $request) {
            User::whereKey($target->user_id)->lockForUpdate()->firstOrFail();
            $leave = LeaveRequest::whereKey($target->id)->lockForUpdate()->firstOrFail();
            abort_unless($leave->user_id === $target->user_id, 422);
            if ($status === 'cancelled') {
                abort_unless($leave->user_id === $actor->id && ($actor->isInstructor() || $actor->isStaff()), 403);
            }
            abort_unless($leave->status === 'pending', 422, 'Only pending requests can be changed.');

            $overlaps = app(LeaveOverlapService::class);
            $overlaps->lockUserRows($leave->user_id);
            if ($status === 'approved') {
                if ($overlaps->hasConflict($leave->user_id, $leave->start_date->toDateString(), $leave->end_date->toDateString(), $leave->id)) {
                    throw ValidationException::withMessages(['status' => 'This leave overlaps another pending or approved request. Resolve the leave overlap first.']);
                }
                if (AttendanceLog::canonical()->where('user_id', $leave->user_id)->whereNull('schedule_id')
                    ->whereBetween('scan_time', [$leave->start_date->copy()->startOfDay(), $leave->end_date->copy()->endOfDay()])->exists()) {
                    throw ValidationException::withMessages(['status' => 'This leave cannot be approved because attendance already exists within the requested dates. Reconcile those attendance records first.']);
                }
            }

            $leave->update($status === 'cancelled' ? ['status' => $status] : [
                'status' => $status, 'review_notes' => $notes, 'reviewed_by' => $actor->id, 'reviewed_at' => now(),
            ]);
            app(AuditLogger::class)->record($status === 'cancelled' ? 'leave.cancelled' : 'leave.reviewed', $leave,
                $status === 'cancelled' ? [] : ['status' => $status], $actor, $request);
            DB::afterCommit(function () use ($leave, $status) {
                $leave->user->notify(new LeaveRequestNotification($leave, $status));
                if ($status === 'cancelled') {
                    Notification::send(User::whereHas('roles', fn ($q) => $q->where('name', 'admin')->where('guard_name', 'web'))->get(), new LeaveRequestNotification($leave, $status));
                }
            });

            return $leave;
        });
    }
}
