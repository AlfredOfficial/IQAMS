<?php

namespace App\Http\Controllers;

use App\Models\Schedule;
use App\Models\SchoolEvent;
use App\Models\Section;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SchoolEventController extends Controller
{
    public function index()
    {
        $events = SchoolEvent::with(['targets.section', 'targets.schedule.subject', 'targets.schedule.section'])
            ->withCount('attendanceLogs')->latest('starts_at')->paginate(15);

        return view('school-events.index', [
            'events' => $events,
            'sections' => Section::orderBy('section_name')->get(),
            'schedules' => Schedule::with(['subject', 'section'])->orderBy('day')->orderBy('start_time')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $event = DB::transaction(function () use ($data) {
            $event = SchoolEvent::create($this->attributes($data));
            $this->syncTargets($event, $data);

            return $event;
        });

        return redirect()->route('school-events.index')->with('success', "{$event->title} created as a draft.");
    }

    public function update(Request $request, SchoolEvent $schoolEvent)
    {
        $this->ensureEditable($schoolEvent);
        $data = $this->validated($request);
        DB::transaction(function () use ($schoolEvent, $data) {
            $schoolEvent->update($this->attributes($data));
            $this->syncTargets($schoolEvent, $data);
            if ($schoolEvent->status === 'published') {
                $schoolEvent->load('targets.schedule');
                $this->ensureTargets($schoolEvent);
                $this->ensureNoConflict($schoolEvent);
            }
        });

        return redirect()->route('school-events.index')->with('success', 'School event updated.');
    }

    public function publish(SchoolEvent $schoolEvent)
    {
        if ($schoolEvent->status !== 'draft' || now()->greaterThanOrEqualTo($schoolEvent->starts_at)) {
            throw ValidationException::withMessages(['event' => 'Only a draft event can be published before it starts.']);
        }
        $schoolEvent->load('targets.schedule');
        $this->ensureTargets($schoolEvent);
        $this->ensureNoConflict($schoolEvent);
        $schoolEvent->update(['status' => 'published', 'published_at' => now()]);

        return back()->with('success', 'School event published.');
    }

    public function cancel(SchoolEvent $schoolEvent)
    {
        if ($schoolEvent->status !== 'published' || now()->greaterThanOrEqualTo($schoolEvent->starts_at)
            || $schoolEvent->attendanceLogs()->exists()) {
            throw ValidationException::withMessages(['event' => 'This event can no longer be cancelled.']);
        }
        $schoolEvent->update(['status' => 'cancelled']);

        return back()->with('success', 'School event cancelled.');
    }

    public function destroy(SchoolEvent $schoolEvent)
    {
        if ($schoolEvent->attendanceLogs()->exists()) {
            throw ValidationException::withMessages(['event' => 'Events with attendance history cannot be deleted.']);
        }
        $schoolEvent->delete();

        return back()->with('success', 'School event deleted.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'location' => ['nullable', 'string', 'max:255'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'attendance_mode' => ['required', Rule::in(['cancelled', 'event_attendance', 'unchanged'])],
            'target_scope' => ['required', Rule::in(['school', 'sections', 'schedules'])],
            'section_ids' => ['required_if:target_scope,sections', 'array'],
            'section_ids.*' => ['integer', 'exists:sections,id'],
            'schedule_ids' => ['required_if:target_scope,schedules', 'array'],
            'schedule_ids.*' => ['integer', 'exists:schedules,id'],
        ]);
    }

    private function attributes(array $data): array
    {
        return collect($data)->only(['title', 'description', 'location', 'starts_at', 'ends_at', 'attendance_mode', 'target_scope'])->all();
    }

    private function syncTargets(SchoolEvent $event, array $data): void
    {
        $event->targets()->delete();
        if ($event->target_scope === 'sections') {
            foreach (array_unique($data['section_ids'] ?? []) as $id) {
                $event->targets()->create(['section_id' => $id]);
            }
        }
        if ($event->target_scope === 'schedules') {
            $schedules = Schedule::whereIn('id', array_unique($data['schedule_ids'] ?? []))->get();
            foreach ($schedules as $schedule) {
                if (! $this->scheduleOverlaps($schedule, Carbon::parse($event->starts_at), Carbon::parse($event->ends_at))) {
                    throw ValidationException::withMessages(['schedule_ids' => "{$schedule->subject?->subject_code} does not overlap the event date and time."]);
                }
                $event->targets()->create(['schedule_id' => $schedule->id]);
            }
        }
    }

    private function scheduleOverlaps(Schedule $schedule, Carbon $start, Carbon $end): bool
    {
        for ($date = $start->copy()->startOfDay(); $date->lessThanOrEqualTo($end->copy()->startOfDay()); $date->addDay()) {
            if (strtolower($date->format('l')) !== strtolower($schedule->day)) {
                continue;
            }
            $classStart = $date->copy()->setTimeFromTimeString($schedule->start_time);
            $classEnd = $date->copy()->setTimeFromTimeString($schedule->end_time);
            if ($classEnd->lessThanOrEqualTo($classStart)) {
                $classEnd->addDay();
            }
            if ($start->lessThan($classEnd) && $end->greaterThan($classStart)) {
                return true;
            }
        }

        return false;
    }

    private function ensureTargets(SchoolEvent $event): void
    {
        if ($event->target_scope !== 'school' && $event->targets->isEmpty()) {
            throw ValidationException::withMessages(['event' => 'Select at least one event target before publishing.']);
        }
    }

    private function ensureNoConflict(SchoolEvent $event): void
    {
        if ($event->attendance_mode !== 'event_attendance') {
            return;
        }
        $candidateSections = $this->sectionIds($event);
        $conflicts = SchoolEvent::with('targets.schedule')->where('status', 'published')
            ->where('attendance_mode', 'event_attendance')->whereKeyNot($event->id)
            ->where('starts_at', '<', $event->ends_at)->where('ends_at', '>', $event->starts_at)->get();
        if ($conflicts->contains(fn ($other) => $this->sectionIds($other)->intersect($candidateSections)->isNotEmpty())) {
            throw ValidationException::withMessages(['event' => 'A required attendance event already overlaps for one or more targeted students.']);
        }
    }

    private function sectionIds(SchoolEvent $event)
    {
        if ($event->target_scope === 'school') {
            return Section::pluck('id');
        }
        if ($event->target_scope === 'sections') {
            return $event->targets->pluck('section_id')->filter();
        }

        return $event->targets->pluck('schedule.section_id')->filter()->unique();
    }

    private function ensureEditable(SchoolEvent $event): void
    {
        if ($event->status === 'cancelled'
            || ($event->status === 'published' && now()->greaterThanOrEqualTo($event->starts_at))) {
            abort(409, 'A started published event cannot be edited.');
        }
    }
}
