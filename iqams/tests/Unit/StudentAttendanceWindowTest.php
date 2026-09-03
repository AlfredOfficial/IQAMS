<?php

namespace Tests\Unit;

use App\Models\Schedule;
use App\Services\ScheduleOccurrenceResolver;
use App\Services\StudentAttendanceWindow;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use InvalidArgumentException;
use Tests\TestCase;

class StudentAttendanceWindowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'app.timezone' => 'Asia/Manila',
            'attendance.early_scan_minutes' => 15,
            'attendance.present_grace_minutes' => 15,
        ]);
    }

    public static function boundaries(): array
    {
        return [
            ['08:00', '07:44', false, null],
            ['08:00', '07:45', true, 'present'],
            ['08:00', '08:00', true, 'present'],
            ['08:00', '08:15', true, 'present'],
            ['08:00', '08:16', true, 'late'],
            ['11:00', '10:44', false, null],
            ['11:00', '10:45', true, 'present'],
            ['11:00', '11:00', true, 'present'],
            ['11:00', '11:15', true, 'present'],
            ['11:00', '11:16', true, 'late'],
            ['13:30', '13:14', false, null],
            ['13:30', '13:15', true, 'present'],
            ['13:30', '13:30', true, 'present'],
            ['13:30', '13:45', true, 'present'],
            ['13:30', '13:46', true, 'late'],
            ['15:00', '14:44', false, null],
            ['15:00', '14:45', true, 'present'],
            ['15:00', '15:00', true, 'present'],
            ['15:00', '15:15', true, 'present'],
            ['15:00', '15:16', true, 'late'],
        ];
    }

    #[DataProvider('boundaries')]
    public function test_dynamic_schedule_boundaries(string $start, string $time, bool $open, ?string $status): void
    {
        $schedule = new Schedule(['day' => 'monday', 'start_time' => $start, 'end_time' => '17:00']);
        $at = Carbon::parse("2026-08-10 {$time}:00", 'Asia/Manila');
        $occurrence = app(ScheduleOccurrenceResolver::class)->forDate($schedule, $at);
        $window = app(StudentAttendanceWindow::class);

        $this->assertNotNull($occurrence);
        $this->assertSame($open, $window->isOpen($occurrence, $at));

        if ($status !== null) {
            $this->assertSame($status, $window->status($occurrence, $at));
        }
    }

    public function test_cross_midnight_occurrence_is_anchored_to_its_start_day(): void
    {
        $schedule = new Schedule([
            'day' => 'monday',
            'start_time' => '22:00:00',
            'end_time' => '01:00:00',
        ]);
        $resolver = app(ScheduleOccurrenceResolver::class);
        $occurrence = $resolver->resolveAt($schedule, Carbon::parse('2026-08-11 00:30:00', 'Asia/Manila'));

        $this->assertNotNull($occurrence);
        $this->assertSame('2026-08-10', $occurrence->sessionDate->toDateString());
        $this->assertSame('2026-08-10 22:00:00', $occurrence->startsAt->toDateTimeString());
        $this->assertSame('2026-08-11 01:00:59', $occurrence->endsAt->toDateTimeString());
        $this->assertTrue($occurrence->overnight);
    }

    public function test_equal_start_and_end_times_cannot_form_an_occurrence(): void
    {
        $schedule = new Schedule([
            'day' => 'monday',
            'start_time' => '22:00:00',
            'end_time' => '22:00:00',
        ]);

        $this->expectException(InvalidArgumentException::class);
        app(ScheduleOccurrenceResolver::class)->forDate($schedule, Carbon::parse('2026-08-10', 'Asia/Manila'));
    }
}
