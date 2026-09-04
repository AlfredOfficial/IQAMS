@php
    $periods = [
        'morning_in' => ['MORNING','Time-In'], 'lunch_out' => ['MORNING','Time-Out'],
        'afternoon_in' => ['AFTERNOON','Time-In'], 'final_out' => ['AFTERNOON','Time-Out'],
    ];
    $progressPeriods = [
        'morning_in' => 'Morning In', 'lunch_out' => 'Lunch Out',
        'afternoon_in' => 'Afternoon In', 'final_out' => 'Final Out',
    ];
    $greeting = now()->hour < 12 ? 'Good morning' : (now()->hour < 18 ? 'Good afternoon' : 'Good evening');
    $statusClasses = [
        'Present' => 'bg-emerald-100 text-emerald-700', 'Absent' => 'bg-red-100 text-red-700',
        'In Progress' => 'bg-amber-100 text-amber-700', 'Incomplete' => 'bg-amber-100 text-amber-700',
        'Not Started' => 'bg-slate-100 text-slate-600', 'On Leave' => 'bg-sky-100 text-sky-700',
    ];
    $workStart = config('attendance.personnel_windows.instructor.morning_in.window_start', '08:00');
    $workEnd = config('attendance.personnel_windows.instructor.final_out.window_end', '17:00');
    $icons = [
        'attendance'=>'M9 12l2 2 4-4m5-4v14a2 2 0 01-2 2H6a2 2 0 01-2-2V6a2 2 0 012-2h2m4-1h4a1 1 0 011 1v2H9V4a1 1 0 011-1h2z',
        'history'=>'M12 8v4l3 2m6-2a9 9 0 11-18 0 9 9 0 0118 0z','summary'=>'M4 19h16M7 16V9m5 7V5m5 11v-4',
        'schedule'=>'M8 7V3m8 4V3M5 11h14M5 5h14v16H5z','issues'=>'M12 9v4m0 4h.01M10.3 3.6L2.6 17a2 2 0 001.7 3h15.4a2 2 0 001.7-3L13.7 3.6a2 2 0 00-3.4 0z','profile'=>'M5.1 17.8a9 9 0 1113.8 0M15 11a3 3 0 11-6 0 3 3 0 016 0z'
    ];
    $chartWidth = 760;
    $chartHeight = 230;
    $chartTop = 16;
    $chartRight = 18;
    $chartBottom = 38;
    $chartLeft = 46;
    $chartPlotWidth = $chartWidth - $chartLeft - $chartRight;
    $chartPlotHeight = $chartHeight - $chartTop - $chartBottom;
    $chartDays = $monthDays->values();
    $chartLabelInterval = max(1, (int) ceil(max(1, $chartDays->count()) / 7));
    $chartPoints = $chartDays->map(function (array $day, int $index) use ($chartDays, $chartLeft, $chartPlotWidth, $chartTop, $chartPlotHeight, $chartLabelInterval): array {
        $value = ($day['isExcluded'] || $day['leave']) ? null : (int) $day['progressPercentage'];
        $x = $chartLeft + ($chartDays->count() > 1 ? $index * $chartPlotWidth / ($chartDays->count() - 1) : $chartPlotWidth / 2);
        $y = $chartTop + (100 - ($value ?? 0)) * $chartPlotHeight / 100;
        $color = match (true) {
            $value === null => '#cbd5e1',
            $day['status'] === 'Absent' => '#f43f5e',
            $day['status'] === 'Not Started' => '#94a3b8',
            $day['isIncomplete'] => '#fbbf24',
            $day['late'] => '#fb923c',
            $day['early'] => '#8b5cf6',
            default => '#34d399',
        };

        return [
            'day' => $day,
            'value' => $value,
            'x' => round($x, 2),
            'y' => round($y, 2),
            'color' => $color,
            'showLabel' => $index === 0 || $index === $chartDays->count() - 1 || $index % $chartLabelInterval === 0,
        ];
    });
    $chartSegments = [];
    $currentSegment = [];
    foreach ($chartPoints as $point) {
        if ($point['value'] === null) {
            if ($currentSegment) {
                $chartSegments[] = implode(' ', $currentSegment);
                $currentSegment = [];
            }
            continue;
        }
        $currentSegment[] = $point['x'].','.$point['y'];
    }
    if ($currentSegment) {
        $chartSegments[] = implode(' ', $currentSegment);
    }
    $chartHasData = $chartPoints->contains(fn (array $point) => $point['value'] !== null);
