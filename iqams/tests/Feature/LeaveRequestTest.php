<?php

namespace Tests\Feature;

use App\Models\LeaveRequest;
use App\Models\AttendanceLog;
use App\Models\Role;
use App\Models\User;
use App\Services\PersonnelAttendanceSummary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class LeaveRequestTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

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

    public function test_approved_multi_day_leave_counts_every_date_and_is_not_absent(): void
    {
        $user = $this->user('staff');
        LeaveRequest::create([
            'user_id' => $user->id, 'leave_type' => 'vacation',
            'start_date' => '2026-08-20', 'end_date' => '2026-08-22',
            'reason' => 'Approved leave.', 'status' => 'approved',
        ]);

        $service = app(PersonnelAttendanceSummary::class);
        $days = $service->days($user, Carbon::parse('2026-08-20'), Carbon::parse('2026-08-22'), true);
        $totals = $service->totals($days);

        $this->assertSame(3, $totals['leaveDays']);
        $this->assertSame(0, $totals['absentDays']);
        $this->assertSame(['On Leave', 'On Leave', 'On Leave'], $days->pluck('status')->all());
        $this->assertSame([0, 0, 0], $days->pluck('minutes')->all());
    }

    public function test_pending_and_rejected_leave_do_not_count_as_approved_leave(): void
    {
        $user = $this->user('staff');
        foreach ([['2026-08-20', 'pending'], ['2026-08-21', 'rejected']] as [$date, $status]) {
            LeaveRequest::create([
                'user_id' => $user->id, 'leave_type' => 'vacation',
                'start_date' => $date, 'end_date' => $date,
                'reason' => ucfirst($status).' request.', 'status' => $status,
            ]);
        }

        $service = app(PersonnelAttendanceSummary::class);
        $days = $service->days($user, Carbon::parse('2026-08-20'), Carbon::parse('2026-08-21'), true);

        $this->assertSame(0, $service->totals($days)['leaveDays']);
    }

    public function test_leave_spanning_months_only_counts_dates_inside_selected_month(): void
    {
        $user = $this->user('staff');
        LeaveRequest::create([
            'user_id' => $user->id, 'leave_type' => 'vacation',
            'start_date' => '2026-07-30', 'end_date' => '2026-08-02',
            'reason' => 'Cross-month leave.', 'status' => 'approved',
        ]);

        $service = app(PersonnelAttendanceSummary::class);
        $augustDays = $service->days($user, Carbon::parse('2026-08-01'), Carbon::parse('2026-08-31'), true);

        $this->assertSame(2, $service->totals($augustDays)['leaveDays']);
        $this->assertSame(['2026-08-01', '2026-08-02'], $augustDays->whereNotNull('leave')->pluck('date')->map->toDateString()->all());
    }

    public function test_staff_monthly_summary_card_and_table_show_approved_leave(): void
    {
        Carbon::setTestNow('2026-08-30 12:00:00');
        $user = $this->user('staff');
        LeaveRequest::create([
            'user_id' => $user->id, 'leave_type' => 'vacation',
            'start_date' => '2026-08-20', 'end_date' => '2026-08-20',
            'reason' => 'Approved leave.', 'status' => 'approved',
        ]);

        $this->actingAs($user)->get(route('staff.attendance.summary', ['month' => 8, 'year' => 2026]))
            ->assertOk()
            ->assertViewHas('totals', fn (array $totals) => $totals['leaveDays'] === 1 && $totals['absentDays'] >= 0)
            ->assertSee('Approved leave')
            ->assertSee('Aug 20, 2026')
            ->assertSee('On Leave');
    }

    public function test_approved_leave_later_in_current_month_is_visible_in_monthly_summary(): void
    {
        Carbon::setTestNow('2026-08-30 19:58:00');
        $user = $this->user('staff');
        LeaveRequest::create([
            'user_id' => $user->id, 'leave_type' => 'vacation',
            'start_date' => '2026-08-31', 'end_date' => '2026-08-31',
            'reason' => 'Approved future date in selected month.', 'status' => 'approved',
        ]);

        $this->actingAs($user)->get(route('staff.attendance.summary', ['month' => 8, 'year' => 2026]))
            ->assertOk()
            ->assertViewHas('totals', fn (array $totals) => $totals['leaveDays'] === 1)
            ->assertSee('Aug 31, 2026')
            ->assertSee('On Leave');
    }

    public function test_leave_approval_is_rejected_when_attendance_exists_in_requested_dates(): void
    {
        $owner = $this->user('instructor');
        $admin = $this->user('admin');
        AttendanceLog::create([
            'user_id' => $owner->id,
            'attendance_type' => 'time_in',
            'scan_time' => '2026-08-17 08:00:00',
            'status' => 'present',
        ]);
        $leave = LeaveRequest::create([
            'user_id' => $owner->id,
            'leave_type' => 'vacation',
            'start_date' => '2026-08-17',
            'end_date' => '2026-08-17',
            'reason' => 'Personal appointment.',
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.leave-requests.update', $leave), ['status' => 'approved'])
            ->assertSessionHasErrors(['status']);

        $this->assertSame('pending', $leave->fresh()->status);
    }

    public function test_legacy_attendance_during_approved_leave_is_reported_as_excused_leave(): void
    {
        $user = $this->user('staff');
        $leave = LeaveRequest::create([
            'user_id' => $user->id,
            'leave_type' => 'vacation',
            'start_date' => '2026-08-17',
            'end_date' => '2026-08-17',
            'reason' => 'Approved leave.',
            'status' => 'approved',
        ]);
        $log = AttendanceLog::create([
            'user_id' => $user->id,
            'attendance_type' => 'time_in',
            'attendance_period' => 'morning_in',
            'scan_time' => '2026-08-17 08:00:00',
            'status' => 'present',
        ]);

        $day = app(PersonnelAttendanceSummary::class)->day(
            Carbon::parse('2026-08-17'), collect([$log]), $leave,
        );

        $this->assertSame('On Leave', $day['status']);
        $this->assertSame('Excused', $day['punctuality']);
        $this->assertSame(0, $day['minutes']);
        $this->assertContains('Attendance exists during approved leave', $day['notes']);
    }

    private function user(string $role): User
    {
        return User::factory()->create(['role_id' => Role::firstOrCreate(['role_name' => $role])->id]);
    }
}
