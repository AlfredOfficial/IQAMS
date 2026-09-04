<?php

namespace App\Services;

use App\Models\LeaveRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class LeaveOverlapService
{
    public const ACTIVE_STATUSES = ['pending', 'approved'];

    public function conflictingQuery(int $userId, string $startDate, string $endDate, ?int $exceptId = null): Builder
    {
        $endExclusive = Carbon::createFromFormat('!Y-m-d', $endDate, config('app.timezone'))
            ->addDay()
            ->toDateString();

        return LeaveRequest::query()
            ->where('user_id', $userId)
            ->whereIn('status', self::ACTIVE_STATUSES)
            ->where('start_date', '<', $endExclusive)
            ->where('end_date', '>=', $startDate)
            ->when($exceptId, fn (Builder $query) => $query->whereKeyNot($exceptId));
    }

    public function hasConflict(int $userId, string $startDate, string $endDate, ?int $exceptId = null): bool
    {
        return $this->conflictingQuery($userId, $startDate, $endDate, $exceptId)->exists();
    }

    public function lockUserRows(int $userId): Collection
    {
        return LeaveRequest::query()
            ->where('user_id', $userId)
            ->whereIn('status', self::ACTIVE_STATUSES)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
    }

    public function groups(): Collection
    {
        $groups = collect();

        LeaveRequest::query()
            ->whereIn('status', self::ACTIVE_STATUSES)
            ->orderBy('user_id')
            ->orderBy('start_date')
            ->orderBy('id')
            ->chunkById(500, function (Collection $requests) use (&$groups): void {
                foreach ($requests as $request) {
                    $groups->push($request);
                }
            });

        return $groups->groupBy('user_id')->flatMap(function (Collection $requests) {
            $result = collect();
            $current = collect();
            $currentEnd = null;

            foreach ($requests as $request) {
                if ($current->isEmpty() || $currentEnd === null || $request->start_date->toDateString() <= $currentEnd) {
                    $current->push($request);
                    $currentEnd = $currentEnd === null || $request->end_date->toDateString() > $currentEnd
                        ? $request->end_date->toDateString()
                        : $currentEnd;
                    continue;
                }

                if ($current->count() > 1) {
                    $result->push($current);
                }
                $current = collect([$request]);
                $currentEnd = $request->end_date->toDateString();
            }

            if ($current->count() > 1) {
                $result->push($current);
            }

            return $result;
        })->values();
    }

    public function assignOverlapGroup(Collection $requests): string
    {
        $groupId = (string) Str::uuid();
        LeaveRequest::query()->whereKey($requests->pluck('id')->all())->update([
            'overlap_group_id' => $groupId,
            'overlap_state' => 'open',
        ]);

        return $groupId;
    }
}
