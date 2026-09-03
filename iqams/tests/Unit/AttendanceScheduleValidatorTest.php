<?php

namespace Tests\Unit;

use App\Models\Schedule;
use App\Models\Student;
use App\Models\User;
use App\Services\AttendanceScheduleValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AttendanceScheduleValidatorTest extends TestCase
{
    use RefreshDatabase;

    private AttendanceScheduleValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'app.timezone' => 'Asia/Manila',
            'attendance.early_scan_minutes' => 15,
            'attendance.present_grace_minutes' => 15,
        ]);
        $this->validator = app(AttendanceScheduleValidator::class);
    }

    public function test_student_is_accepted_only_during_their_sections_session(): void
    {
        [$user, $schedule] = $this->attendanceContext();

        $this->validator->validate($user, $schedule, Carbon::parse('2026-08-10 08:00:00', 'Asia/Manila'));
        $this->validator->validate($user, $schedule, Carbon::parse('2026-08-10 09:30:00', 'Asia/Manila'));

        $this->expectNotToPerformAssertions();
    }

    public function test_student_is_rejected_after_the_session_ends(): void
    {
        [$user, $schedule] = $this->attendanceContext();

        $this->expectException(ValidationException::class);
        $this->validator->validate($user, $schedule, Carbon::parse('2026-08-10 11:00:00', 'Asia/Manila'));
    }

    public function test_student_is_accepted_at_the_dynamic_early_scan_boundary(): void
    {
        [$user, $schedule] = $this->attendanceContext();

        $this->validator->validate($user, $schedule, Carbon::parse('2026-08-10 07:45:00', 'Asia/Manila'));

        $this->expectNotToPerformAssertions();
    }

    public function test_student_is_rejected_on_a_different_day(): void
    {
        [$user, $schedule] = $this->attendanceContext();

        $this->expectException(ValidationException::class);
        $this->validator->validate($user, $schedule, Carbon::parse('2026-08-11 09:00:00', 'Asia/Manila'));
    }

    public function test_student_is_rejected_for_another_sections_schedule(): void
    {
        [$user, $schedule] = $this->attendanceContext();
        $schedule->section_id = 2;

        $this->expectException(ValidationException::class);
        $this->validator->validate($user, $schedule, Carbon::parse('2026-08-10 09:00:00', 'Asia/Manila'));
    }

    public function test_cross_midnight_session_accepts_times_on_both_days(): void
    {
        [$user, $schedule] = $this->attendanceContext();
        $schedule->start_time = '22:00:00';
        $schedule->end_time = '01:00:00';

        $this->validator->validate($user, $schedule, Carbon::parse('2026-08-10 23:00:00', 'Asia/Manila'));
        $this->validator->validate($user, $schedule, Carbon::parse('2026-08-11 00:30:00', 'Asia/Manila'));

        $this->expectNotToPerformAssertions();
    }

    private function attendanceContext(): array
    {
        $student = new Student(['section_id' => 1]);
        $user = new User;
        $user->setRelation('student', $student);

        $schedule = new Schedule([
            'section_id' => 1,
            'day' => 'monday',
            'start_time' => '08:00:00',
            'end_time' => '10:00:00',
        ]);

        return [$user, $schedule];
    }
}
