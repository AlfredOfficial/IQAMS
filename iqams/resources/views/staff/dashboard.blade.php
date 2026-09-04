@php
    $periods = [
        'morning_in' => 'Morning In',
        'lunch_out' => 'Lunch Out',
        'afternoon_in' => 'Afternoon In',
        'final_out' => 'Final Out',
    ];
    $statusClasses = [
        'Present' => 'bg-emerald-100 text-emerald-700',
        'In Progress' => 'bg-amber-100 text-amber-700',
        'Incomplete' => 'bg-amber-100 text-amber-700',
        'Absent' => 'bg-red-100 text-red-700',
        'Not Started' => 'bg-slate-100 text-slate-600',
        'On Leave' => 'bg-sky-100 text-sky-700',
    ];
@endphp

<x-staff-layout title="Dashboard">
    <div x-data="staffWorkspace" data-realtime-url="{{ route('staff.dashboard.realtime') }}" data-id-card-url="{{ route('id-card.show') }}">
        <div class="mx-auto max-w-[1500px] space-y-6">
            @unless (Auth::user()->isAccountActive())
                <div class="rounded-lg border border-red-200 bg-red-50 px-5 py-4 text-sm font-medium text-red-800">
                    Attendance is unavailable because your account is inactive. Please contact the administrator.
                </div>
            @endunless

            <section aria-labelledby="today-attendance">
                <div class="mb-3">
                    <div><h2 id="today-attendance" class="text-lg font-semibold text-gray-900">Today's attendance</h2><p class="text-sm text-gray-500">Your four daily attendance periods</p></div>
                </div>
                <div class="rounded-xl border border-gray-200 bg-white px-4 py-4 sm:px-5">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div class="flex items-baseline gap-2"><p data-staff-progress-count class="text-sm font-bold text-gray-900">{{ $today['completedPeriods'] }} of 4 completed</p><p data-staff-progress-percent class="text-sm font-semibold text-emerald-700">{{ $today['progressPercentage'] }}%</p></div>
                        <span data-staff-status class="inline-flex rounded-full px-3 py-1 text-xs font-bold {{ $statusClasses[$today['summaryStatus'] ?? $today['status']] ?? 'bg-amber-100 text-amber-700' }}">{{ $today['summaryStatus'] ?? $today['status'] }}</span>
                    </div>
                    <div class="mt-3 h-2.5 w-full overflow-hidden rounded-full bg-gray-200" role="progressbar" aria-label="Today's attendance progress" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $today['progressPercentage'] }}" data-staff-progress-track>
                        <div data-staff-progress-bar class="h-full rounded-full bg-emerald-500 transition-[width] duration-300" style="width: {{ $today['progressPercentage'] }}%"></div>
                    </div>
                    <div class="mt-4 grid grid-cols-2 gap-x-4 gap-y-3 lg:grid-cols-4">
                    @foreach ($periods as $key => $label)
                        @php($log = $today['events'][$key])
                        <div data-staff-milestone="{{ $key }}" class="flex min-w-0 items-center gap-2 rounded-lg px-2 py-2 {{ $today['nextPeriod'] === $key ? 'bg-emerald-50' : '' }}">
                            <span data-staff-milestone-icon class="grid h-6 w-6 shrink-0 place-items-center rounded-full text-sm font-bold {{ $log ? 'bg-emerald-500 text-white' : 'bg-gray-200 text-gray-500' }}">{{ $log ? '✓' : '○' }}</span>
                            <span class="min-w-0 text-sm font-semibold {{ $log ? 'text-emerald-700' : 'text-gray-600' }}" data-staff-milestone-label>{{ $label }}</span>
                        </div>
                    @endforeach
                    </div>
                    <p class="mt-3 border-t border-gray-100 pt-3 text-sm font-medium text-slate-600">Next: <span data-staff-next class="font-bold text-slate-900">{{ $today['nextPeriod'] ? str($today['nextPeriod'])->replace('_', ' ')->title() : 'Complete' }}</span></p>
                </div>
            </section>

            <div class="grid items-start gap-6 xl:grid-cols-[minmax(0,1.6fr)_minmax(300px,.8fr)]">
                <div class="space-y-6">
                    <section class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                        <div class="border-b border-gray-100 px-5 py-4"><h2 class="font-semibold text-gray-900">Monthly summary</h2><p class="text-xs text-gray-500">{{ now()->format('F Y') }}</p></div>
                        <div class="grid grid-cols-2 divide-x divide-y divide-gray-100 sm:grid-cols-3 xl:grid-cols-4">
                            @foreach ([['percentage', 'Attendance rate', $totals['percentage'].'%'], ['presentDays', 'Present', $totals['presentDays']], ['absentDays', 'Absent', $totals['absentDays']], ['inProgressCount', 'In Progress', $totals['inProgressCount']], ['incompleteCount', 'Incomplete', $totals['incompleteCount']], ['lateCount', 'Late', $totals['lateCount']], ['earlyOutCount', 'Early Out', $totals['earlyOutCount']]] as [$key, $label, $value])
                                <div class="px-5 py-5"><p class="text-xs font-medium uppercase tracking-wide text-gray-500">{{ $label }}</p><p data-staff-stat="{{ $key }}" class="mt-2 text-2xl font-bold text-gray-900">{{ $value }}</p></div>
                            @endforeach
                        </div>
                    </section>

                    <section class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                        <div class="border-b border-gray-100 px-5 py-4"><h2 class="font-semibold text-gray-900">Recent attendance</h2><p class="text-xs text-gray-500">Your latest recorded scans</p></div>
                        <div data-staff-recent class="divide-y divide-gray-100">
                            @forelse ($recentLogs as $log)
                                <article class="flex items-center gap-4 px-5 py-4">
                                    <div class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-indigo-50 text-indigo-600">
                                        <x-heroicon-o-clock class="h-5 w-5" />
                                    </div>
                                    <div class="min-w-0 flex-1"><p class="text-sm font-semibold text-gray-800">{{ str($log->attendance_period ?? $log->attendance_type)->replace('_', ' ')->title() }}</p><p class="text-xs text-gray-500">{{ $log->scan_time->format('F j, Y') }}</p></div>
                                    <div class="text-right"><p class="whitespace-nowrap text-sm font-semibold tabular-nums text-gray-800">{{ $log->scan_time->format('g:i A') }}</p><p class="text-xs capitalize text-gray-500">{{ str_replace('_', ' ', $log->punctuality_status ?? $log->status) }}</p></div>
                                </article>
                            @empty
                                <div class="px-6 py-12 text-center text-sm text-gray-400">No attendance records yet.</div>
                            @endforelse
                        </div>
                    </section>
                </div>

                <aside class="space-y-6">
                    <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                        <h2 class="font-semibold text-gray-900">My profile</h2>
                        <div class="mt-4 flex items-center gap-4">
                            <div class="h-20 w-20 shrink-0 overflow-hidden rounded-full bg-indigo-100 text-indigo-700">
                                 @if (Auth::user()->avatar_thumbnail_url)<img loading="lazy" width="80" height="80" src="{{ Auth::user()->avatar_thumbnail_url }}" alt="{{ $staff->fullName() }}" class="h-full w-full object-cover">@else<div class="grid h-full place-items-center font-bold">{{ strtoupper(substr($staff->first_name, 0, 1).substr($staff->last_name, 0, 1)) }}</div>@endif
                            </div>
                            <div class="min-w-0 text-sm leading-6"><p class="break-words text-base font-extrabold text-gray-900">{{ $staff->fullName() }}</p><p class="text-gray-600">Staff ID: {{ $staff->employee_no }}</p><p class="break-words text-gray-600">Office/Unit: {{ $staff->officeUnit?->name ?? 'N/A' }}</p><p class="text-gray-600">Position: Non-Teaching Personnel</p></div>
                        </div>
                        <div class="mt-5 text-center">
                            <p class="text-xs font-bold text-slate-800">My QR Code</p>
                            <div id="staff-qr" class="mx-auto mt-2 grid min-h-44 min-w-44 w-fit place-items-center rounded-lg border border-slate-200 bg-white p-2 text-xs text-slate-500" aria-label="Staff attendance QR code"></div>
                            <p class="mx-auto mt-2 max-w-[240px] text-[11px] leading-4 text-slate-500">Present this QR code to the dedicated scanner for attendance.</p>
                        </div>
                        <div class="mt-4 grid gap-2 sm:grid-cols-2 xl:grid-cols-1">
                            {{-- <a href="{{ route('staff.profile.edit') }}" class="rounded-lg bg-indigo-600 px-4 py-2.5 text-center text-sm font-semibold text-white hover:bg-indigo-700">Edit profile</a> --}}
                            <button type="button" @click="window.ensureIqamsQrCode().then(() => window.downloadIqamsIdCard(@js(route('id-card.show')))).catch(error => window.alert(error.message))" class="inline-flex items-center justify-center gap-2 rounded-lg border border-indigo-200 bg-indigo-50 px-4 py-2.5 text-sm font-semibold text-indigo-700 hover:bg-indigo-100"><x-heroicon-o-arrow-down-tray class="h-5 w-5" />Download ID Card</button>
                        </div>
                    </section>

                    {{-- <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                        <h2 class="font-semibold text-gray-900">Quick access</h2>
                        <a href="{{ route('staff.leave-requests.index') }}" class="mt-4 flex items-center justify-between rounded-lg border border-gray-200 px-4 py-3 text-sm font-medium text-gray-700 hover:border-indigo-200 hover:bg-indigo-50 hover:text-indigo-700"><span>Leave requests</span><span aria-hidden="true">&rarr;</span></a>
                    </section> --}}
                </aside>
            </div>
        </div>
    </div>
</x-staff-layout>
