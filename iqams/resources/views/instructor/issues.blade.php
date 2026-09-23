<x-instructor-layout title="Attendance Issues">
    <div class="mb-6">
        <h2 class="text-xl font-semibold">Attendance Issues</h2>
        <p class="text-sm text-slate-500">Automatically detected issues for the current month.</p>
    </div>
    <section class="mb-6 rounded-2xl border border-rose-200 bg-rose-50 p-5 shadow-sm" aria-labelledby="student-risk-title">
        <div class="flex items-start justify-between gap-3">
            <div>
                <h2 id="student-risk-title" class="text-lg font-bold text-rose-900">Student dropout-risk warnings</h2>
                <p class="mt-1 text-sm text-rose-800">Students with at least {{ \App\Services\StudentAbsenceWarningService::THRESHOLD }} absences in one of your subjects.</p>
            </div>
            <span class="rounded-full bg-rose-100 px-3 py-1 text-xs font-bold text-rose-700">At Risk</span>
        </div>
        <div class="mt-4 space-y-3">
            @forelse($studentWarnings as $warning)
                <div class="rounded-xl bg-white p-4 ring-1 ring-rose-200">
                    <div class="flex flex-col justify-between gap-2 sm:flex-row">
                        <div>
                            <p class="font-bold text-slate-900">{{ $warning['student_name'] }} <span class="font-normal text-slate-500">({{ $warning['student_no'] }})</span></p>
                            <p class="text-sm text-slate-600">{{ $warning['subject_code'] }} · {{ $warning['subject_name'] }} · {{ $warning['section_name'] }}</p>
                        </div>
                        <span class="self-start rounded-lg bg-rose-100 px-3 py-1 text-sm font-bold text-rose-700">{{ $warning['absence_count'] }} absences · {{ $warning['status'] }}</span>
                    </div>
                    <div class="mt-2 flex flex-wrap items-center justify-between gap-2">
                        <p class="text-xs text-slate-500">Review the class attendance history for the affected dates before taking action.</p>
                        <a href="{{ route('instructor.schedule') }}" class="text-xs font-bold text-rose-700 hover:text-rose-900">Review attendance</a>
                    </div>
                </div>
            @empty
                <p class="rounded-xl bg-white p-4 text-sm text-emerald-700 ring-1 ring-emerald-200">No student dropout-risk warnings.</p>
            @endforelse
        </div>
    </section>
    <div class="space-y-4">
        @forelse($days as $day)
            <div
                class="flex flex-col justify-between gap-3 rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200/70 md:flex-row">
                <div>
                    <p class="font-semibold">{{ $day['date']->format('F j, Y') }}</p>
                    <p class="text-sm text-slate-500">
                        @if ($day['status'] === 'Absent')
                            No Time-In recorded (Absent)
                        @elseif($day['isIncomplete'])
                            Missing:
                            {{ $day['events']->filter(fn($v) => !$v)->keys()->map(fn($v) => str($v)->replace('_', ' ')->title())->join(', ') }}@else{{ $day['notes']->join(', ') }}
                        @endif
                    </p>
                </div><span
                    class="self-start rounded-full bg-amber-50 px-3 py-1 text-sm text-amber-700">{{ $day['isIncomplete'] ? 'Incomplete' : $day['status'] }}
                    · {{ $day['punctuality'] }}</span>
        </div>@empty<div class="rounded-2xl bg-white p-8 text-center text-slate-500 shadow-sm">No attendance issues
                found this month.</div>
        @endforelse
    </div>
</x-instructor-layout>
