<?php

namespace App\Services;

use App\Models\SchoolEvent;
use App\Models\User;
use Illuminate\Support\Carbon;

class PersonnelWorkCalendar
{
    public function context(User $user, Carbon $from, Carbon $to): array
    {
        $user->loadMissing(['role', 'instructor', 'nonTeachingStaff']);
        $profile = $user->isInstructor() ? $user->instructor : $user->nonTeachingStaff;
        $events = SchoolEvent::query()
            ->where('status', 'published')
            ->where('attendance_mode', 'cancelled')
            ->where('starts_at', '<=', $to->copy()->endOfDay())
            ->where('ends_at', '>=', $from->copy()->startOfDay())
            ->get();

        return compact('user', 'profile', 'events');
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

}
