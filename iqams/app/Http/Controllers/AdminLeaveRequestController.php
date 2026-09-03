<?php

namespace App\Http\Controllers;

use App\Models\AttendanceLog;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Notifications\LeaveRequestNotification;
use App\Services\AuditLogger;
use App\Services\LeaveOverlapService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class AdminLeaveRequestController extends Controller
{
    public function index(Request $request)
    {
        $query = LeaveRequest::with(['user.roles', 'reviewer'])->latest();
        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }
        $requests = $query->paginate(15)->withQueryString();

        return view('admin.leave-requests.index', compact('requests'));
    }

    public function update(Request $request, LeaveRequest $leaveRequest)
    {
        $validated = $request->validate(['status' => 'required|in:approved,rejected', 'review_notes' => 'nullable|string|max:2000']);
        abort_unless($leaveRequest->status === 'pending', 422, 'Only pending requests can be reviewed.');

        DB::transaction(function () use ($leaveRequest, $validated, $request) {
            User::whereKey($leaveRequest->user_id)->lockForUpdate()->firstOrFail();
            $overlapService = app(LeaveOverlapService::class);
            $overlapService->lockUserRows($leaveRequest->user_id);

            if ($validated['status'] === 'approved') {
                if ($overlapService->hasConflict(
                    $leaveRequest->user_id,
                    $leaveRequest->start_date->toDateString(),
                    $leaveRequest->end_date->toDateString(),
                    $leaveRequest->id,
                )) {
                    throw ValidationException::withMessages([
                        'status' => 'This leave overlaps another pending or approved request. Resolve the leave overlap first.',
                    ]);
                }

                $hasAttendance = AttendanceLog::canonical()->where('user_id', $leaveRequest->user_id)
                    ->whereNull('schedule_id')
                    ->whereBetween('scan_time', [
                        $leaveRequest->start_date->copy()->startOfDay(),
                        $leaveRequest->end_date->copy()->endOfDay(),
                    ])
                    ->exists();

                if ($hasAttendance) {
                    throw ValidationException::withMessages([
                        'status' => 'This leave cannot be approved because attendance already exists within the requested dates. Reconcile those attendance records first.',
                    ]);
                }
            }

            $leaveRequest->update($validated + ['reviewed_by' => $request->user()->id, 'reviewed_at' => now()]);
            app(AuditLogger::class)->record('leave.reviewed', $leaveRequest, [
                'status' => $validated['status'],
            ], $request->user(), $request);
        });

        $leaveRequest->user->notify(new LeaveRequestNotification($leaveRequest->fresh(), $validated['status']));

        return back()->with('success', 'Leave request '.$validated['status'].'.');
    }

    public function attachment(LeaveRequest $leaveRequest)
    {
        abort_unless($leaveRequest->attachment_path && Storage::exists($leaveRequest->attachment_path), 404);

        return Storage::download($leaveRequest->attachment_path);
    }
}
