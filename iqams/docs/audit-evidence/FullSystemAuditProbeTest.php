<?php

// Audit evidence: several tests deliberately assert current defects, not desired behavior.
// Kept outside tests/ so these probes do not become release acceptance tests.

use App\Models\{User,Role,LeaveRequest,ReportExport,Student,Course,Department,Section,Subject,Instructor,Schedule,AttendanceLog,ScannerTerminal,NonTeachingStaff};
use App\Services\{RoleAssignmentService,QrCredentialService,QrAttendanceService,PersonnelAttendanceReportService,DailyPersonnelAttendanceExportService,ProfileImageService};
use App\Http\Controllers\{LeaveRequestController,AdminLeaveRequestController};
use App\Jobs\GenerateDailyPersonnelExport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Storage,Notification,Hash,DB,Artisan};
use Illuminate\Support\Carbon;
use Illuminate\Http\{Request,UploadedFile};
use Database\Seeders\RolePermissionSeeder;

class FullSystemAuditProbeTest extends Tests\TestCase
{
    use RefreshDatabase;

    public function createApplication()
    {
        $application=parent::createApplication();
        $driver=config('database.default');
        $connection=config('database.connections.'.$driver);
        $isolated=$driver==='sqlite' && ($connection['database']??null)===':memory:';
        $isolated=$isolated || ($driver==='mysql'
            && ($connection['host']??null)==='127.0.0.1'
            && (string)($connection['port']??'')==='13316'
            && ($connection['database']??null)==='iqams_audit');
        if (!$application->environment('testing') || !$isolated || !empty($connection['url'])) {
            throw new RuntimeException('Audit probes require isolated SQLite memory or the disposable iqams_audit instance.');
        }
        return $application;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        Notification::fake();
        Storage::fake('local');
        Storage::fake('public');
        Carbon::setTestNow(Carbon::parse('2026-08-10 08:05:00', 'Asia/Manila'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function user(string $role): User
    {
        return User::factory()->create(['role_id'=>Role::findByName($role,'web')->id, 'status'=>'active']);
    }

    private function leave(User $owner): LeaveRequest
    {
        return LeaveRequest::create(['user_id'=>$owner->id,'leave_type'=>'sick','start_date'=>'2026-08-12','end_date'=>'2026-08-12','reason'=>'Synthetic audit fixture'])->fresh();
    }

    public function test_stale_pending_cancel_overwrites_approved_leave(): void
    {
        $owner=$this->user('staff');
        $admin=$this->user('admin');
        $stale=$this->leave($owner);
        LeaveRequest::whereKey($stale->id)->update(['status'=>'approved','reviewed_by'=>$admin->id,'reviewed_at'=>now()]);
        $request=Request::create('/leave-requests/'.$stale->id.'/cancel','PATCH');
        $request->setUserResolver(fn()=>$owner);
        app(LeaveRequestController::class)->cancel($request,$stale);
        $this->assertSame('cancelled',$stale->fresh()->status,'Audit reproduces stale cancellation overwriting approval');
        $this->assertSame($admin->id,$stale->fresh()->reviewed_by);
    }

    public function test_stale_pending_review_overwrites_cancelled_leave(): void
    {
        $owner=$this->user('staff');
        $admin=$this->user('admin');
        $stale=$this->leave($owner);
        LeaveRequest::whereKey($stale->id)->update(['status'=>'cancelled']);
        $request=Request::create('/admin/leave-requests/'.$stale->id,'PATCH',['status'=>'approved']);
        $request->setUserResolver(fn()=>$admin);
        app(AdminLeaveRequestController::class)->update($request,$stale);
        $this->assertSame('approved',$stale->fresh()->status,'Audit reproduces stale approval overwriting cancellation');
    }

    public function test_export_retry_drops_processing_record_without_rendering(): void
    {
        $owner=$this->user('admin');
        $export=ReportExport::create(['requested_by'=>$owner->id,'report_type'=>ReportExport::TYPE_DAILY_PERSONNEL,'format'=>'pdf','parameters'=>['date'=>'2026-08-10','filters'=>[]],'status'=>'processing','expires_at'=>now()->addDay()]);
        $reports=Mockery::mock(PersonnelAttendanceReportService::class);
        $reports->shouldNotReceive('getDailyReport');
        $renderer=Mockery::mock(DailyPersonnelAttendanceExportService::class);
        $renderer->shouldNotReceive('pdf');
        (new GenerateDailyPersonnelExport($export->id))->handle($reports,$renderer);
        $this->assertSame('processing',$export->fresh()->status);
        $this->assertNull($export->fresh()->path);
    }

    public function test_failed_avatar_writes_are_reported_as_success(): void
    {
        $disk=Mockery::mock();
        $disk->shouldReceive('put')->twice()->andReturn(false);
        Storage::shouldReceive('disk')->with('public')->andReturn($disk);
        $image=UploadedFile::fake()->image('audit.png',10,10);
        $path=app(ProfileImageService::class)->store($image);
        $this->assertStringStartsWith('avatars/',$path);
        $this->assertStringEndsWith('.jpg',$path);
    }

    public function test_overlapping_leave_upload_leaves_orphan_file(): void
    {
        $owner=$this->user('staff');
        $this->leave($owner);
        $this->actingAs($owner)->postJson(route('staff.leave-requests.store'),[
            'leave_type'=>'sick','start_date'=>'2026-08-12','end_date'=>'2026-08-12','reason'=>'Synthetic duplicate',
            'attachment'=>UploadedFile::fake()->create('audit.pdf',1,'application/pdf'),
        ])->assertUnprocessable();
        $this->assertSame(1,LeaveRequest::count());
        $this->assertCount(1,Storage::disk('local')->allFiles('leave-attachments'));
    }

    public function test_admin_reset_installs_predictable_password(): void
    {
        $admin=$this->user('admin');
        $target=$this->user('staff');
        $this->actingAs($admin)->withSession(['auth.password_confirmed_at'=>time()])
            ->post(route('users.password.reset',$target))->assertRedirect();
        $this->assertTrue(Hash::check('Staff@'.$target->username,$target->fresh()->password));
        $this->assertTrue($target->fresh()->must_change_password);
    }

    public function test_random_qr_still_records_student_attendance_after_promotion_to_admin(): void
    {
        [$student,$schedule]=$this->studentFixture();
        $credentials=app(QrCredentialService::class);
        $qr=$credentials->plainText($credentials->issue($student));
        app(RoleAssignmentService::class)->assign($student,'admin');
        $log=app(QrAttendanceService::class)->record($qr,'Audit',now());
        $this->assertSame('admin',$student->fresh()->primaryRoleName());
        $this->assertSame($schedule->id,$log->schedule_id);
    }

    public function test_spreadsheet_name_is_interpreted_as_formula(): void
    {
        $report=['date'=>now(),'rows'=>[['name'=>'=1+1','morning_time_in'=>'','morning_time_out'=>'','afternoon_time_in'=>'','afternoon_time_out'=>'']]];
        $method=new ReflectionMethod(DailyPersonnelAttendanceExportService::class,'spreadsheet');
        $book=$method->invoke(app(DailyPersonnelAttendanceExportService::class),$report);
        $this->assertSame('f',$book->getActiveSheet()->getCell('A7')->getDataType());
        $this->assertSame(2,$book->getActiveSheet()->getCell('A7')->getCalculatedValue());
        $this->assertSame('s',$book->getActiveSheet()->getCell('A7')->getDataType());
        $this->assertSame('=1+1',$book->getActiveSheet()->getCell('A7')->getValue());
        $book->disconnectWorksheets();
    }

    public function test_integrity_report_and_dry_run_leave_rows_unchanged(): void
    {
        [$student,$schedule]=$this->studentFixture();
        $before=[User::count(),AttendanceLog::count(),Schedule::count()];
        $this->assertSame(0,Artisan::call('integrity:report',['--format'=>'json']));
        $this->assertIsArray(json_decode(Artisan::output(),true));
        $this->assertSame(0,Artisan::call('integrity:reconcile',['--dry-run'=>true]));
        $this->assertSame($before,[User::count(),AttendanceLog::count(),Schedule::count()]);
    }

    public function test_student_creation_requires_enrollment_type_and_uses_predictable_password(): void
    {
        [$student,$schedule]=$this->studentFixture();
        $admin=$this->user('admin');
        $payload=['course_id'=>$student->student->course_id,'section_id'=>$student->student->section_id,'student_no'=>'AUD-NEW','first_name'=>'Audit','last_name'=>'New','email'=>'audit-new@example.test','avatar'=>UploadedFile::fake()->image('audit.png',10,10)];
        $this->actingAs($admin)->postJson(route('students.store'),$payload)->assertUnprocessable()->assertJsonValidationErrors('enrollment_type');
        $payload['enrollment_type']='regular';
        $this->post(route('students.store'),$payload)->assertRedirect(route('students.index'));
        $created=User::where('username','AUD-NEW')->firstOrFail();
        $this->assertTrue(Hash::check('Student@AUD-NEW',$created->password));
    }

    public function test_measure_representative_requests(): void
    {
        [$student,$schedule]=$this->studentFixture();
        $admin=$this->user('admin');
        $instructor=$schedule->instructor->user;
        $staff=null;
        foreach (range(1,40) as $index) {
            $user=$this->user('staff');
            NonTeachingStaff::create(['user_id'=>$user->id,'employee_no'=>'PERF-'.$index,'first_name'=>'Audit','last_name'=>'Staff '.$index]);
            $staff??=$user;
            foreach (['2026-08-05','2026-08-06','2026-08-07'] as $date) {
                foreach (['morning_in'=>'08:00','lunch_out'=>'12:00','afternoon_in'=>'13:00','final_out'=>'17:00'] as $period=>$time) {
                    AttendanceLog::create(['user_id'=>$user->id,'attendance_type'=>str_ends_with($period,'in')?'time_in':'time_out','attendance_period'=>$period,'scan_time'=>$date.' '.$time,'status'=>'present']);
                }
            }
        }
        $credential=app(QrCredentialService::class)->issue($student);
        $qr=app(QrCredentialService::class)->plainText($credential);
        app(QrAttendanceService::class)->record($qr,'Audit',now());
        $terminal=ScannerTerminal::create(['name'=>'Audit','location'=>'Audit','is_active'=>true]);
        $cases=[
            'admin.dashboard'=>[$admin,fn()=>$this->get(route('admin.dashboard'))],
            'student.dashboard'=>[$student,fn()=>$this->get(route('student.dashboard'))],
            'instructor.dashboard'=>[$instructor,fn()=>$this->get(route('instructor.dashboard'))],
            'staff.dashboard'=>[$staff,fn()=>$this->get(route('staff.dashboard'))],
            'people.lookup'=>[$admin,fn()=>$this->getJson(route('admin.lookups.people',['search'=>'Audit']))],
            'personnel.report'=>[$admin,fn()=>$this->get(route('admin.reports.daily-personnel.index',['date'=>'2026-08-07']))],
            'scanner.duplicate'=>[$admin,fn()=>$this->withSession(['scanner_terminal_id'=>$terminal->id])->postJson(route('attendance-scanner.scan'),['qr_code'=>$qr])],
        ];
        $results=[];
        foreach ($cases as $name=>[$user,$request]) {
            $this->actingAs($user); $request()->assertOk(); $times=[]; $queries=[]; $errors=0;
            for ($i=0;$i<20;$i++) {
                DB::enableQueryLog(); DB::flushQueryLog(); $started=hrtime(true);
                $response=$request(); $times[]=(hrtime(true)-$started)/1e6;
                $queries[]=count(DB::getQueryLog()); DB::disableQueryLog();
                if ($response->status()!==200) { $errors++; }
            }
            sort($times);
            $results[$name]=['n'=>20,'concurrency'=>1,'p50_ms'=>round(($times[9]+$times[10])/2,2),'p95_ms'=>round($times[18],2),'error_rate'=>$errors/20,'queries_min'=>min($queries),'queries_max'=>max($queries)];
            $this->assertSame(0,$errors,$name);
        }
        file_put_contents(storage_path('framework/testing/audit-benchmark-'.DB::getDriverName().'.json'),json_encode(['driver'=>DB::getDriverName(),'users'=>User::count(),'attendance_rows'=>AttendanceLog::count(),'results'=>$results],JSON_PRETTY_PRINT));
    }

    public function test_daily_report_skips_existing_scans_across_chunk_boundary(): void
    {
        $first=null;
        foreach (range(1,200) as $index) {
            $user=$this->user('staff'); $first??=$user;
            NonTeachingStaff::create(['user_id'=>$user->id,'employee_no'=>'CHUNK-'.$index,'first_name'=>'Audit','last_name'=>'Chunk '.$index]);
            foreach (['morning_in'=>'08:00','lunch_out'=>'12:00','afternoon_in'=>'13:00','final_out'=>'17:00'] as $period=>$time) {
                AttendanceLog::create(['user_id'=>$user->id,'attendance_type'=>str_ends_with($period,'in')?'time_in':'time_out','attendance_period'=>$period,'scan_time'=>'2026-08-10 '.$time,'status'=>'present']);
            }
        }
        $report=app(PersonnelAttendanceReportService::class)->getDailyReport(now(),['personnel_type'=>'staff']);
        $row=collect($report['rows'])->firstWhere('user_id',$first->id);
        $this->assertSame(4,AttendanceLog::where('user_id',$first->id)->count());
        $this->assertNotNull($row);
        $this->assertSame('', $row['afternoon_time_out'], 'Audit reproduces omission of stored final-out scan');
        $this->assertSame('5:00 PM', $row['afternoon_time_out'], 'Final-out scan is retained across chunk boundary');
    }

    private function studentFixture(): array
    {
        $department=Department::create(['department_code'=>'AUD','department_name'=>'Audit']);
        $course=Course::create(['department_id'=>$department->id,'course_code'=>'AUD','course_name'=>'Audit']);
        $section=Section::create(['course_id'=>$course->id,'section_name'=>'A','school_year'=>'2026-2027','semester'=>'1st']);
        $subject=Subject::create(['subject_code'=>'AUD','subject_name'=>'Audit','units'=>3]);
        $instructor=Instructor::create(['user_id'=>$this->user('instructor')->id,'department_id'=>$department->id,'employee_no'=>'AUD-INS','first_name'=>'Audit','last_name'=>'Instructor']);
        $schedule=Schedule::create(['subject_id'=>$subject->id,'section_id'=>$section->id,'instructor_id'=>$instructor->id,'day'=>'monday','start_time'=>'08:00','end_time'=>'10:00','room'=>'Audit']);
        $user=$this->user('student');
        Student::create(['user_id'=>$user->id,'student_no'=>'AUD-STU','first_name'=>'Audit','last_name'=>'Student','course_id'=>$course->id,'section_id'=>$section->id,'status'=>'active','enrollment_type'=>'regular']);
        return [$user->fresh(),$schedule];
    }
}
