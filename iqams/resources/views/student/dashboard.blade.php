<x-student-layout title="Dashboard">
    <section class="rounded-2xl bg-gradient-to-br from-teal-700 to-sky-700 px-6 py-7 text-white shadow-sm">
        <p class="font-display text-2xl font-semibold">Hi, {{ $student->first_name }} 👋</p>
        <p class="mt-1 text-sm text-teal-50/90">{{ $student->course->course_code ?? 'No course' }} @if($student->section) · {{ $student->section->section_name }} @endif</p>
    </section>
    <div class="my-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
        @foreach(['present' => 'text-emerald-600', 'late' => 'text-amber-500', 'absent' => 'text-red-500', 'excused' => 'text-sky-500'] as $status => $color)
            <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200/70"><p class="text-xs font-semibold uppercase tracking-wide text-slate-400">{{ ucfirst($status) }}</p><p class="mt-1 font-display text-3xl font-semibold {{ $color }}">{{ $stats[$status] }}</p></div>
        @endforeach
    </div>
    <div class="grid gap-6 lg:grid-cols-5">
        <section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200/70 lg:col-span-3">
            <h2 class="font-display font-semibold text-slate-900">My weekly schedule</h2>
            @if($schedules->isEmpty())<p class="py-10 text-center text-sm text-slate-400">{{ $student->section ? 'No schedule found for your section.' : "You haven't been assigned to a section yet." }}</p>@else
                <div class="mt-5 space-y-5">@foreach($dayOrder as $day) @if($scheduleByDay->has($day))<div><p class="mb-2 text-xs font-semibold uppercase tracking-wide text-teal-700">{{ ucfirst($day) }}</p><div class="space-y-2">@foreach($scheduleByDay[$day] as $item)<div class="flex items-center gap-3 rounded-xl border border-slate-100 px-3 py-3"><span class="w-1.5 self-stretch rounded-full bg-teal-500"></span><div class="min-w-0 flex-1"><p class="truncate text-sm font-medium">{{ $item->subject->subject_name ?? '—' }}</p><p class="truncate text-xs text-slate-400">{{ $item->instructor->first_name ?? '' }} {{ $item->instructor->last_name ?? '' }} · {{ $item->room }}</p></div><p class="text-xs text-slate-500">{{ \Illuminate\Support\Carbon::parse($item->start_time)->format('g:i A') }}</p></div>@endforeach</div></div>@endif @endforeach</div>
            @endif
        </section>
        <section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200/70 lg:col-span-2">
            <div class="flex items-center justify-between"><h2 class="font-display font-semibold text-slate-900">Recent attendance</h2><a href="{{ route('student.attendance') }}" class="text-xs font-semibold text-teal-700">View all</a></div>
            @if($myAttendance->isEmpty())<p class="py-10 text-center text-sm text-slate-400">No attendance records yet.</p>@else<div class="mt-5 space-y-4">@foreach($myAttendance as $log) @php $color = match($log->status) { 'present'=>'bg-emerald-500','late'=>'bg-amber-500','absent'=>'bg-red-500','excused'=>'bg-sky-500',default=>'bg-slate-300' }; @endphp<div class="flex items-start gap-3"><span class="mt-1.5 h-2 w-2 rounded-full {{ $color }}"></span><div class="min-w-0 flex-1"><p class="truncate text-sm">{{ $log->schedule?->subject?->subject_code ?? '—' }} <span class="text-slate-400">· {{ $log->attendance_type === 'time_in' ? 'Time In' : 'Time Out' }}</span></p><p class="text-xs text-slate-400">{{ \Illuminate\Support\Carbon::parse($log->scan_time)->format('M d, g:i A') }}</p></div><span class="text-xs font-medium capitalize text-slate-500">{{ $log->status }}</span></div>@endforeach</div>@endif
        </section>
    </div>
</x-student-layout>
