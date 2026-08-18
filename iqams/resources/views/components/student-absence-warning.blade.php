@props(['warnings'])

@if($warnings->isNotEmpty())
    <section {{ $attributes->class('mb-6 border border-amber-300 bg-amber-50') }} role="alert" aria-labelledby="absence-warning-title">
        <div class="flex gap-3 border-b border-amber-200 px-4 py-3 sm:px-5">
            <svg class="mt-0.5 h-5 w-5 shrink-0 text-amber-700" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 9v4m0 4h.01M10.3 4.4L2.8 17.5A1 1 0 003.7 19h16.6a1 1 0 00.9-1.5L13.7 4.4a1 1 0 00-1.7 0z" />
            </svg>
            <div>
                <h2 id="absence-warning-title" class="text-sm font-semibold text-amber-950">Attendance warning</h2>
                <p class="mt-0.5 text-sm leading-5 text-amber-900">You have reached at least five absences in the {{ Str::plural('subject', $warnings->count()) }} below. Please contact your instructor or department for guidance.</p>
            </div>
        </div>
        <ul class="divide-y divide-amber-200/80">
            @foreach($warnings as $warning)
                <li class="flex items-center justify-between gap-4 px-4 py-3 sm:px-5">
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-slate-900">{{ $warning->subject_code }}</p>
                        <p class="truncate text-xs text-slate-600">{{ $warning->subject_name }}</p>
                    </div>
                    <span class="shrink-0 rounded-md bg-red-100 px-2.5 py-1 text-xs font-semibold text-red-700">{{ $warning->absence_count }} {{ Str::plural('absence', $warning->absence_count) }}</span>
                </li>
            @endforeach
        </ul>
    </section>
@endif
