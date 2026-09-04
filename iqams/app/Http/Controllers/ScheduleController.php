<?php

namespace App\Http\Controllers;

use App\Models\Schedule;
use App\Services\AuditLogger;
use App\Services\ArchiveService;
use App\Rules\ValidScheduleTimeWindow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ScheduleController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $schedules = Schedule::query()->active()
            ->select(['id', 'subject_id', 'instructor_id', 'section_id', 'day', 'start_time', 'end_time', 'room', 'recurring_schedule_group_id', 'created_at'])
            ->with([
                'subject:id,subject_code,subject_name',
                'instructor:id,first_name,last_name',
                'section:id,section_name',
                'recurringSchedules:id,recurring_schedule_group_id,day',
            ])
            ->latest('schedules.created_at')->paginate(10);

        $dayOrder = array_flip(['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday']);
        $schedules->getCollection()->each(function (Schedule $schedule) use ($dayOrder) {
            $days = ($schedule->recurring_schedule_group_id
                ? $schedule->recurringSchedules->pluck('day')
                : collect([$schedule->day]))->unique()
                ->sortBy(fn (string $day) => $dayOrder[$day])->values();
            $schedule->setAttribute('recurring_days', $days->all());
        });

        return view('schedules.index', compact('schedules'));
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
    public function store(Request $request)
    {
        $validated = $this->validateSchedule($request);

        DB::transaction(function () use ($validated, $request) {
            $this->ensureNoDuplicates($validated);
            $groupId = (string) Str::uuid();

            foreach ($validated['days'] as $day) {
                $schedule = Schedule::create($this->attributesForDay($validated, $day, $groupId));
                app(AuditLogger::class)->record('record.created', $schedule, ['record' => 'schedule'], $request->user(), $request);
            }
        });

        return redirect()->route('schedules.index')->with(
            'success',
            count($validated['days']).' schedule '.(count($validated['days']) === 1 ? 'record' : 'records').' created successfully.'
        );
    }

    /**
     * Display the specified resource.
     */
    public function show(Schedule $schedule)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Schedule $schedule)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Schedule $schedule)
    {
        $validated = $this->validateSchedule($request);

        DB::transaction(function () use ($validated, $schedule, $request) {
            $schedule = Schedule::query()->lockForUpdate()->findOrFail($schedule->getKey());
            $group = $schedule->recurring_schedule_group_id
                ? Schedule::query()
                    ->where('recurring_schedule_group_id', $schedule->recurring_schedule_group_id)
                    ->lockForUpdate()->get()
                : collect([$schedule]);
            $applyToRecurring = ($validated['apply_to_recurring'] ?? false) && $group->count() > 1;

            if ($applyToRecurring) {
                $validated['days'] = $group->pluck('day')->all();
                $this->ensureNoDuplicates($validated, $group->modelKeys());

                foreach ($group as $groupSchedule) {
                    $groupSchedule->update($this->attributesForDay(
                        $validated,
                        $groupSchedule->day,
                        $schedule->recurring_schedule_group_id,
                    ));
                    app(AuditLogger::class)->record('record.updated', $groupSchedule, ['record' => 'schedule', 'scope' => 'recurring_group'], $request->user(), $request);
                }

                return;
            }

            $this->ensureNoDuplicates($validated, [$schedule->getKey()]);
            $newGroupId = (string) Str::uuid();

            $days = $validated['days'];
            $schedule->update($this->attributesForDay($validated, array_shift($days), $newGroupId));
            app(AuditLogger::class)->record('record.updated', $schedule, ['record' => 'schedule'], $request->user(), $request);

            foreach ($days as $day) {
                $created = Schedule::create($this->attributesForDay($validated, $day, $newGroupId));
                app(AuditLogger::class)->record('record.created', $created, ['record' => 'schedule'], $request->user(), $request);
            }
        });

        return redirect()->route('schedules.index')->with('success', 'Schedule days updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, Schedule $schedule)
    {
        app(ArchiveService::class)->archive($schedule, $request->user(), $request);

        return redirect()->route('schedules.index')->with('success', 'Schedule archived successfully.');
    }

    private function validateSchedule(Request $request): array
    {
        return $request->validate([
            'subject_id' => ['required', Rule::exists('subjects', 'id')->whereNull('archived_at')],
            'instructor_id' => ['required', 'exists:instructors,id'],
            'section_id' => ['required', Rule::exists('sections', 'id')->whereNull('archived_at')],
            'days' => ['required', 'array', 'min:1'],
            'days.*' => ['required', 'distinct', 'in:monday,tuesday,wednesday,thursday,friday,saturday,sunday'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', new ValidScheduleTimeWindow($request->input('start_time'))],
            'room' => ['required', 'string', 'max:50'],
            'apply_to_recurring' => ['sometimes', 'boolean'],
        ], [
            'days.required' => 'Select at least one day.',
            'days.min' => 'Select at least one day.',
            'days.*.in' => 'One or more selected days are invalid.',
        ]);
    }

    private function ensureNoDuplicates(array $data, array $exceptIds = []): void
    {
        $duplicates = Schedule::query()
            ->where('subject_id', $data['subject_id'])
            ->where('section_id', $data['section_id'])
            ->where('instructor_id', $data['instructor_id'])
            ->whereIn('day', $data['days'])
            ->whereTime('start_time', $data['start_time'])
            ->whereTime('end_time', $data['end_time'])
            ->where('room', $data['room'])
            ->whereNull('archived_at')
            ->when($exceptIds !== [], fn ($query) => $query->whereKeyNot($exceptIds))
            ->pluck('day')
            ->map(fn ($day) => ucfirst($day))
            ->all();

        if ($duplicates !== []) {
            throw ValidationException::withMessages([
                'days' => 'An equivalent schedule already exists for: '.implode(', ', $duplicates).'.',
            ]);
        }
    }

    private function attributesForDay(array $data, string $day, string $groupId): array
    {
        return [
            'subject_id' => $data['subject_id'],
            'recurring_schedule_group_id' => $groupId,
            'instructor_id' => $data['instructor_id'],
            'section_id' => $data['section_id'],
            'day' => $day,
            'start_time' => $data['start_time'],
            'end_time' => $data['end_time'],
            'room' => $data['room'],
        ];
    }
}
