<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Section;
use App\Models\Schedule;
use App\Models\Student;
use App\Models\User;
use App\Services\AccountInvitationService;
use App\Services\AdminAccountProtectionService;
use App\Services\AuditLogger;
use App\Services\QrCredentialService;
use App\Services\RoleAssignmentService;
use App\Services\ProfileImageService;
use App\Services\StudentScheduleEligibility;
use App\Rules\SectionBelongsToCourse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class StudentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $students = Student::query()
            ->select(['id', 'user_id', 'course_id', 'section_id', 'enrollment_type', 'student_no', 'first_name', 'last_name', 'middle_name', 'status', 'created_at'])
            ->with([
                'user:id,username,name,email,avatar_path,status,must_change_password',
                'course:id,course_code,course_name',
                'section:id,section_name,course_id',
                'scheduleEnrollments:student_id,recurring_schedule_group_id',
            ])
            ->latest('students.created_at')->paginate(10);

        $courses = Course::active()->orderBy('course_name')->get(['id', 'department_id', 'course_code', 'course_name']);

        $sections = Section::active()->with('course:id,course_code')->orderBy('section_name')->get(['id', 'course_id', 'section_name']);
        $offerings = Schedule::active()->with(['subject:id,subject_code,subject_name', 'section:id,section_name', 'instructor:id,first_name,last_name'])
            ->orderBy('start_time')->get(['id', 'recurring_schedule_group_id', 'subject_id', 'section_id', 'instructor_id', 'day', 'start_time', 'end_time', 'room'])
            ->groupBy('recurring_schedule_group_id')->map(function ($schedules, $groupId) {
                $first = $schedules->first();
                return [
                    'id' => $groupId,
                    'label' => sprintf('%s — %s (%s), %s-%s, %s', $first->subject->subject_code, $first->section->section_name,
                        $schedules->pluck('day')->map(fn ($day) => ucfirst(substr($day, 0, 3)))->implode('/'),
                        \Carbon\Carbon::parse($first->start_time)->format('g:i A'), \Carbon\Carbon::parse($first->end_time)->format('g:i A'), $first->room),
                ];
            })->values();

        return view('students.index', compact('students', 'courses', 'sections', 'offerings'));

    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, StudentScheduleEligibility $eligibility)
    {
        $validated = $request->validate([
            'course_id' => ['required', Rule::exists('courses', 'id')->whereNull('archived_at')],
            'section_id' => ['nullable', Rule::exists('sections', 'id')->whereNull('archived_at'), new SectionBelongsToCourse($request->input('course_id'))],
            'enrollment_type' => ['required', Rule::in(['regular', 'irregular'])],
            'enrollment_group_ids' => ['nullable', 'array'],
            'enrollment_group_ids.*' => ['uuid', 'distinct'],
            'student_no' => 'required|string|max:50|unique:students,student_no|unique:users,username',
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'email' => 'required|email|unique:users,email',
            'avatar' => 'required|image|mimes:jpg,jpeg,png|max:2048',
        ]);
        $this->validateEnrollmentGroups($validated);

        $avatarPath = app(ProfileImageService::class)->store($request->file('avatar'));

        $administrator = $request->user();

        try {
            $user = DB::transaction(function () use ($validated, $administrator, $avatarPath, $eligibility) {
                $user = User::create([
                    'username' => $validated['student_no'],
                    'name' => $validated['first_name'].' '.$validated['last_name'],
                    'email' => $validated['email'],
                    'avatar_path' => $avatarPath,
                    'password' => Hash::make(Str::random(64)),
                    'status' => 'active',
                ]);

                $user->forceFill([
                    'must_change_password' => true,
                    'password_changed_at' => null,
                    'email_verified_at' => null,
                ])->saveQuietly();
                app(RoleAssignmentService::class)->assign($user, 'student', $administrator);

                $student = Student::create([
                    'user_id' => $user->id,
                    'student_no' => $validated['student_no'],
                    'first_name' => $validated['first_name'],
                    'last_name' => $validated['last_name'],
                    'middle_name' => $validated['middle_name'] ?? null,
                    'section_id' => $validated['section_id'] ?? null,
                    'enrollment_type' => $validated['enrollment_type'],
                    'course_id' => $validated['course_id'],
                    'status' => 'active',
                    'qr_code' => null,
                ]);
                $eligibility->sync($student, $validated['enrollment_group_ids'] ?? []);

                app(QrCredentialService::class)->issue($user, $administrator);
                app(AccountInvitationService::class)->queue($user, $administrator);

                return $user;
            });
        } catch (\Throwable $exception) {
            app(ProfileImageService::class)->delete($avatarPath);
            throw $exception;
        }

        app(AuditLogger::class)->record('account.created', $user, ['role' => 'student'], $administrator, $request);

        return redirect()->route('students.index')
            ->with('success', 'Account created. A setup email has been queued.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Student $student)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Student $student)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Student $student, StudentScheduleEligibility $eligibility)
    {
        $validated = $request->validate([
            'course_id' => ['required', Rule::exists('courses', 'id')->whereNull('archived_at')],
            'section_id' => ['nullable', Rule::exists('sections', 'id')->whereNull('archived_at'), new SectionBelongsToCourse($request->input('course_id'))],
            'enrollment_type' => ['required', Rule::in(['regular', 'irregular'])],
            'enrollment_group_ids' => ['nullable', 'array'],
            'enrollment_group_ids.*' => ['uuid', 'distinct'],
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'status' => 'required|in:active,inactive,graduated,dropped',
            'avatar' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ]);
        $this->validateEnrollmentGroups($validated);

        $oldAvatarPath = $student->user->avatar_path;
        $newAvatarPath = $request->hasFile('avatar')
            ? app(ProfileImageService::class)->store($request->file('avatar'))
            : null;

        try {
            DB::transaction(function () use ($validated, $student, $newAvatarPath, $eligibility) {
                $student->update($validated);
                $eligibility->sync($student, $validated['enrollment_group_ids'] ?? []);
                $student->user->update(array_filter([
                    'name' => $validated['first_name'].' '.$validated['last_name'],
                    'avatar_path' => $newAvatarPath,
                ], fn ($value) => $value !== null));
            });
        } catch (\Throwable $exception) {
            if ($newAvatarPath) {
                app(ProfileImageService::class)->delete($newAvatarPath);
            }
            throw $exception;
        }

        if ($newAvatarPath && $oldAvatarPath) {
            app(ProfileImageService::class)->delete($oldAvatarPath);
        }

        app(AuditLogger::class)->record('account.profile_updated', $student->user, [
            'profile' => 'student',
            'profile_id' => $student->id,
        ], $request->user(), $request);

        return redirect()->route('students.index')->with('success', 'Student updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, Student $student)
    {
        $user = $student->user;

        DB::transaction(function () use ($student, $user, $request) {
            $lockedUser = app(AdminAccountProtectionService::class)->assertCanChangeStatus($user, 'inactive');
            $oldStatus = $lockedUser->status;
            $lockedUser->update(['status' => 'inactive']);
            app(AuditLogger::class)->record('account.status_changed', $lockedUser, [
                'from' => $oldStatus,
                'to' => 'inactive',
                'record' => 'student_account',
                'profile_id' => $student->id,
            ], $request->user(), $request);
        });

        return redirect()->route('students.index')->with('success', 'Student account deactivated successfully.');
    }

    private function validateEnrollmentGroups(array $validated): void
    {
        $groupIds = collect($validated['enrollment_group_ids'] ?? [])->unique()->values();
        if ($validated['enrollment_type'] === 'irregular' && ($validated['status'] ?? 'active') === 'active' && $groupIds->isEmpty()) {
            throw ValidationException::withMessages(['enrollment_group_ids' => 'Select at least one class offering for an active irregular student.']);
        }

        if ($groupIds->isNotEmpty() && Schedule::active()->whereIn('recurring_schedule_group_id', $groupIds)->distinct('recurring_schedule_group_id')->count('recurring_schedule_group_id') !== $groupIds->count()) {
            throw ValidationException::withMessages(['enrollment_group_ids' => 'One or more selected class offerings are unavailable.']);
        }
    }
}
