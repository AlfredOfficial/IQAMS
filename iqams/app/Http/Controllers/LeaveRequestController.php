<?php

namespace App\Http\Controllers;

use App\Models\LeaveRequest as LeaveRequestModel;
use App\Models\User;
use App\Notifications\LeaveRequestNotification;
use App\Services\AuditLogger;
use App\Services\LeaveOverlapService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

class LeaveRequestController extends Controller
{
    public function index(Request $request)
    {
        $this->ensureLeaveAccess($request);

        if ($request->user()->isStaff() && ! $request->routeIs('staff.leave-requests.index')) {
            return redirect()->route('staff.leave-requests.index');
        }

        $requests = $request->user()->leaveRequests()->latest()->paginate(10);

        $view = $request->user()->isStaff()
            ? 'staff.leave-requests.index'
            : 'leave-requests.index';

        return view($view, compact('requests'));
    }

    public function store(Request $request)
    {
        $this->ensureLeaveAccess($request);

        $validated = $request->validate([
            'leave_type' => 'required|in:vacation,sick,emergency,other',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'reason' => 'required|string|max:2000',
            'attachment' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        unset($validated['attachment']);
        if ($request->hasFile('attachment')) {
            $validated['attachment_path'] = $request->file('attachment')->store('leave-attachments');
        }
        $leaveRequest = DB::transaction(function () use ($request, $validated) {
            $service = app(LeaveOverlapService::class);
            User::query()->whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            $service->lockUserRows($request->user()->id);
            if ($service->hasConflict($request->user()->id, $validated['start_date'], $validated['end_date'])) {
                throw ValidationException::withMessages(['start_date' => 'These dates overlap an existing pending or approved request.']);
            }

            return $request->user()->leaveRequests()->create($validated);
        });
        app(AuditLogger::class)->record('leave.submitted', $leaveRequest, [], $request->user(), $request);

        if ($request->user()->isInstructor() || $request->user()->isStaff()) {
            $request->user()->notify(new LeaveRequestNotification($leaveRequest, 'submitted'));
            Notification::send(
                User::whereHas('roles', fn ($query) => $query->where('name', 'admin')->where('guard_name', 'web'))->get(),
                new LeaveRequestNotification($leaveRequest, 'submitted'),
            );
        }

        return back()->with('success', 'Leave request submitted for review.');
    }

    public function cancel(Request $request, LeaveRequestModel $leaveRequest)
    {
        $this->ensureLeaveAccess($request);

        abort_unless($leaveRequest->user_id === $request->user()->id, 403);
        abort_unless($leaveRequest->status === 'pending', 422, 'Only pending requests can be cancelled.');
        $leaveRequest->update(['status' => 'cancelled']);
        app(AuditLogger::class)->record('leave.cancelled', $leaveRequest, [], $request->user(), $request);

        if ($request->user()->isInstructor() || $request->user()->isStaff()) {
            $request->user()->notify(new LeaveRequestNotification($leaveRequest->fresh(), 'cancelled'));
            Notification::send(
                User::whereHas('roles', fn ($query) => $query->where('name', 'admin')->where('guard_name', 'web'))->get(),
                new LeaveRequestNotification($leaveRequest->fresh(), 'cancelled'),
            );
        }

        return back()->with('success', 'Leave request cancelled.');
    }

    private function ensureLeaveAccess(Request $request): void
    {
        abort_unless($request->user()->isInstructor() || $request->user()->isStaff(), 403);
    }
}
