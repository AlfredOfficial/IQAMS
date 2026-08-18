<?php

namespace Tests\Unit;

use App\Models\Schedule;
use App\Services\StudentAttendanceWindow;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
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
        $schedule = new Schedule(['start_time' => $start, 'end_time' => '17:00']);
        $at = Carbon::parse("2026-08-10 {$time}:00", 'Asia/Manila');
        $window = app(StudentAttendanceWindow::class);

        $this->assertSame($open, $window->isOpen($schedule, $at));

        if ($status !== null) {
            $this->assertSame($status, $window->status($schedule, $at));
        }
    }
}
