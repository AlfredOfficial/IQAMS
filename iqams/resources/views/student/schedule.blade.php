<x-student-layout title="Schedule">
    <section class="border border-slate-200 bg-white px-5 py-5 sm:px-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="text-sm font-medium text-teal-700">Class schedule</p>
                <h2 class="mt-1 text-xl font-semibold text-slate-900">Weekly schedule</h2>
                <p class="mt-1 text-sm text-slate-500">
                    {{ $student->course?->course_code ?? 'Course not assigned' }}
                    @if ($student->section)
                        · Section {{ $student->section->section_name }}
                    @endif
                </p>
            </div>
            <span class="text-sm font-medium text-slate-400">
                {{ $schedules->count() }} {{ $schedules->count() === 1 ? 'class' : 'classes' }}
            </span>
        </div>
    </section>

    <section class="mt-6" aria-labelledby="weekly-schedule-title">
        <h2 id="weekly-schedule-title" class="sr-only">Weekly schedule by day</h2>

        @if ($schedules->isEmpty())
            <div class="border border-dashed border-slate-300 bg-white px-5 py-12 text-center text-sm text-slate-500">
                {{ $student->section ? 'No schedule found for your section.' : "You haven't been assigned to a section yet." }}
            </div>
        @else
            <div class="border border-slate-200 bg-white">
                @foreach ($dayOrder as $day)
                    @if ($scheduleByDay->has($day))
                        <section class="grid border-b border-slate-200 last:border-b-0 md:grid-cols-[140px_1fr]" aria-labelledby="schedule-{{ $day }}">
                            <h3 id="schedule-{{ $day }}" class="bg-slate-50 px-4 py-4 text-xs font-semibold uppercase tracking-wider text-teal-800">
                                {{ ucfirst($day) }}
                            </h3>
                            <div class="divide-y divide-slate-100">
                                @foreach ($scheduleByDay[$day] as $item)
                                    <article class="grid gap-3 px-4 py-4 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-center">
                                        <div class="min-w-0">
                                            <div class="flex flex-wrap items-baseline gap-x-2">
                                                <p class="text-sm font-bold text-teal-800">{{ $item->subject?->subject_code ?? '—' }}</p>
                                                <p class="text-sm font-semibold text-slate-900">{{ $item->subject?->subject_name ?? 'Subject unavailable' }}</p>
                                            </div>
                                            <p class="mt-1 text-xs text-slate-500">
                                                {{ trim(($item->instructor?->first_name ?? '').' '.($item->instructor?->last_name ?? '')) ?: 'Instructor not assigned' }}
                                                <span class="mx-1.5 text-slate-300">•</span>
                                                {{ $item->room ?: 'Room TBA' }}
                                            </p>
                                        </div>
                                        <p class="whitespace-nowrap text-left text-sm font-medium text-slate-700 sm:text-right">
                                            {{ \Illuminate\Support\Carbon::parse($item->start_time)->format('g:i A') }}
                                            –
                                            {{ \Illuminate\Support\Carbon::parse($item->end_time)->format('g:i A') }}
                                        </p>
                                    </article>
                                @endforeach
                            </div>
                        </section>
                    @endif
                @endforeach
            </div>
        @endif
    </section>
</x-student-layout>
