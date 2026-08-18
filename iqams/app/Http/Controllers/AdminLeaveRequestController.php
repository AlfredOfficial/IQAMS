<?php

namespace App\Http\Controllers;

use App\Models\LeaveRequest;
use App\Notifications\LeaveRequestNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AdminLeaveRequestController extends Controller
{
    public function index(Request $request)
    {
        $query = LeaveRequest::with(['user.role', 'reviewer'])->latest();
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
        $leaveRequest->update($validated + ['reviewed_by' => $request->user()->id, 'reviewed_at' => now()]);
        $leaveRequest->user->notify(new LeaveRequestNotification($leaveRequest->fresh(), $validated['status']));

        return back()->with('success', 'Leave request '.$validated['status'].'.');
    }

    public function attachment(LeaveRequest $leaveRequest)
    {
        abort_unless($leaveRequest->attachment_path && Storage::exists($leaveRequest->attachment_path), 404);

        return Storage::download($leaveRequest->attachment_path);
    }
}
