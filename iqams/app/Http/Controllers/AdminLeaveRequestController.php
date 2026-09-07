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
        app(\App\Services\LeaveTransitionService::class)->transition($leaveRequest, $validated['status'], $request->user(), $validated['review_notes'] ?? null, $request);

        return back()->with('success', 'Leave request '.$validated['status'].'.');
    }

    public function attachment(LeaveRequest $leaveRequest)
    {
        abort_unless($leaveRequest->attachment_path && Storage::exists($leaveRequest->attachment_path), 404);

        return Storage::download($leaveRequest->attachment_path);
    }
}
