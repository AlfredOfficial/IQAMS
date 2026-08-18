<?php

namespace Tests\Feature;

use App\Models\LeaveRequest;
use App\Models\Role;
use App\Models\User;
use App\Notifications\LeaveRequestNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class LeaveNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_instructor_and_staff_submissions_notify_the_requester_and_every_admin(): void
    {
        $firstAdmin = $this->user('admin');
        $secondAdmin = $this->user('admin');

        foreach (['instructor', 'staff'] as $role) {
            $requester = $this->user($role);
            $this->actingAs($requester)->post(route('leave-requests.store'), $this->leavePayload($role))->assertRedirect();

            $this->assertSame(1, $requester->unreadNotifications()->count());
        }

        $this->assertSame(2, $firstAdmin->unreadNotifications()->count());
        $this->assertSame(2, $secondAdmin->unreadNotifications()->count());
    }

    public function test_student_submission_does_not_create_leave_notifications(): void
    {
        $admin = $this->user('admin');
        $student = $this->user('student');

        $this->actingAs($student)->post(route('leave-requests.store'), $this->leavePayload('student'))->assertRedirect();

        $this->assertSame(0, $student->notifications()->count());
        $this->assertSame(0, $admin->notifications()->count());
    }

    public function test_approval_and_rejection_notify_only_the_request_owner(): void
    {
        $admin = $this->user('admin');
        $approvedOwner = $this->user('instructor');
        $rejectedOwner = $this->user('staff');
        $approved = $this->leave($approvedOwner);
        $rejected = $this->leave($rejectedOwner, '2026-09-03');

        $this->actingAs($admin)->patch(route('admin.leave-requests.update', $approved), ['status' => 'approved', 'review_notes' => 'Approved by dean.'])->assertRedirect();
        $this->patch(route('admin.leave-requests.update', $rejected), ['status' => 'rejected', 'review_notes' => 'Insufficient staffing.'])->assertRedirect();

        $this->assertSame('approved', $approvedOwner->notifications()->first()->data['status']);
        $this->assertSame('Approved by dean.', $approvedOwner->notifications()->first()->data['review_notes']);
        $this->assertSame('rejected', $rejectedOwner->notifications()->first()->data['status']);
        $this->assertSame(0, $admin->notifications()->count());
    }

    public function test_cancellation_notifies_requester_and_all_admins(): void
    {
        $admin = $this->user('admin');
        $owner = $this->user('staff');
        $leave = $this->leave($owner);

        $this->actingAs($owner)->patch(route('leave-requests.cancel', $leave))->assertRedirect();

        $this->assertSame('cancelled', $owner->notifications()->first()->data['event']);
        $this->assertSame('cancelled', $admin->notifications()->first()->data['event']);
    }

    public function test_opening_bell_marks_only_authenticated_users_leave_notifications_as_read(): void
    {
        $first = $this->user('instructor');
        $second = $this->user('staff');
        $leave = $this->leave($first);
        $first->notify(new LeaveRequestNotification($leave, 'submitted'));
        $second->notify(new LeaveRequestNotification($leave, 'submitted'));

        $this->actingAs($first)->postJson(route('leave-notifications.read'))
            ->assertOk()->assertJson(['unread_count' => 0]);

        $this->assertSame(0, $first->unreadNotifications()->count());
        $this->assertSame(1, $second->unreadNotifications()->count());
    }

    public function test_bell_shows_unread_count_and_only_eight_newest_items(): void
    {
        $owner = $this->user('instructor');
        $leave = $this->leave($owner);

        for ($index = 1; $index <= 10; $index++) {
            Carbon::setTestNow(Carbon::parse('2026-08-18 10:00:00')->addSeconds($index));
            $leave->update(['review_notes' => "Notification {$index}"]);
            $owner->notify(new LeaveRequestNotification($leave->fresh(), 'approved'));
        }
        Carbon::setTestNow();

        $response = $this->actingAs($owner)->get(route('leave-requests.index'))->assertOk();
        $response->assertSee('unread: 10', false)
            ->assertSee('Notification 10')->assertSee('Notification 3')
            ->assertDontSee('Note: Notification 2</p>', false)
            ->assertDontSee('Note: Notification 1</p>', false);
    }

    private function user(string $role): User
    {
        return User::factory()->create(['role_id' => Role::firstOrCreate(['role_name' => $role])->id]);
    }

    private function leave(User $owner, string $start = '2026-09-01'): LeaveRequest
    {
        return LeaveRequest::create([
            'user_id' => $owner->id, 'leave_type' => 'sick', 'start_date' => $start,
            'end_date' => $start, 'reason' => 'Medical rest required.',
        ]);
    }

    private function leavePayload(string $suffix): array
    {
        return [
            'leave_type' => 'sick', 'start_date' => '2026-09-01', 'end_date' => '2026-09-01',
            'reason' => "Medical rest required for {$suffix}.",
        ];
    }
}
