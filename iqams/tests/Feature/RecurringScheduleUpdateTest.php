<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Department;
use App\Models\AttendanceLog;
use App\Models\Instructor;
use App\Models\Role;
use App\Models\Schedule;
use App\Models\Section;
use App\Models\Subject;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class RecurringScheduleUpdateTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Instructor $instructor;
    private Instructor $otherInstructor;
    private Subject $subject;
    private Subject $otherSubject;
    private Section $section;
    private Section $otherSection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->admin = User::factory()->create(['role_id' => Role::findByName('admin')->id]);
        $department = Department::create(['department_code' => 'CCS', 'department_name' => 'Computing']);
        $course = Course::create(['department_id' => $department->id, 'course_code' => 'BSIT', 'course_name' => 'Information Technology']);
        $this->section = Section::create(['course_id' => $course->id, 'section_name' => 'A', 'school_year' => '2026-2027', 'semester' => '1st']);
        $this->otherSection = Section::create(['course_id' => $course->id, 'section_name' => 'B', 'school_year' => '2026-2027', 'semester' => '1st']);
        $this->subject = Subject::create(['subject_code' => 'IT101', 'subject_name' => 'Introduction to IT', 'units' => 3]);
        $this->otherSubject = Subject::create(['subject_code' => 'IT102', 'subject_name' => 'Programming', 'units' => 3]);
        $this->instructor = $this->instructor($department, 'INS-001');
        $this->otherInstructor = $this->instructor($department, 'INS-002');
    }

    public function test_mwf_edit_updates_every_member_and_preserves_days(): void
    {
        $group = $this->group(['monday', 'wednesday', 'friday']);
        $attendance = AttendanceLog::create([
            'user_id' => $this->admin->id,
            'schedule_id' => $group[1]->id,
            'attendance_type' => 'time_in',
            'scan_time' => '2026-08-26 08:00:00',
            'status' => 'present',
        ]);

        $this->update($group->first(), [
            'subject_id' => $this->otherSubject->id,
            'start_time' => '09:00',
            'end_time' => '10:00',
            'room' => 'Room 202',
            'apply_to_recurring' => '1',
        ])->assertSessionHasNoErrors();

        $updated = Schedule::where('recurring_schedule_group_id', $group->first()->recurring_schedule_group_id)->get();
        $this->assertEqualsCanonicalizing(['monday', 'wednesday', 'friday'], $updated->pluck('day')->all());
        $this->assertTrue($updated->every(fn (Schedule $schedule) =>
            $schedule->subject_id === $this->otherSubject->id
            && substr($schedule->start_time, 0, 5) === '09:00'
            && substr($schedule->end_time, 0, 5) === '10:00'
            && $schedule->room === 'Room 202'
        ));
        $this->assertSame($group[1]->id, $attendance->fresh()->schedule_id);
    }

    public function test_multi_day_creation_assigns_one_group_without_duplicates(): void
    {
        $this->actingAs($this->admin)->post(route('schedules.store'), [
            'subject_id' => $this->subject->id,
            'instructor_id' => $this->instructor->id,
            'section_id' => $this->section->id,
            'days' => ['monday', 'wednesday', 'friday'],
            'start_time' => '08:00',
            'end_time' => '09:00',
            'room' => 'Room 101',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $created = Schedule::all();
        $this->assertCount(3, $created);
        $this->assertCount(1, $created->pluck('recurring_schedule_group_id')->unique());
        $this->assertNotNull($created->first()->recurring_schedule_group_id);
        $this->assertEqualsCanonicalizing(['monday', 'wednesday', 'friday'], $created->pluck('day')->all());
    }

    public function test_tth_edit_updates_both_days(): void
    {
        $group = $this->group(['tuesday', 'thursday']);

        $this->update($group->first(), [
            'instructor_id' => $this->otherInstructor->id,
            'apply_to_recurring' => '1',
        ])->assertSessionHasNoErrors();

        $updated = Schedule::where('recurring_schedule_group_id', $group->first()->recurring_schedule_group_id)->get();
        $this->assertEqualsCanonicalizing(['tuesday', 'thursday'], $updated->pluck('day')->all());
        $this->assertTrue($updated->every(fn (Schedule $schedule) => $schedule->instructor_id === $this->otherInstructor->id));
    }

    public function test_single_day_edit_only_updates_that_schedule(): void
    {
        $single = $this->group(['monday'])->first();

        $this->update($single, ['room' => 'Room 303'])->assertSessionHasNoErrors();

        $this->assertSame('Room 303', $single->fresh()->room);
        $this->assertSame(1, Schedule::count());
    }

    public function test_disabling_recurring_update_changes_only_one_day_and_detaches_it(): void
    {
        $group = $this->group(['monday', 'wednesday', 'friday']);
        $originalGroupId = $group->first()->recurring_schedule_group_id;

        $this->update($group->first(), [
            'start_time' => '11:00',
            'end_time' => '12:00',
            'apply_to_recurring' => '0',
        ])->assertSessionHasNoErrors();

        $monday = $group->first()->fresh();
        $this->assertNotSame($originalGroupId, $monday->recurring_schedule_group_id);
        $this->assertSame('11:00', substr($monday->start_time, 0, 5));
        $this->assertCount(2, Schedule::where('recurring_schedule_group_id', $originalGroupId)->get());
        $this->assertTrue(Schedule::where('recurring_schedule_group_id', $originalGroupId)->get()
            ->every(fn (Schedule $schedule) => substr($schedule->start_time, 0, 5) === '08:00'));
    }

    public function test_same_subject_groups_with_other_sections_instructors_or_rooms_are_untouched(): void
    {
        $target = $this->group(['monday', 'wednesday', 'friday']);
        $otherSection = $this->group(['monday', 'wednesday', 'friday'], ['section_id' => $this->otherSection->id]);
        $otherInstructor = $this->group(['monday', 'wednesday', 'friday'], ['instructor_id' => $this->otherInstructor->id]);
        $otherRoom = $this->group(['monday', 'wednesday', 'friday'], ['room' => 'Room 999']);

        $this->update($target->first(), [
            'start_time' => '09:00',
            'end_time' => '10:00',
            'apply_to_recurring' => '1',
        ])->assertSessionHasNoErrors();

        foreach ([$otherSection, $otherInstructor, $otherRoom] as $unrelated) {
            $this->assertTrue($unrelated->map->fresh()->every(
                fn (Schedule $schedule) => substr($schedule->start_time, 0, 5) === '08:00'
            ));
        }
    }

    public function test_admin_ui_defaults_recurring_option_on_and_instructor_uses_group_id(): void
    {
        $first = $this->group(['monday', 'wednesday', 'friday']);
        $this->group(['tuesday', 'thursday']);

        $this->actingAs($this->admin)->get(route('schedules.index'))
            ->assertOk()
            ->assertSee('Apply changes to all recurring schedules')
            ->assertSee('Changes will apply to', false);

        $instructorUser = $this->instructor->user;
        $response = $this->actingAs($instructorUser)->get(route('instructor.schedule'));
        $response->assertOk()->assertViewHas('scheduleGroups', function ($groups) use ($first) {
            return $groups->count() === 2
                && $groups->contains(fn (array $group) => count($group['days']) === $first->count());
        });
    }

    private function update(Schedule $schedule, array $changes)
    {
        $payload = array_merge([
            'subject_id' => $schedule->subject_id,
            'instructor_id' => $schedule->instructor_id,
            'section_id' => $schedule->section_id,
            'days' => [$schedule->day],
            'start_time' => substr($schedule->start_time, 0, 5),
            'end_time' => substr($schedule->end_time, 0, 5),
            'room' => $schedule->room,
        ], $changes);

        return $this->actingAs($this->admin)->put(route('schedules.update', $schedule), $payload)->assertRedirect();
    }

    private function group(array $days, array $overrides = [])
    {
        $groupId = (string) Str::uuid();
        $attributes = array_merge([
            'recurring_schedule_group_id' => $groupId,
            'subject_id' => $this->subject->id,
            'instructor_id' => $this->instructor->id,
            'section_id' => $this->section->id,
            'start_time' => '08:00',
            'end_time' => '09:00',
            'room' => 'Room 101',
        ], $overrides);

        return collect($days)->map(fn (string $day) => Schedule::create($attributes + ['day' => $day]));
    }

    private function instructor(Department $department, string $employeeNo): Instructor
    {
        $user = User::factory()->create(['role_id' => Role::findByName('instructor')->id]);

        return Instructor::create([
            'user_id' => $user->id,
            'department_id' => $department->id,
            'employee_no' => $employeeNo,
            'first_name' => 'Test',
            'last_name' => $employeeNo,
        ]);
    }
}
