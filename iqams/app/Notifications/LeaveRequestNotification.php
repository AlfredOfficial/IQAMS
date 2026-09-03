<?php

namespace App\Notifications;

use App\Models\LeaveRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class LeaveRequestNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public LeaveRequest $leaveRequest;

    public string $event;

    public function __construct(LeaveRequest $leaveRequest, string $event)
    {
        $this->leaveRequest = $leaveRequest;
        $this->event = $event;
        $this->afterCommit = true;
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $leave = $this->leaveRequest->loadMissing('user');
        $url = match (true) {
            $notifiable->isAdmin() => route('admin.leave-requests.index'),
            $notifiable->isStaff() => route('staff.leave-requests.index'),
            default => route('leave-requests.index'),
        };

        return [
            'leave_request_id' => $leave->id,
            'event' => $this->event,
            'status' => $leave->status,
            'requester_name' => $leave->user->name,
            'leave_type' => $leave->type_label,
            'start_date' => $leave->start_date->toDateString(),
            'end_date' => $leave->end_date->toDateString(),
            'review_notes' => $leave->review_notes,
            'url' => $url,
        ];
    }
}
