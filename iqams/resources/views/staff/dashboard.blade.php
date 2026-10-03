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
    <div x-data="staffWorkspace" data-realtime-url="{{ route('staff.dashboard.realtime') }}">
        <div class="mx-auto max-w-[1500px] space-y-6">
            @unless (Auth::user()->isAccountActive())
                <div class="rounded-lg border border-red-200 bg-red-50 px-5 py-4 text-sm font-medium text-red-800">
                    Attendance is unavailable because your account is inactive. Please contact the administrator.
                </div>
            @endunless

            <section aria-labelledby="today-attendance">
                <div class="rounded-xl border border-gray-200 bg-white px-4 py-4 sm:px-5">
                    <div class="mb-5 border-b border-gray-100 pb-4">
                        <h2 id="today-attendance" class="text-lg font-semibold text-gray-900">Today's attendance</h2>
                        <p class="mt-1 text-sm text-gray-500">Your four daily attendance periods</p>
                    </div>
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div class="flex items-baseline gap-2">
                            <p data-staff-progress-count class="text-sm font-bold text-gray-900">
                                {{ $today['completedPeriods'] }} of 4 completed</p>
                            <p data-staff-progress-percent class="text-sm font-semibold text-emerald-700">
                                {{ $today['progressPercentage'] }}%</p>
                        </div>
                        <span data-staff-status
                            class="inline-flex rounded-full px-3 py-1 text-xs font-bold {{ $statusClasses[$today['summaryStatus'] ?? $today['status']] ?? 'bg-amber-100 text-amber-700' }}">{{ $today['summaryStatus'] ?? $today['status'] }}</span>
                    </div>
                    <div class="mt-3 h-2.5 w-full overflow-hidden rounded-full bg-gray-200" role="progressbar"
                        aria-label="Today's attendance progress" aria-valuemin="0" aria-valuemax="100"
                        aria-valuenow="{{ $today['progressPercentage'] }}" data-staff-progress-track>
                        <div data-staff-progress-bar
                            class="h-full rounded-full bg-emerald-500 transition-[width] duration-300"
                            style="width: {{ $today['progressPercentage'] }}%"></div>
                    </div>
                    <div class="mt-4 grid grid-cols-2 gap-x-4 gap-y-3 lg:grid-cols-4">
                        @foreach ($periods as $key => $label)
                            @php($log = $today['events'][$key])
                            <div data-staff-milestone="{{ $key }}"
                                class="flex min-w-0 items-center gap-2 rounded-lg px-2 py-2 {{ $today['nextPeriod'] === $key ? 'bg-emerald-50' : '' }}">
                                <span data-staff-milestone-icon
                                    class="grid h-6 w-6 shrink-0 place-items-center rounded-full text-sm font-bold {{ $log ? 'bg-emerald-500 text-white' : 'bg-gray-200 text-gray-500' }}">{{ $log ? '✓' : '○' }}</span>
                                <span
                                    class="min-w-0 text-sm font-semibold {{ $log ? 'text-emerald-700' : 'text-gray-600' }}"
                                    data-staff-milestone-label>{{ $label }}</span>
                            </div>
                        @endforeach
                    </div>
                    <p class="mt-3 border-t border-gray-100 pt-3 text-sm font-medium text-slate-600">Next: <span
                            data-staff-next
                            class="font-bold text-slate-900">{{ $today['nextPeriod'] ? str($today['nextPeriod'])->replace('_', ' ')->title() : 'Complete' }}</span>
                    </p>
                </div>
            </section>

            <section class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                <div class="border-b border-gray-100 px-5 py-4">
                    <h2 class="flex items-center gap-2 font-semibold text-gray-900">
                        <span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-indigo-50 text-indigo-600">
                            <x-heroicon-o-clock class="h-5 w-5" />
                        </span>
                        Recent attendance
                    </h2>
                    <p class="ml-11 text-xs text-gray-500">Your latest recorded scans</p>
                </div>
                <div data-staff-recent class="divide-y divide-gray-100">
                    @forelse ($recentLogs as $log)
                        <article class="flex items-center gap-4 px-5 py-4">
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-semibold text-gray-800">{{ str($log->attendance_period ?? $log->attendance_type)->replace('_', ' ')->title() }}</p>
                                <p class="text-xs text-gray-500">{{ $log->scan_time->format('F j, Y') }}</p>
                            </div>
                            <div class="text-right">
                                <p class="whitespace-nowrap text-sm font-semibold tabular-nums text-gray-800">{{ $log->scan_time->format('g:i A') }}</p>
                                <p class="text-xs capitalize text-gray-500">{{ str_replace('_', ' ', $log->punctuality_status ?? $log->status) }}</p>
                            </div>
                        </article>
                    @empty
                        <div class="px-6 py-12 text-center text-sm text-gray-400">No attendance records yet.</div>
                    @endforelse
                </div>
            </section>
            </div>
        </div>
    </div>
</x-staff-layout>
