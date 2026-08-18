<?php

namespace Tests\Feature;

use App\Models\LeaveRequest;
use App\Models\Role;
use App\Models\User;
use App\Services\PersonnelAttendanceSummary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class LeaveRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_can_submit_and_cancel_a_leave_request(): void
    {
        $user = $this->user('instructor');
        $response = $this->actingAs($user)->post(route('leave-requests.store'), [
            'leave_type' => 'sick', 'start_date' => '2026-08-17',
            'end_date' => '2026-08-18', 'reason' => 'Medical rest required.',
        ]);
        $response->assertRedirect();
        $leave = LeaveRequest::firstOrFail();
        $this->assertSame('pending', $leave->status);

        $this->actingAs($user)->patch(route('leave-requests.cancel', $leave))->assertRedirect();
        $this->assertSame('cancelled', $leave->fresh()->status);
    }

    public function test_admin_can_approve_and_only_owner_can_cancel(): void
    {
        $owner = $this->user('staff');
        $other = $this->user('staff');
        $admin = $this->user('admin');
        $leave = LeaveRequest::create(['user_id' => $owner->id, 'leave_type' => 'vacation', 'start_date' => '2026-08-17', 'end_date' => '2026-08-17', 'reason' => 'Personal appointment.']);

        $this->actingAs($other)->patch(route('leave-requests.cancel', $leave))->assertForbidden();
        $this->actingAs($admin)->patch(route('admin.leave-requests.update', $leave), ['status' => 'approved', 'review_notes' => 'Approved.'])->assertRedirect();
        $leave->refresh();
        $this->assertSame('approved', $leave->status);
        $this->assertSame($admin->id, $leave->reviewed_by);
    }

    public function test_approved_sick_leave_is_excused_in_attendance_summary(): void
    {
        $user = $this->user('instructor');
        LeaveRequest::create(['user_id' => $user->id, 'leave_type' => 'sick', 'start_date' => '2026-08-17', 'end_date' => '2026-08-17', 'reason' => 'Medical rest.', 'status' => 'approved']);
        $service = app(PersonnelAttendanceSummary::class);
        $days = $service->days($user, Carbon::parse('2026-08-17'), Carbon::parse('2026-08-17'), true);

        $this->assertSame('Sick Leave', $days->first()['status']);
        $this->assertSame('Excused', $days->first()['punctuality']);
        $this->assertSame(0, $service->totals($days)['absentDays']);
        $this->assertSame(1, $service->totals($days)['leaveDays']);
    }

    private function user(string $role): User
    {
        return User::factory()->create(['role_id' => Role::firstOrCreate(['role_name' => $role])->id]);
    }
}
