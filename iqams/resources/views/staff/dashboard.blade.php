@php
    $periods = [
        'morning_in' => ['Morning time-in', 'Start of the workday'],
        'lunch_out' => ['Lunch time-out', 'End of the morning session'],
        'afternoon_in' => ['Afternoon time-in', 'Start of the afternoon session'],
        'final_out' => ['Final time-out', 'End of the workday'],
    ];
    $statusClasses = [
        'Completed' => 'bg-emerald-100 text-emerald-700',
        'Absent' => 'bg-red-100 text-red-700',
        'Not Started' => 'bg-slate-100 text-slate-600',
        'On Leave' => 'bg-sky-100 text-sky-700',
        'Sick Leave' => 'bg-sky-100 text-sky-700',
    ];
@endphp

<x-staff-layout title="Dashboard">
    <div x-data="{ qrModal: { show: false, value: '', label: '' } }" @keydown.escape.window="qrModal.show = false">
        <div class="mx-auto max-w-[1500px] space-y-6">
            @unless (Auth::user()->isAccountActive())
                <div class="rounded-lg border border-red-200 bg-red-50 px-5 py-4 text-sm font-medium text-red-800">
                    Attendance is unavailable because your account is inactive. Please contact the administrator.
                </div>
            @endunless

            <section aria-labelledby="today-attendance">
                <div class="mb-3 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div><h2 id="today-attendance" class="text-lg font-semibold text-gray-900">Today's attendance</h2><p class="text-sm text-gray-500">Your four daily attendance periods</p></div>
                    <div class="flex items-center gap-3">
                        <span class="inline-flex rounded-full px-3 py-1 text-xs font-bold {{ $statusClasses[$today['status']] ?? 'bg-amber-100 text-amber-700' }}">{{ $today['status'] }}</span>
                        <p class="text-sm text-slate-500">Next: {{ $today['nextPeriod'] ? str($today['nextPeriod'])->replace('_', ' ')->title() : 'Complete' }}</p>
                    </div>
                </div>
                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    @foreach ($periods as $key => [$label, $description])
                        @php($log = $today['events'][$key])
                        <article class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                            <div class="flex items-start justify-between gap-3">
                                <div><h3 class="text-sm font-semibold text-gray-800">{{ $label }}</h3><p class="mt-1 text-xs text-gray-400">{{ $description }}</p></div>
                                <span class="h-2.5 w-2.5 shrink-0 rounded-full {{ $log ? 'bg-emerald-500' : 'bg-gray-300' }}"></span>
                            </div>
                            <p class="mt-5 text-2xl font-bold tabular-nums text-gray-900">{{ $log?->scan_time?->format('g:i A') ?? 'Not recorded' }}</p>
                            <p class="mt-1 text-xs font-medium {{ in_array($log?->punctuality_status, ['late', 'early_out']) ? 'text-amber-600' : ($log ? 'text-emerald-600' : 'text-gray-400') }}">
                                {{ $log ? str($log->punctuality_status ?? 'on_time')->replace('_', ' ')->title() : 'Pending' }}
                            </p>
                        </article>
                    @endforeach
                </div>
            </section>

            <div class="grid items-start gap-6 xl:grid-cols-[minmax(0,1.6fr)_minmax(300px,.8fr)]">
                <div class="space-y-6">
                    <section class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                        <div class="border-b border-gray-100 px-5 py-4"><h2 class="font-semibold text-gray-900">Monthly summary</h2><p class="text-xs text-gray-500">{{ now()->format('F Y') }}</p></div>
                        <div class="grid grid-cols-2 divide-x divide-y divide-gray-100 sm:grid-cols-4 sm:divide-y-0">
                            @foreach ([['Attendance rate', $totals['percentage'].'%'], ['Present', $totals['presentDays']], ['Late', $totals['lateCount']], ['Incomplete', $totals['incompleteCount']]] as [$label, $value])
                                <div class="px-5 py-5"><p class="text-xs font-medium uppercase tracking-wide text-gray-500">{{ $label }}</p><p class="mt-2 text-2xl font-bold text-gray-900">{{ $value }}</p></div>
                            @endforeach
                        </div>
                    </section>

                    <section class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                        <div class="border-b border-gray-100 px-5 py-4"><h2 class="font-semibold text-gray-900">Recent attendance</h2><p class="text-xs text-gray-500">Your latest recorded scans</p></div>
                        <div class="divide-y divide-gray-100">
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
                            <div class="h-16 w-16 shrink-0 overflow-hidden rounded-full bg-indigo-100 text-indigo-700">
                                @if (Auth::user()->avatar_url)<img src="{{ Auth::user()->avatar_url }}" alt="{{ $staff->fullName() }}" class="h-full w-full object-cover">@else<div class="grid h-full place-items-center font-bold">{{ strtoupper(substr($staff->first_name, 0, 1).substr($staff->last_name, 0, 1)) }}</div>@endif
                            </div>
                            <div class="min-w-0"><p class="break-words font-semibold text-gray-900">{{ $staff->fullName() }}</p><p class="text-sm text-gray-500">{{ $staff->employee_no }}</p><p class="mt-1 break-words text-xs text-gray-400">{{ $staff->department?->department_name ?? 'Department not assigned' }}</p></div>
                        </div>
                        <div class="mt-5 grid gap-2 sm:grid-cols-2 xl:grid-cols-1">
                            <a href="{{ route('staff.profile.edit') }}" class="rounded-lg bg-indigo-600 px-4 py-2.5 text-center text-sm font-semibold text-white hover:bg-indigo-700">Edit profile</a>
                            <button type="button" @click="qrModal = {{ Illuminate\Support\Js::from(['show' => true, 'value' => $staff->qr_code ?? $staff->employee_no, 'label' => $staff->fullName()]) }}" class="rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50">View my QR code</button>
                            <button type="button" @click="window.downloadIqamsIdCard(@js(route('id-card.show'))).catch(error => window.alert(error.message))" class="inline-flex items-center justify-center gap-2 rounded-lg border border-indigo-200 bg-indigo-50 px-4 py-2.5 text-sm font-semibold text-indigo-700 hover:bg-indigo-100"><x-heroicon-o-arrow-down-tray class="h-5 w-5" />Download ID Card</button>
                        </div>
                    </section>

                    <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                        <h2 class="font-semibold text-gray-900">Quick access</h2>
                        <a href="{{ route('staff.leave-requests.index') }}" class="mt-4 flex items-center justify-between rounded-lg border border-gray-200 px-4 py-3 text-sm font-medium text-gray-700 hover:border-indigo-200 hover:bg-indigo-50 hover:text-indigo-700"><span>Leave requests</span><span aria-hidden="true">&rarr;</span></a>
                    </section>
                </aside>
            </div>
        </div>

        <x-qr-modal />
    </div>
</x-staff-layout>
