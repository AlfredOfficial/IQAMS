<x-student-layout title="Dashboard">
    <div x-data="studentWorkspace" data-realtime-url="{{ route('student.dashboard.realtime') }}"
        class="space-y-6 sm:space-y-7">
        @unless (Auth::user()->isAccountActive())
            <div class="border-l-4 border-red-500 bg-red-50 px-4 py-3 text-sm font-medium text-red-800">Attendance is
                unavailable because your account is inactive. Please contact the administrator.</div>
        @endunless
        <x-student-absence-warning :warnings="$subjectAbsenceWarnings" />
        <section x-data="attendanceOverview(@js($attendanceOverview))" class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm"
            aria-labelledby="attendance-overview-title">
            <div class="flex flex-wrap items-start justify-between gap-4 px-5 py-5 sm:px-7 sm:py-6">
                <div>
                    <h2 id="attendance-overview-title"
                        class="text-xl font-bold tracking-tight text-[#10294b] sm:text-2xl">Attendance Overview</h2>
                    <p class="mt-1 text-sm text-slate-500">Your attendance rate over time</p>
                </div><label
                    class="relative flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm font-semibold text-slate-600 shadow-sm"><svg
                        class="h-5 w-5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                        aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                            d="M7 3v3m10-3v3M4 9h16M5 5h14a1 1 0 011 1v13a1 1 0 01-1 1H5a1 1 0 01-1-1V6a1 1 0 011-1z" />
                    </svg><span class="sr-only">Attendance period</span><select id="attendance-overview-period"
                        x-model="period"
                        class="border-0 bg-transparent py-0 pl-0 pr-6 text-sm font-semibold text-slate-600 focus:border-0 focus:ring-0">
                        <option value="week">This Week</option>
                        <option value="month">This Month</option>
                        <option value="semester">This Semester</option>
                    </select></label>
            </div>
            <div class="relative px-3 pb-2 sm:px-7">
                <div x-show="active" x-cloak
                    class="pointer-events-none absolute right-8 top-2 z-10 rounded-lg bg-slate-900 px-2 py-1 text-xs font-semibold text-white"
                    x-text="active ? `${active.label}: ${format(active.percentage)}` : ''"></div>
                <div x-show="points.length" x-cloak class="h-[300px] w-full overflow-hidden"><svg class="block h-full w-full" viewBox="0 0 760 300"
                            preserveAspectRatio="none"
                            role="img" aria-labelledby="attendance-chart-title attendance-chart-description">
                            <title id="attendance-chart-title">Attendance rate over time</title>
                            <desc id="attendance-chart-description">Attendance percentage by period with a 75 percent
                                passing-rate target.</desc>
                            <defs>
                                <linearGradient id="studentAttendanceFill" x1="0" y1="0" x2="0"
                                    y2="1">
                                    <stop offset="0" stop-color="#10b981" stop-opacity=".22" />
                                    <stop offset="1" stop-color="#10b981" stop-opacity=".02" />
                                </linearGradient>
                            </defs><g data-chart-grid></g>
                            <line x1="58" :y1="y(target)" x2="730" :y2="y(target)"
                                stroke="#f59e0b" stroke-dasharray="6 4" /><text x="728" :y="y(target) - 10"
                                text-anchor="end" fill="#f59e0b" font-size="12"
                                x-text="`${target}% (passing rate)`"></text>
                            <polygon :points="areaPoints" fill="url(#studentAttendanceFill)"></polygon>
                            <polyline data-chart-line fill="none" stroke="#0f9f86" stroke-width="3"
                                stroke-linecap="round" stroke-linejoin="round"></polyline><g data-chart-points></g>
                        </svg></div>
                <p x-show="!points.length"
                    class="flex h-72 items-center justify-center text-center text-sm text-slate-500">No rated attendance
                    sessions are available for this period.</p>
            </div>
            <div
                class="mx-5 mb-5 flex flex-wrap items-center gap-5 rounded-xl bg-emerald-50/70 px-5 py-4 sm:mx-7 sm:mb-6 sm:px-7">
                <div class="grid h-11 w-11 shrink-0 place-items-center rounded-full bg-emerald-500 text-white"><svg
                        class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                            d="M7 3v3m10-3v3M4 9h16M5 5h14a1 1 0 011 1v13a1 1 0 01-1 1H5a1 1 0 01-1-1V6a1 1 0 011-1z" />
                    </svg></div>
                <div class="min-w-[130px]">
                    <p class="text-sm text-slate-500">Attendance rate</p>
                    <p data-student-stat="percentage" class="text-2xl font-bold text-emerald-700">
                        {{ number_format($summary['percentage'], 1) }}%</p>
                </div>
                <div class="hidden h-12 w-px bg-emerald-200 sm:block"></div>
                <div>
                    <p class="text-xl font-bold text-[#10294b]"><span
                            data-student-stat="attended-count">{{ $summary['attended'] }}</span> of
                        {{ $summary['scheduled'] }}</p>
                    <p class="text-sm text-slate-500">rated sessions attended</p>
                </div>
            </div>
        </section>
        <section class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm"
            aria-labelledby="recent-attendance-title">
            <div class="flex items-center justify-between gap-3 px-5 py-5 sm:px-7">
                <h2 id="recent-attendance-title" class="text-xl font-bold tracking-tight text-[#10294b]">Recent
                    Attendance</h2><a href="{{ route('student.attendance') }}"
                    class="shrink-0 text-sm font-semibold text-emerald-700 hover:text-emerald-900">View all <span
                        aria-hidden="true">›</span></a>
            </div>
            <div class="overflow-x-auto px-5 pb-5 sm:px-7 sm:pb-6">
                <table class="w-full min-w-[680px] border-collapse text-left" data-recent-attendance>
                    <thead>
                        <tr class="bg-slate-50 text-xs font-semibold text-slate-500">
                            <th class="px-4 py-3">Date</th>
                            <th class="px-4 py-3">Subject</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($myAttendance as $log)
                            <tr class="border-b border-slate-100 text-sm last:border-0">
                                <td class="whitespace-nowrap px-4 py-3.5 text-slate-600">
                                    {{ \Illuminate\Support\Carbon::parse($log->scan_time)->format('M j, Y (D)') }}</td>
                                <td class="px-4 py-3.5 font-medium text-[#10294b]">
                                    {{ $log->schoolEvent?->title ?? ($log->schedule?->subject?->subject_name ?? 'Attendance record') }}
                                </td>
                                <td class="px-4 py-3.5"><x-student-status :status="$log->status" /></td>
                                <td class="whitespace-nowrap px-4 py-3.5 text-slate-600">
                                    {{ $log->status === 'absent' ? '—' : \Illuminate\Support\Carbon::parse($log->scan_time)->format('g:i A') }}
                                </td>
                        </tr>@empty<tr>
                                <td colspan="4" class="px-4 py-12 text-center text-sm text-slate-500">No attendance
                                    records yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-student-layout>
