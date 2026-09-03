<?php

namespace App\Services;

use App\Models\SchoolEvent;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class PersonnelWorkCalendar
{
    public function __construct(private ScheduleOccurrenceResolver $occurrences) {}

    public function context(User $user, Carbon $from, Carbon $to): array
    {
        $user->loadMissing(['role', 'instructor.schedules', 'nonTeachingStaff']);
        $profile = $user->isInstructor() ? $user->instructor : $user->nonTeachingStaff;
        $schedules = $user->isInstructor() ? ($user->instructor?->schedules ?? collect()) : collect();
        $events = SchoolEvent::with('targets.schedule')
            ->where('status', 'published')
            ->where('attendance_mode', 'cancelled')
            ->where('starts_at', '<=', $to->copy()->endOfDay())
            ->where('ends_at', '>=', $from->copy()->startOfDay())
            ->get();

        return compact('user', 'profile', 'schedules', 'events');
    }

    public function exclusionReason(Carbon $date, array $context): ?string
    {
        /** @var User $user */
        $user = $context['user'];
        $profile = $context['profile'];

        if ($date->isWeekend()) {
            return 'Weekend';
        }

        if ($profile?->created_at && $date->isBefore($profile->created_at->copy()->startOfDay())) {
            return 'Before employment start';
        }

        if (! $user->isAccountActive()) {
            return 'Employee inactive';
        }

        if ($user->isInstructor()) {
            /** @var Collection $schedules */
            $schedules = $context['schedules'];
            $scheduledToday = $schedules->where('day', strtolower($date->format('l')));
            if ($scheduledToday->isEmpty()) {
                return 'Not scheduled';
            }

            $cancellations = $context['events']->filter(fn (SchoolEvent $event) =>
                $scheduledToday->contains(fn ($schedule) =>
                    $this->targetsSchedule($event, $schedule)
                    && $this->overlapsSchedule($event, $schedule, $date)
                )
            );
            $hasRequiredSchedule = $scheduledToday->contains(fn ($schedule) =>
                ! $cancellations->contains(fn (SchoolEvent $event) =>
                    $this->targetsSchedule($event, $schedule)
                    && $this->overlapsSchedule($event, $schedule, $date)
                )
            );

            return $hasRequiredSchedule
                ? null
                : 'Non-working day: '.$cancellations->pluck('title')->unique()->implode(', ');
        }

        $event = $context['events']->first(fn (SchoolEvent $event) =>
            $event->target_scope === 'school' && $this->overlapsDate($event, $date)
        );

        return $event ? 'Non-working day: '.$event->title : null;
    }

    private function overlapsDate(SchoolEvent $event, Carbon $date): bool
    {
        return $event->starts_at->lte($date->copy()->endOfDay())
            && $event->ends_at->gte($date->copy()->startOfDay());
    }

    private function targetsSchedule(SchoolEvent $event, $schedule): bool
    {
        if ($event->target_scope === 'school') {
            return true;
        }
        return $event->target_scope === 'schedules'
            ? $event->targets->contains('schedule_id', $schedule->id)
            : $event->targets->contains('section_id', $schedule->section_id);
    }

    private function overlapsSchedule(SchoolEvent $event, $schedule, Carbon $date): bool
    {
        $occurrence = $this->occurrences->forDate($schedule, $date);

        if (! $occurrence) {
            return false;
        }

        return $event->starts_at->lt($occurrence->endsAt)
            && $event->ends_at->gt($occurrence->startsAt);
    }
}
