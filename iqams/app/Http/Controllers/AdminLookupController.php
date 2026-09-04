<?php

namespace App\Http\Controllers;

use App\Models\Instructor;
use App\Models\Schedule;
use App\Models\Section;
use App\Models\Subject;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class AdminLookupController extends Controller
{
    private const MAX_PER_PAGE = 25;

    public function people(Request $request): JsonResponse
    {
        $filters = $this->filters($request);
        $people = $this->peopleQuery($filters['search']);
        $selectedPeople = $this->peopleQuery('');
        $paginator = DB::query()
            ->fromSub($people, 'people')
            ->select(['user_id', 'first_name', 'middle_name', 'last_name', 'name_prefix', 'name_suffix', 'credentials', 'person_type'])
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->orderBy('user_id')
            ->paginate($filters['per_page'], ['*'], 'page', $filters['page']);

        $rows = collect($paginator->items());
        if ($filters['selected'] !== []) {
            $selected = DB::query()
                ->fromSub($selectedPeople, 'people')
                ->select(['user_id', 'first_name', 'middle_name', 'last_name', 'name_prefix', 'name_suffix', 'credentials', 'person_type'])
                ->whereIn('user_id', $filters['selected'])
                ->get();
            $rows = $selected->merge($rows)->unique(fn (object $row): string => $row->user_id.':'.$row->person_type)->values();
        }

        return $this->paginated($paginator, $rows->map(fn (object $person): array => [
            'id' => (int) $person->user_id,
            'label' => $this->personLabel($person),
            'type' => strtolower((string) $person->person_type),
        ])->all());
    }

    public function schedules(Request $request): JsonResponse
    {
        $filters = $this->filters($request);
        $query = Schedule::query()
            ->active()
            ->select(['id', 'subject_id', 'section_id', 'day', 'start_time', 'end_time'])
            ->with([
                'subject:id,subject_code,subject_name',
                'section:id,section_name',
            ])
            ->when($filters['search'], function ($query, string $search): void {
                $like = "%{$search}%";
                $query->where(function ($query) use ($like): void {
                    $query->where('day', 'like', $like)
                        ->orWhereHas('subject', fn ($subject) => $subject->where('subject_code', 'like', $like)->orWhere('subject_name', 'like', $like))
                        ->orWhereHas('section', fn ($section) => $section->where('section_name', 'like', $like));
                });
            })
            ->orderBy('day')
            ->orderBy('start_time');

        $paginator = $query->paginate($filters['per_page'], ['*'], 'page', $filters['page']);
        $rows = collect($paginator->items());
        if ($filters['selected'] !== []) {
            $selected = Schedule::query()
                ->whereIn('id', $filters['selected'])
                ->select(['id', 'subject_id', 'section_id', 'day', 'start_time', 'end_time'])
                ->with(['subject:id,subject_code,subject_name', 'section:id,section_name'])
                ->get();
            $rows = $selected->merge($rows)->unique('id')->values();
        }

        return $this->paginated($paginator, $rows->map(fn (Schedule $schedule): array => [
            'id' => $schedule->id,
            'label' => $this->scheduleLabel($schedule),
        ])->all());
    }

    public function subjects(Request $request): JsonResponse
    {
        $filters = $this->filters($request);
        $base = Subject::query()->active()
            ->select(['id', 'subject_code', 'subject_name'])
            ->orderBy('subject_name');
        $query = (clone $base)
            ->when($filters['search'], function ($query, string $search): void {
                $like = "%{$search}%";
                $query->where(fn ($query) => $query->where('subject_code', 'like', $like)->orWhere('subject_name', 'like', $like));
            });

        return $this->modelLookup($query, $filters, fn (Subject $subject): array => [
            'id' => $subject->id,
            'label' => "{$subject->subject_code} - {$subject->subject_name}",
        ], $base);
    }

    public function instructors(Request $request): JsonResponse
    {
        $filters = $this->filters($request);
        $base = Instructor::query()
            ->select(['id', 'first_name', 'last_name', 'employee_no'])
            ->orderBy('first_name')
            ->orderBy('last_name');
        $query = (clone $base)
            ->when($filters['search'], function ($query, string $search): void {
                $like = "%{$search}%";
                $query->where(fn ($query) => $query->where('first_name', 'like', $like)
                    ->orWhere('last_name', 'like', $like)
                    ->orWhere('employee_no', 'like', $like));
            });

        return $this->modelLookup($query, $filters, fn (Instructor $instructor): array => [
            'id' => $instructor->id,
            'label' => trim("{$instructor->first_name} {$instructor->last_name}"),
        ], $base);
    }

    public function sections(Request $request): JsonResponse
    {
        $filters = $this->filters($request);
        $base = Section::query()->active()
            ->select(['id', 'course_id', 'section_name'])
            ->with('course:id,course_code')
            ->orderBy('section_name');
        $query = (clone $base)
            ->when($filters['search'], function ($query, string $search): void {
                $like = "%{$search}%";
                $query->where(function ($query) use ($like): void {
                    $query->where('section_name', 'like', $like)
                        ->orWhereHas('course', fn ($course) => $course->where('course_code', 'like', $like));
                });
            });

        return $this->modelLookup($query, $filters, fn (Section $section): array => [
            'id' => $section->id,
            'label' => $section->section_name.' ('.($section->course?->course_code ?? '—').')',
        ], $base);
    }

    /**
     * @return array{search: string, page: int, per_page: int, selected: array<int, int>}
     */
    private function filters(Request $request): array
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:'.self::MAX_PER_PAGE],
            'selected' => ['nullable', 'array', 'max:25'],
            'selected.*' => ['integer', 'min:1'],
        ]);

        return [
            'search' => trim((string) ($validated['search'] ?? '')),
            'page' => (int) ($validated['page'] ?? 1),
            'per_page' => (int) ($validated['per_page'] ?? self::MAX_PER_PAGE),
            'selected' => array_map('intval', $validated['selected'] ?? []),
        ];
    }

    private function peopleQuery(string $search): QueryBuilder
    {
        $student = DB::table('students')
            ->select(['user_id', 'first_name', 'middle_name', 'last_name'])
            ->selectRaw('NULL as name_prefix, NULL as name_suffix, NULL as credentials, \'Student\' as person_type');
        $instructor = DB::table('instructors')
            ->select(['user_id', 'first_name', 'middle_name', 'last_name'])
            ->selectRaw('NULL as name_prefix, NULL as name_suffix, professional_credentials as credentials, \'Instructor\' as person_type');
        $staff = DB::table('non_teaching_staff')
            ->select(['user_id', 'first_name', 'middle_name', 'last_name'])
            ->selectRaw('name_prefix, name_suffix, NULL as credentials, \'Staff\' as person_type');

        foreach ([[$student, ['first_name', 'last_name', 'student_no']], [$instructor, ['first_name', 'last_name', 'employee_no']], [$staff, ['first_name', 'last_name', 'employee_no']]] as [$query, $columns]) {
            if ($search === '') {
                continue;
            }

            $like = "%{$search}%";
            $query->where(function ($query) use ($columns, $like): void {
                foreach ($columns as $index => $column) {
                    $index === 0
                        ? $query->where($column, 'like', $like)
                        : $query->orWhere($column, 'like', $like);
                }
            });
        }

        return $student->unionAll($instructor)->unionAll($staff);
    }

    private function personLabel(object $person): string
    {
        $name = collect([$person->name_prefix, $person->first_name, $person->middle_name, $person->last_name])
            ->map(fn ($part) => trim((string) $part))
            ->filter()
            ->implode(' ');
        $type = strtolower((string) $person->person_type);

        if ($type === 'student') {
            $name = trim("{$person->first_name} {$person->last_name}");
        }

        $suffix = trim((string) ($person->name_suffix ?: $person->credentials), " \t\n\r\0\x0B,");

        return ($suffix ? "{$name}, {$suffix}" : $name).' ('.ucfirst($type).')';
    }

    private function scheduleLabel(Schedule $schedule): string
    {
        return ($schedule->subject?->subject_code ?? '—')
            .' - '.ucfirst((string) $schedule->day)
            .' ('.($schedule->section?->section_name ?? '—').')';
    }

    private function modelLookup($query, array $filters, callable $map, $selectedQuery = null): JsonResponse
    {
        $paginator = $query->paginate($filters['per_page'], ['*'], 'page', $filters['page']);
        $rows = collect($paginator->items());

        if ($filters['selected'] !== []) {
            $selectedQuery ??= $query;
            $selected = (clone $selectedQuery)->whereIn($selectedQuery->getModel()->getTable().'.id', $filters['selected'])->get();
            $rows = $selected->merge($rows)->unique('id')->values();
        }

        return $this->paginated($paginator, $rows->map($map)->all());
    }

    private function paginated(LengthAwarePaginator $paginator, array $data): JsonResponse
    {
        return response()->json([
            'data' => array_values($data),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'has_more_pages' => $paginator->hasMorePages(),
            ],
        ]);
    }
}
