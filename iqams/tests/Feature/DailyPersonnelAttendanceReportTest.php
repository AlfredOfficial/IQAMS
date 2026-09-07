<?php

namespace Tests\Feature;

use App\Models\AttendanceLog;
use App\Models\Department;
use App\Models\Instructor;
use App\Models\NonTeachingStaff;
use App\Models\OfficeUnit;
use App\Models\ReportExport;
use App\Models\Role;
use App\Models\User;
use App\Jobs\GenerateDailyPersonnelExport;
use App\Services\DailyPersonnelAttendanceExportService;
use App\Services\PersonnelAttendanceReportService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class DailyPersonnelAttendanceReportTest extends TestCase
{
    use RefreshDatabase;

    private Department $department;

    private OfficeUnit $officeUnit;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->department = Department::create(['department_code' => 'CCS', 'department_name' => 'Computer Studies']);
        $this->officeUnit = OfficeUnit::firstOrCreate(
            ['code' => 'REG'],
            ['name' => 'Registrar', 'is_active' => true],
        );
    }

    public function test_report_is_admin_only_and_defaults_to_today(): void
    {
        $admin = $this->user('admin');
        $instructor = $this->instructor('Zara', 'Zulu');
        $staff = $this->staff('Ana', 'Alpha');

        $this->actingAs($admin)->get(route('admin.reports.daily-personnel.index'))
            ->assertOk()
            ->assertViewHas('date', fn (Carbon $date) => $date->isSameDay(today()))
            ->assertSeeInOrder([$staff->fullName(), $instructor->fullName()]);

        $this->actingAs($this->user('instructor'))
            ->get(route('admin.reports.daily-personnel.index'))
            ->assertForbidden();
    }

    public function test_combined_report_maps_actual_period_times_and_keeps_missing_values_blank(): void
    {
        $date = Carbon::parse('2026-08-30');
        $instructor = $this->instructor('Late', 'Teacher');
        $staff = $this->staff('Missing', 'Scan');
        $this->log($instructor->user, 'morning_in', 'time_in', $date->copy()->setTime(8, 15), 'late');
        $this->log($instructor->user, 'lunch_out', 'time_out', $date->copy()->setTime(12, 2));
        $this->log($instructor->user, 'afternoon_in', 'time_in', $date->copy()->setTime(13, 5));
        $this->log($instructor->user, 'final_out', 'time_out', $date->copy()->setTime(17, 0));
        $this->log($staff->user, 'lunch_out', 'time_out', $date->copy()->setTime(12, 1));

        $rows = app(PersonnelAttendanceReportService::class)->getDailyReport($date)['rows']->keyBy('user_id');

        $this->assertSame(['8:15 AM', '12:02 PM', '1:05 PM', '5:00 PM'], [
            $rows[$instructor->user_id]['morning_time_in'],
            $rows[$instructor->user_id]['morning_time_out'],
            $rows[$instructor->user_id]['afternoon_time_in'],
            $rows[$instructor->user_id]['afternoon_time_out'],
        ]);
        $this->assertSame('', $rows[$staff->user_id]['morning_time_in']);
        $this->assertSame('12:01 PM', $rows[$staff->user_id]['morning_time_out']);
        $this->assertSame('', $rows[$staff->user_id]['afternoon_time_in']);
        $this->assertSame('', $rows[$staff->user_id]['afternoon_time_out']);
        $this->assertNotContains('Late', $rows[$instructor->user_id]);
    }

    public function test_legacy_duplicates_use_earliest_time_in_and_latest_time_out(): void
    {
        $date = Carbon::parse('2026-08-30');
        $staff = $this->staff('Legacy', 'Duplicate');
        $this->legacyLog($staff->user, 'morning_in', 'time_in', $date->copy()->setTime(8, 5));
        $this->legacyLog($staff->user, 'morning_in', 'time_in', $date->copy()->setTime(7, 55));
        $this->legacyLog($staff->user, 'lunch_out', 'time_out', $date->copy()->setTime(11, 58));
        $this->legacyLog($staff->user, 'lunch_out', 'time_out', $date->copy()->setTime(12, 4));

        $row = app(PersonnelAttendanceReportService::class)->getDailyReport($date)['rows']->firstWhere('user_id', $staff->user_id);

        $this->assertSame('7:55 AM', $row['morning_time_in']);
        $this->assertSame('12:04 PM', $row['morning_time_out']);
    }

    public function test_inactive_users_students_and_non_personnel_logs_are_excluded(): void
    {
        $date = Carbon::parse('2026-08-30');
        $active = $this->staff('Active', 'Staff');
        $inactive = $this->instructor('Inactive', 'Teacher');
        $inactive->user->update(['status' => 'inactive']);
        $student = $this->user('student');
        $this->log($student, 'morning_in', 'time_in', $date->copy()->setTime(8, 0));

        $rows = app(PersonnelAttendanceReportService::class)->getDailyReport($date)['rows'];

        $this->assertSame([$active->user_id], $rows->pluck('user_id')->all());
    }

    public function test_all_report_filters_are_applied(): void
    {
        $otherDepartment = Department::create(['department_code' => 'CTE', 'department_name' => 'Teacher Education']);
        $otherOffice = OfficeUnit::create(['code' => 'CMP', 'name' => 'Compliance Office', 'is_active' => true]);
        $targetInstructor = $this->instructor('Target', 'Instructor');
        $otherInstructor = $this->instructor('Other', 'Instructor', $otherDepartment);
        $targetStaff = $this->staff('Target', 'Staff');
        $otherStaff = $this->staff('Other', 'Staff', $otherOffice);
        $service = app(PersonnelAttendanceReportService::class);
        $date = Carbon::parse('2026-08-30');

        $this->assertSame([$targetInstructor->user_id], $service->getDailyReport($date, ['department_id' => $this->department->id])['rows']->pluck('user_id')->all());
        $this->assertSame([$targetStaff->user_id], $service->getDailyReport($date, ['office_unit_id' => $this->officeUnit->id])['rows']->pluck('user_id')->all());
        $this->assertEqualsCanonicalizing([$targetInstructor->user_id, $otherInstructor->user_id], $service->getDailyReport($date, ['personnel_type' => 'instructor'])['rows']->pluck('user_id')->all());
        $this->assertSame([$otherStaff->user_id], $service->getDailyReport($date, ['personnel_id' => $otherStaff->user_id])['rows']->pluck('user_id')->all());
    }

    public function test_invalid_or_mismatched_filters_are_rejected(): void
    {
        $admin = $this->user('admin');

        $this->actingAs($admin)->get(route('admin.reports.daily-personnel.index', ['date' => '08/30/2026']))
            ->assertSessionHasErrors('date');
        $this->actingAs($admin)->get(route('admin.reports.daily-personnel.index', ['personnel_type' => 'staff', 'department_id' => $this->department->id]))
            ->assertSessionHasErrors('department_id');
        $this->actingAs($admin)->get(route('admin.reports.daily-personnel.index', ['personnel_type' => 'instructor', 'office_unit_id' => $this->officeUnit->id]))
            ->assertSessionHasErrors('office_unit_id');
        $this->actingAs($admin)->get(route('admin.reports.daily-personnel.index', ['department_id' => $this->department->id, 'office_unit_id' => $this->officeUnit->id]))
            ->assertSessionHasErrors('office_unit_id');
    }

    public function test_pdf_and_excel_exports_are_queued_and_use_the_same_report_structure(): void
    {
        $admin = $this->user('admin');
        $staff = $this->staff('Export', 'Person');
        $query = ['date' => '2026-08-30'];
        Queue::fake();
        $disk = Storage::fake('local');

        $pdf = $this->actingAs($admin)->postJson(route('admin.reports.daily-personnel.exports.store'), $query + ['format' => 'pdf']);
        $pdf->assertStatus(202)->assertJsonPath('status', ReportExport::STATUS_PENDING);
        $pdfExport = ReportExport::findOrFail($pdf->json('id'));

        $excel = $this->actingAs($admin)->postJson(route('admin.reports.daily-personnel.exports.store'), $query + ['format' => 'xlsx']);
        $excel->assertStatus(202)->assertJsonPath('status', ReportExport::STATUS_PENDING);
        $excelExport = ReportExport::findOrFail($excel->json('id'));

        Queue::assertPushed(GenerateDailyPersonnelExport::class, 2);

        app(GenerateDailyPersonnelExport::class, ['exportId' => $pdfExport->id])->handle(
            app(PersonnelAttendanceReportService::class),
            app(\App\Services\DailyPersonnelAttendanceExportService::class),
        );
        app(GenerateDailyPersonnelExport::class, ['exportId' => $excelExport->id])->handle(
            app(PersonnelAttendanceReportService::class),
            app(\App\Services\DailyPersonnelAttendanceExportService::class),
        );

        $pdfExport->refresh();
        $excelExport->refresh();
        $this->assertSame(ReportExport::STATUS_COMPLETED, $pdfExport->status);
        $this->assertSame(ReportExport::STATUS_COMPLETED, $excelExport->status);
        $disk->assertExists($pdfExport->path);
        $disk->assertExists($excelExport->path);
        $this->actingAs($this->user('admin'))
            ->getJson(route('admin.report-exports.show', $pdfExport))
            ->assertForbidden();
        $this->actingAs($admin)
            ->get(route('admin.report-exports.download', $pdfExport))
            ->assertOk();
        $this->assertStringStartsWith('%PDF', Storage::disk('local')->get($pdfExport->path));

        $content = Storage::disk('local')->get($excelExport->path);
        $path = tempnam(sys_get_temp_dir(), 'iqams-report-').'.xlsx';
        file_put_contents($path, $content);
        $sheet = IOFactory::load($path)->getActiveSheet();
        @unlink($path);

        $this->assertSame('DANAO TECHNOLOGICAL COLLEGE', $sheet->getCell('A1')->getValue());
        $this->assertSame('DAILY ATTENDANCE REPORT', $sheet->getCell('A2')->getValue());
        $this->assertSame($staff->fullName(), $sheet->getCell('A7')->getValue());
        $this->assertSame('', (string) $sheet->getCell('B7')->getValue());
    }

    public function test_report_keeps_approximately_eighty_personnel_and_prints_only_the_document(): void
    {
        $admin = $this->user('admin');
        for ($index = 1; $index <= 80; $index++) {
            $this->staff('Person', str_pad((string) $index, 2, '0', STR_PAD_LEFT));
        }

        $response = $this->actingAs($admin)->get(route('admin.reports.daily-personnel.index', ['date' => '2026-08-30']));

        $response->assertOk()
            ->assertViewHas('rows', fn ($rows) => $rows->count() === 80)
            ->assertSee('@page { size: A4 landscape;', false)
            ->assertSee('class="no-print', false)
            ->assertSee('Prepared by:')
            ->assertSee('Checked by:');
    }

    public function test_larger_reports_retain_all_attendance_scans_across_chunk_boundaries(): void
    {
        $date = Carbon::parse('2026-08-30');
        $staffMembers = [];
        for ($index = 1; $index <= 200; $index++) {
            $staff = $this->staff('Staff', str_pad((string) $index, 3, '0', STR_PAD_LEFT));
            $staffMembers[] = $staff;
            // Insert 4 scans per staff member in person-by-person order so IDs interleave with scan times
            $this->log($staff->user, 'morning_in', 'time_in', $date->copy()->setTime(8, 0));
            $this->log($staff->user, 'lunch_out', 'time_out', $date->copy()->setTime(12, 0));
            $this->log($staff->user, 'afternoon_in', 'time_in', $date->copy()->setTime(13, 0));
            $this->log($staff->user, 'final_out', 'time_out', $date->copy()->setTime(17, 0));
        }

        $report = app(PersonnelAttendanceReportService::class)->getDailyReport($date, ['personnel_type' => 'staff']);
        $rows = $report['rows']->keyBy('user_id');

        $this->assertCount(200, $rows);

        // Verify every staff member has all 4 non-empty scan times
        foreach ($staffMembers as $member) {
            $row = $rows->get($member->user_id);
            $this->assertNotNull($row, "Staff {$member->employee_no} must be present in report");
            $this->assertSame('8:00 AM', $row['morning_time_in']);
            $this->assertSame('12:00 PM', $row['morning_time_out']);
            $this->assertSame('1:00 PM', $row['afternoon_time_in']);
            $this->assertSame('5:00 PM', $row['afternoon_time_out']);
        }
    }

    public function test_large_report_renders_every_expected_scan_on_page_pdf_and_excel(): void
    {
        $admin = $this->user('admin');
        $date = Carbon::parse('2026-08-30');
        for ($index = 1; $index <= 50; $index++) {
            $staff = $this->staff('Employee', str_pad((string) $index, 3, '0', STR_PAD_LEFT));
            $this->log($staff->user, 'morning_in', 'time_in', $date->copy()->setTime(8, 5));
            $this->log($staff->user, 'lunch_out', 'time_out', $date->copy()->setTime(12, 5));
            $this->log($staff->user, 'afternoon_in', 'time_in', $date->copy()->setTime(13, 5));
            $this->log($staff->user, 'final_out', 'time_out', $date->copy()->setTime(17, 5));
        }

        // 1. Verify Page (HTML)
        $response = $this->actingAs($admin)->get(route('admin.reports.daily-personnel.index', ['date' => $date->toDateString()]));
        $response->assertOk();
        $response->assertSee('Employee 001');
        $response->assertSee('Employee 050');
        $response->assertSee('8:05 AM');
        $response->assertSee('12:05 PM');
        $response->assertSee('1:05 PM');
        $response->assertSee('5:05 PM');

        // 2. Verify PDF and Excel Export
        $service = app(PersonnelAttendanceReportService::class);
        $renderer = app(DailyPersonnelAttendanceExportService::class);
        $reportData = $service->getDailyReport($date);

        // PDF Generation
        $pdfOutput = $renderer->pdf($reportData);
        $this->assertNotEmpty($pdfOutput);
        $this->assertStringStartsWith('%PDF', $pdfOutput);

        // Excel Generation
        $xlsxOutput = $renderer->xlsx($reportData);
        $this->assertNotEmpty($xlsxOutput);
        $tempPath = tempnam(sys_get_temp_dir(), 'report_test_').'.xlsx';
        file_put_contents($tempPath, $xlsxOutput);
        $spreadsheet = IOFactory::load($tempPath);
        $sheet = $spreadsheet->getActiveSheet();
        @unlink($tempPath);

        // Header and row assertions
        $this->assertSame('DANAO TECHNOLOGICAL COLLEGE', $sheet->getCell('A1')->getValue());
        $this->assertSame('Employee 001', $sheet->getCell('A7')->getValue());
        $this->assertSame('8:05 AM', (string) $sheet->getCell('B7')->getValue());
        $this->assertSame('12:05 PM', (string) $sheet->getCell('C7')->getValue());
        $this->assertSame('1:05 PM', (string) $sheet->getCell('D7')->getValue());
        $this->assertSame('5:05 PM', (string) $sheet->getCell('E7')->getValue());

        // Row 50 (7 + 49 = 56)
        $this->assertSame('Employee 050', $sheet->getCell('A56')->getValue());
        $this->assertSame('8:05 AM', (string) $sheet->getCell('B56')->getValue());
        $this->assertSame('12:05 PM', (string) $sheet->getCell('C56')->getValue());
        $this->assertSame('1:05 PM', (string) $sheet->getCell('D56')->getValue());
        $this->assertSame('5:05 PM', (string) $sheet->getCell('E56')->getValue());
    }

    public function test_excel_export_sanitizes_potential_formula_injection_names(): void
    {
        $formulaNames = ['=1+1', '+SUM(A1:A5)', '-5+10', '@admin'];
        $date = Carbon::parse('2026-08-30');
        $rows = collect($formulaNames)->map(fn ($name) => [
            'name' => $name,
            'morning_time_in' => '8:00 AM',
            'morning_time_out' => '12:00 PM',
            'afternoon_time_in' => '1:00 PM',
            'afternoon_time_out' => '5:00 PM',
        ]);

        $renderer = app(DailyPersonnelAttendanceExportService::class);
        $xlsxOutput = $renderer->xlsx(['date' => $date, 'rows' => $rows, 'filters' => []]);

        $tempPath = tempnam(sys_get_temp_dir(), 'report_formula_').'.xlsx';
        file_put_contents($tempPath, $xlsxOutput);
        $sheet = IOFactory::load($tempPath)->getActiveSheet();
        @unlink($tempPath);

        foreach ($formulaNames as $i => $expectedName) {
            $cell = $sheet->getCell('A' . (7 + $i));
            $this->assertSame('s', $cell->getDataType(), "Cell for '{$expectedName}' must be string type");
            $this->assertSame($expectedName, $cell->getValue(), "Cell value for '{$expectedName}' must match exactly");
        }
    }

    public function test_export_recovery_when_job_retried_in_processing_state(): void
    {
        $disk = Storage::fake('local');
        $admin = $this->user('admin');
        $staff = $this->staff('Retry', 'Person');
        $date = Carbon::parse('2026-08-30');
        $this->log($staff->user, 'morning_in', 'time_in', $date->copy()->setTime(8, 0));

        $export = ReportExport::create([
            'requested_by' => $admin->id,
            'report_type' => ReportExport::TYPE_DAILY_PERSONNEL,
            'format' => 'xlsx',
            'parameters' => ['date' => $date->toDateString(), 'filters' => []],
            'status' => ReportExport::STATUS_PROCESSING,
            'expires_at' => now()->addDay(),
        ]);

        $job = new GenerateDailyPersonnelExport($export->id);
        $job->handle(
            app(PersonnelAttendanceReportService::class),
            app(DailyPersonnelAttendanceExportService::class),
        );

        $export->refresh();
        $this->assertSame(ReportExport::STATUS_COMPLETED, $export->status);
        $this->assertNotNull($export->path);
        $disk->assertExists($export->path);
    }

    private function user(string $role): User
    {
        return User::factory()->create(['role_id' => Role::findByName($role, 'web')->id]);
    }

    private function instructor(string $firstName, string $lastName, ?Department $department = null): Instructor
    {
        return Instructor::create([
            'user_id' => $this->user('instructor')->id,
            'department_id' => ($department ?? $this->department)->id,
            'employee_no' => fake()->unique()->numerify('INS-#####'),
            'first_name' => $firstName,
            'last_name' => $lastName,
        ])->load('user');
    }

    private function staff(string $firstName, string $lastName, ?OfficeUnit $officeUnit = null): NonTeachingStaff
    {
        return NonTeachingStaff::create([
            'user_id' => $this->user('staff')->id,
            'office_unit_id' => ($officeUnit ?? $this->officeUnit)->id,
            'employee_no' => fake()->unique()->numerify('STF-#####'),
            'first_name' => $firstName,
            'last_name' => $lastName,
        ])->load('user');
    }

    private function log(User $user, string $period, string $type, Carbon $at, string $punctuality = 'on_time'): AttendanceLog
    {
        return AttendanceLog::create([
            'user_id' => $user->id,
            'attendance_type' => $type,
            'attendance_period' => $period,
            'scan_time' => $at,
            'status' => $punctuality === 'late' ? 'late' : 'present',
            'punctuality_status' => $punctuality,
        ]);
    }

    private function legacyLog(User $user, string $period, string $type, Carbon $at): void
    {
        DB::table('attendance_logs')->insert([
            'user_id' => $user->id,
            'attendance_type' => $type,
            'attendance_period' => $period,
            'scan_time' => $at,
            'status' => 'present',
            'punctuality_status' => 'on_time',
            'record_state' => 'canonical',
            'created_at' => $at,
            'updated_at' => $at,
        ]);
    }
}
