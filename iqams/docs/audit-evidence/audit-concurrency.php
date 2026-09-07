<?php
// Disposable audit only. Never point this script at an application database.
$root=__DIR__;
while (!is_file($root.'/artisan')) {
    $parent=dirname($root);
    if ($parent===$root) { throw new RuntimeException('IQAMS root not found'); }
    $root=$parent;
}
foreach (['APP_ENV'=>'testing','APP_CONFIG_CACHE'=>$root.'/bootstrap/cache/config.audit-unused.php','DB_CONNECTION'=>'mysql','DB_HOST'=>'127.0.0.1','DB_PORT'=>'13316','DB_DATABASE'=>'iqams_audit','DB_USERNAME'=>'root','DB_PASSWORD'=>'(empty)','DB_URL'=>'(null)','CACHE_STORE'=>'array','SESSION_DRIVER'=>'array','MAIL_MAILER'=>'array','QUEUE_CONNECTION'=>'sync','BCRYPT_ROUNDS'=>'4','VIEW_COMPILED_PATH'=>$root.'/storage/framework/testing/audit-views'] as $key=>$value) {
    putenv($key.'='.$value); $_ENV[$key]=$_SERVER[$key]=$value;
}
require $root.'/vendor/autoload.php';
$app=require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (config('database.connections.mysql.database')!=='iqams_audit' || (string)config('database.connections.mysql.port')!=='13316') { throw new RuntimeException('Isolation guard'); }
Illuminate\Support\Carbon::setTestNow(Illuminate\Support\Carbon::parse('2026-08-10 08:05:00','Asia/Manila'));
Illuminate\Support\Facades\Notification::fake();
$app['redirect']->setSession($app['session.store']);

use App\Models\{User,Role,Department,Course,Section,Subject,Instructor,Student,Schedule,AttendanceLog,LeaveRequest};
use App\Services\{QrCredentialService,QrAttendanceService};

function makeUser($role) { return User::factory()->create(['role_id'=>Role::findByName($role,'web')->id,'status'=>'active']); }

if (($argv[1]??'')==='worker') {
    $kind=$argv[2]; $user=User::findOrFail((int)$argv[3]); $deadline=(float)$argv[4];
    $qr=$kind==='scan' ? app(QrCredentialService::class)->plainText($user->qrCredentials()->where('status','active')->firstOrFail()) : null;
    while (microtime(true)<$deadline) { usleep(1000); }
    $start=hrtime(true);
    try {
        if ($kind==='scan') { app(QrAttendanceService::class)->record($qr,'Audit concurrency',now()); }
        else {
            $request=Illuminate\Http\Request::create('/staff/leave-requests','POST',['leave_type'=>'sick','start_date'=>'2026-08-12','end_date'=>'2026-08-12','reason'=>'Synthetic concurrency test']);
            $request->setUserResolver(fn()=>$user);
            app(App\Http\Controllers\LeaveRequestController::class)->store($request);
        }
        $result='created';
    } catch (App\Exceptions\AttendanceAlreadyRecordedException) { $result='duplicate'; }
    catch (Illuminate\Validation\ValidationException) { $result='validation_rejected'; }
    catch (Throwable $error) { $result='error:'.get_class($error).':'.$error->getCode(); }
    echo json_encode(['result'=>$result,'ms'=>round((hrtime(true)-$start)/1e6,2)]);
    exit;
}

app(Database\Seeders\RolePermissionSeeder::class)->run();
$department=Department::create(['department_code'=>'RACE','department_name'=>'Audit concurrency']);
$course=Course::create(['department_id'=>$department->id,'course_code'=>'RACE','course_name'=>'Audit']);
$section=Section::create(['course_id'=>$course->id,'section_name'=>'A','school_year'=>'2026-2027','semester'=>'1st']);
$subject=Subject::create(['subject_code'=>'RACE','subject_name'=>'Audit','units'=>3]);
$instructor=Instructor::create(['user_id'=>makeUser('instructor')->id,'department_id'=>$department->id,'employee_no'=>'RACE-INS','first_name'=>'Audit','last_name'=>'Instructor']);
$schedule=Schedule::create(['subject_id'=>$subject->id,'instructor_id'=>$instructor->id,'section_id'=>$section->id,'day'=>'monday','start_time'=>'08:00','end_time'=>'10:00','room'=>'Audit']);
$student=makeUser('student');
Student::create(['user_id'=>$student->id,'student_no'=>'RACE-STU','first_name'=>'Audit','last_name'=>'Student','course_id'=>$course->id,'section_id'=>$section->id,'status'=>'active','enrollment_type'=>'regular']);
app(QrCredentialService::class)->issue($student);
$staff=makeUser('staff');
foreach (['scan'=>[$student,8],'leave'=>[$staff,4]] as $kind=>[$user,$count]) {
    $jobs=[]; $deadline=microtime(true)+4;
    for ($index=0;$index<$count;$index++) {
        $process=proc_open([PHP_BINARY,__FILE__,'worker',$kind,(string)$user->id,(string)$deadline],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,$root);
        fclose($pipes[0]); $jobs[]=[$process,$pipes];
    }
    $results=[];
    foreach ($jobs as [$process,$pipes]) {
        $output=stream_get_contents($pipes[1]); $error=stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]); $exit=proc_close($process);
        $results[]=json_decode($output,true)??['result'=>'invalid_output','exit'=>$exit,'stderr_bytes'=>strlen($error)];
    }
    echo json_encode(['kind'=>$kind,'concurrency'=>$count,'results'=>$results,'stored_rows'=>$kind==='scan'?AttendanceLog::where('user_id',$user->id)->count():LeaveRequest::where('user_id',$user->id)->count()]).PHP_EOL;
}
