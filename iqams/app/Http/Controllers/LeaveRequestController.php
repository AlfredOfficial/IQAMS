<?php

namespace App\Http\Controllers;

use App\Models\LeaveRequest as LeaveRequestModel;
use App\Models\User;
use App\Notifications\LeaveRequestNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

class LeaveRequestController extends Controller
{
    public function index(Request $request)
    {
        $requests = $request->user()->leaveRequests()->latest()->paginate(10);

        return view('leave-requests.index', compact('requests'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'leave_type' => 'required|in:vacation,sick,emergency,other',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'reason' => 'required|string|max:2000',
            'attachment' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        $overlap = $request->user()->leaveRequests()
            ->whereIn('status', ['pending', 'approved'])
            ->whereDate('start_date', '<=', $validated['end_date'])
            ->whereDate('end_date', '>=', $validated['start_date'])->exists();
        if ($overlap) {
            throw ValidationException::withMessages(['start_date' => 'These dates overlap an existing pending or approved request.']);
        }

        unset($validated['attachment']);
        if ($request->hasFile('attachment')) {
            $validated['attachment_path'] = $request->file('attachment')->store('leave-attachments');
        }
        $leaveRequest = $request->user()->leaveRequests()->create($validated);

        if ($request->user()->isInstructor() || $request->user()->isStaff()) {
            $request->user()->notify(new LeaveRequestNotification($leaveRequest, 'submitted'));
            Notification::send(
                User::whereHas('role', fn ($query) => $query->where('role_name', 'admin'))->get(),
                new LeaveRequestNotification($leaveRequest, 'submitted'),
            );
        }

        return back()->with('success', 'Leave request submitted for review.');
    }

    public function cancel(Request $request, LeaveRequestModel $leaveRequest)
    {
        abort_unless($leaveRequest->user_id === $request->user()->id, 403);
        abort_unless($leaveRequest->status === 'pending', 422, 'Only pending requests can be cancelled.');
        $leaveRequest->update(['status' => 'cancelled']);

        if ($request->user()->isInstructor() || $request->user()->isStaff()) {
            $request->user()->notify(new LeaveRequestNotification($leaveRequest->fresh(), 'cancelled'));
            Notification::send(
                User::whereHas('role', fn ($query) => $query->where('role_name', 'admin'))->get(),
                new LeaveRequestNotification($leaveRequest->fresh(), 'cancelled'),
            );
        }

        return back()->with('success', 'Leave request cancelled.');
    }
}