@endphp
<x-instructor-layout title="Dashboard">
<div x-data="instructorWorkspace" data-realtime-url="{{ route('instructor.dashboard.realtime') }}" data-id-card-url="{{ route('id-card.show') }}" class="mx-auto max-w-[1500px] space-y-4">
    @unless (Auth::user()->isAccountActive())
        <div class="rounded-2xl border border-red-200 bg-red-50 px-5 py-4 text-red-800">
            <div class="flex items-center gap-3"><span class="rounded-full bg-red-100 px-2.5 py-1 text-xs font-bold">Inactive</span><p class="text-sm font-semibold">Attendance unavailable. Your account is inactive. Please contact the administrator.</p></div>
        </div>
    @endunless
    <div class="grid gap-4 xl:grid-cols-[minmax(0,1.9fr)_minmax(310px,1fr)]">
        <div class="space-y-4">
            <section class="rounded-xl border border-gray-200 bg-white px-4 py-4 sm:px-5">
                <div class="mb-3"><h2 class="text-lg font-semibold text-gray-900">Today's attendance</h2><p class="text-sm text-gray-500">Your four daily attendance periods</p></div>
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div class="flex items-baseline gap-2"><p data-instructor-progress-count class="text-sm font-bold text-gray-900">{{ $today['completedPeriods'] }} of 4 completed</p><p data-instructor-progress-percent class="text-sm font-semibold text-emerald-700">{{ $today['progressPercentage'] }}%</p></div>
                    <span data-instructor-status class="inline-flex rounded-full px-3 py-1 text-xs font-bold {{ $statusClasses[$today['summaryStatus'] ?? $today['status']] ?? 'bg-amber-100 text-amber-700' }}">{{ $today['summaryStatus'] ?? $today['status'] }}</span>
                </div>
                <div class="mt-3 h-2.5 w-full overflow-hidden rounded-full bg-gray-200" role="progressbar" aria-label="Today's attendance progress" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $today['progressPercentage'] }}" data-instructor-progress-track>
                    <div data-instructor-progress-bar class="h-full rounded-full bg-emerald-500 transition-[width] duration-300" style="width: {{ $today['progressPercentage'] }}%"></div>
                </div>
                <div class="mt-4 grid grid-cols-2 gap-x-4 gap-y-3 lg:grid-cols-4">
                    @foreach ($progressPeriods as $key => $label)
                        @php($log = $today['events'][$key])
                        <div data-instructor-milestone="{{ $key }}" class="flex min-w-0 items-center gap-2 rounded-lg px-2 py-2 {{ $today['nextPeriod'] === $key ? 'bg-emerald-50' : '' }}">
                            <span data-instructor-milestone-icon class="grid h-6 w-6 shrink-0 place-items-center rounded-full text-sm font-bold {{ $log ? 'bg-emerald-500 text-white' : 'bg-gray-200 text-gray-500' }}">{{ $log ? '✓' : '○' }}</span>
                            <span data-instructor-milestone-label class="min-w-0 text-sm font-semibold {{ $log ? 'text-emerald-700' : 'text-gray-600' }}">{{ $label }}</span>
                        </div>
                    @endforeach
                </div>
                <p class="mt-3 border-t border-gray-100 pt-3 text-sm font-medium text-slate-600">Next: <span data-instructor-next class="font-bold text-slate-900">{{ $today['nextPeriod'] ? str($today['nextPeriod'])->replace('_', ' ')->title() : 'Complete' }}</span></p>
            </section>

            <div class="grid gap-4 lg:grid-cols-[.85fr_1.15fr]">
                <section class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm"><h2 class="text-xs font-extrabold uppercase text-[#10294b]">Punctuality Today</h2><div class="mt-3 divide-y divide-slate-100">@foreach($periods as $key => [$session,$label]) @php($log=$today['events'][$key])<div class="flex items-center gap-3 py-2.5"><span class="grid h-7 w-7 place-items-center rounded-full {{ $log ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-400' }}">◷</span><p class="flex-1 text-xs font-semibold">{{ ucfirst(strtolower($session)) }} {{ $label }}</p><span id="detail-{{ $key }}" class="text-right text-[11px] font-medium {{ in_array($log?->punctuality_status,['late','early_out'])?'text-rose-600':($log?'text-emerald-600':'text-slate-400') }}">{{ $log ? str($log->punctuality_status ?? 'on_time')->replace('_',' ')->title() : 'Not Yet Recorded' }}</span></div>@endforeach</div></section>
                <section class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm"><div class="flex items-center justify-between gap-3"><h2 class="text-xs font-extrabold uppercase text-[#10294b]">Monthly Attendance Overview</h2><a href="{{ route('instructor.summary') }}" class="shrink-0 rounded-lg bg-slate-50 px-2.5 py-1.5 text-[10px] font-semibold">{{ now()->format('F Y') }}⌄</a></div><div class="mt-3 grid grid-cols-2 gap-2 text-center sm:grid-cols-3">@foreach([['Attendance Rate','attendance',$totals['percentage'].'%','bg-blue-50 text-blue-700'],['Present','present',$totals['presentDays'].' days','bg-emerald-50 text-emerald-700'],['Absent','absent',$totals['absentDays'].' days','bg-rose-50 text-rose-700'],['Late','late',$totals['lateCount'].' days','bg-orange-50 text-orange-600'],['Early Out','early',$totals['earlyOutCount'].' days','bg-purple-50 text-purple-700'],['Incomplete','incomplete',$totals['incompleteCount'].' days','bg-amber-50 text-amber-700'],['In Progress','in_progress',$totals['inProgressCount'].' day'.($totals['inProgressCount'] === 1 ? '' : 's'),'bg-sky-50 text-sky-700']] as [$label,$key,$value,$class])<div class="rounded-lg p-2 {{ $class }}"><p class="text-[10px] text-slate-600">{{ $label }}</p><p data-stat="{{ $key }}" class="mt-1 text-lg font-extrabold">{{ $value }}</p></div>@endforeach</div><div class="mt-2 flex items-end justify-between rounded-lg border border-slate-100 px-3 py-2"><div><p class="text-[10px] text-slate-500">Total Hours</p><p data-stat="hours" class="text-lg font-extrabold text-slate-950">{{ intdiv($totals['totalMinutes'],60) }}h {{ $totals['totalMinutes']%60 }}m</p></div><span class="text-3xl text-blue-300">◷</span></div></section>
            </div>

            <section class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm">
                <div class="flex items-center justify-between gap-3">
                    <div><h2 class="text-xs font-extrabold uppercase text-[#10294b]">My Attendance This Month</h2><p class="mt-1 text-[11px] text-slate-500">Daily completion of your four attendance periods</p></div>
                    <a href="{{ route('instructor.summary') }}" class="shrink-0 rounded-lg bg-slate-50 px-3 py-1.5 text-[10px] font-semibold">{{ now()->format('F Y') }}⌄</a>
                </div>
                @if($chartHasData)
                    <div class="mt-4 overflow-x-auto rounded-xl border border-slate-100 bg-slate-50/40 px-2 pt-2">
                        <svg class="h-64 min-w-[680px] w-full" viewBox="0 0 {{ $chartWidth }} {{ $chartHeight }}" role="img" aria-labelledby="instructor-attendance-chart-title instructor-attendance-chart-description">
                            <title id="instructor-attendance-chart-title">Daily attendance completion</title>
                            <desc id="instructor-attendance-chart-description">Attendance completion percentage for each working day this month. One hundred percent means all four attendance periods were recorded.</desc>
                            @foreach([100, 75, 50, 25, 0] as $gridValue)
                                @php($gridY = $chartTop + (100 - $gridValue) * $chartPlotHeight / 100)
                                <line x1="{{ $chartLeft }}" y1="{{ $gridY }}" x2="{{ $chartWidth - $chartRight }}" y2="{{ $gridY }}" stroke="#e2e8f0" stroke-width="1" />
                                <text x="{{ $chartLeft - 10 }}" y="{{ $gridY + 4 }}" text-anchor="end" fill="#64748b" font-size="11">{{ $gridValue }}%</text>
                            @endforeach
                            @foreach($chartSegments as $segment)
                                <polyline points="{{ $segment }}" fill="none" stroke="#2563eb" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" />
                            @endforeach
                            @foreach($chartPoints as $point)
                                @if($point['value'] !== null)
                                    <circle cx="{{ $point['x'] }}" cy="{{ $point['y'] }}" r="4.5" fill="{{ $point['color'] }}" stroke="#ffffff" stroke-width="2">
                                        <title>{{ $point['day']['date']->format('M j') }}: {{ $point['value'] }}% ({{ $point['day']['status'] }})</title>
                                    </circle>
                                @endif
                                @if($point['showLabel'])
                                    <text x="{{ $point['x'] }}" y="{{ $chartHeight - 9 }}" text-anchor="middle" fill="#64748b" font-size="10">{{ $point['day']['date']->format('M j') }}</text>
                                @endif
                            @endforeach
                        </svg>
                    </div>
                @else
                    <div class="mt-4 rounded-xl border border-dashed border-slate-200 bg-slate-50 p-8 text-center text-xs text-slate-500">No rated attendance days yet this month.</div>
                @endif
                <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-2 text-[10px] text-slate-600">
                    <span class="inline-flex items-center gap-1.5"><i class="h-0.5 w-4 rounded-full bg-blue-600"></i>Daily completion</span>
                    @foreach(['#34d399'=>'Complete','#fbbf24'=>'Incomplete','#f43f5e'=>'Absent'] as $color=>$label)<span class="inline-flex items-center gap-1.5"><i class="h-2 w-2 rounded-full" style="background-color:{{ $color }}"></i>{{ $label }}</span>@endforeach
                </div>
            </section>
        </div>

        <aside class="space-y-4">
             <section class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm"><h2 class="text-sm font-extrabold uppercase text-[#10294b]">My Profile</h2><div class="mt-4 flex items-center gap-4"><div class="h-20 w-20 shrink-0 overflow-hidden rounded-full bg-blue-100 text-blue-800">@if(auth()->user()->avatar_thumbnail_url)<img loading="lazy" width="80" height="80" src="{{ auth()->user()->avatar_thumbnail_url }}" class="h-full w-full object-cover" alt="{{ $instructor->fullName() }}">@else<div class="grid h-full place-items-center text-xl font-bold">{{ strtoupper(substr($instructor->first_name,0,1).substr($instructor->last_name,0,1)) }}</div>@endif</div><div class="min-w-0 text-sm leading-6"><p class="truncate text-base font-extrabold text-slate-950">{{ $instructor->fullName() }}</p><p class="text-slate-600">Instructor ID: {{ $instructor->employee_no }}</p><p class="text-slate-600">Department: {{ $instructor->department?->department_code ?? $instructor->department?->department_name ?? 'N/A' }}</p><p class="text-slate-600">Position: Instructor</p></div></div><div class="mt-4 text-center"><p class="text-xs font-bold text-slate-800">My QR Code</p><div id="instructor-qr" class="mx-auto mt-2 w-fit rounded-lg border border-slate-200 bg-white p-2"></div><p class="mx-auto mt-2 max-w-[240px] text-[11px] leading-4 text-slate-500">Present this QR code to the dedicated scanner for attendance.</p><button type="button" @click="downloadIdCard" class="mx-auto mt-3 inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-xs font-bold text-white hover:bg-blue-700"><x-heroicon-o-arrow-down-tray class="h-4 w-4" />Download ID Card</button></div></section>
            <section class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm"><h2 class="text-xs font-extrabold uppercase text-[#10294b]">Next Teaching Schedule</h2>@if($nextSchedule)<div class="mt-3 border-t border-slate-100 pt-3"><p class="text-sm font-extrabold text-[#10294b]">◷ &nbsp;{{ Carbon\Carbon::parse($nextSchedule->start_time)->format('g:i A') }} - {{ Carbon\Carbon::parse($nextSchedule->end_time)->format('g:i A') }}</p><p class="mt-2 text-xs font-semibold">♙ &nbsp;{{ $nextSchedule->subject?->subject_name ?? 'Unnamed subject' }}</p><p class="mt-2 text-xs text-slate-600">♧ &nbsp;{{ $nextSchedule->section?->section_name ?? 'No section' }}</p><p class="mt-2 text-xs text-slate-600">⌖ &nbsp;Room {{ $nextSchedule->room ?? 'TBD' }}</p></div>@else<p class="mt-3 rounded-lg bg-slate-50 p-4 text-center text-xs text-slate-500">No upcoming teaching schedule today.</p>@endif</section>
            <section class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm"><div class="flex items-center justify-between"><h2 class="text-xs font-extrabold uppercase text-[#10294b]">Attendance Issues</h2><a href="{{ route('instructor.issues') }}" class="text-[11px] font-bold text-blue-600">View All</a></div><div class="mt-3 divide-y divide-slate-100">@forelse($issues as $issue)<div class="flex items-center gap-3 py-3"><span class="grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-orange-100 text-orange-600">△</span><div class="min-w-0 flex-1"><p class="truncate text-xs font-bold">{{ $issue['status']==='Absent'?'Absent Day':($issue['isIncomplete']?'Incomplete Attendance':($issue['late']?'Late Time-In':'Early Time-Out')) }}</p><p class="mt-1 text-[10px] text-slate-500">{{ $issue['date']->format('F j, Y') }} · {{ $issue['punctuality'] }}</p></div><span class="rounded-lg bg-orange-50 px-2.5 py-1 text-[9px] font-bold text-orange-600">Open</span></div>@empty<p class="rounded-lg bg-emerald-50 p-4 text-center text-xs text-emerald-700">No attendance issues found.</p>@endforelse</div></section>
        </aside>
    </div>

    <section class="rounded-2xl border border-slate-200/80 bg-white p-4 shadow-sm"><h2 class="text-xs font-extrabold uppercase text-[#10294b]">Quick Access</h2><div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-6">@foreach([['attendance','My Attendance'],['history','Attendance History'],['summary','Monthly Summary'],['schedule','My Teaching Schedule'],['issues','Attendance Issues'],['profile','Profile']] as [$route,$label])<a href="{{ $route==='profile'?route('my-profile.edit'):route('instructor.'.$route) }}" class="flex min-h-14 items-center justify-center gap-3 rounded-xl border border-slate-100 px-3 py-2 text-center text-[11px] font-semibold text-[#10294b] transition hover:border-blue-200 hover:bg-blue-50"><svg class="h-6 w-6 shrink-0 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="{{ $icons[$route] }}"/></svg>{{ $label }}</a>@endforeach</div></section>
</div>
</x-instructor-layout>
