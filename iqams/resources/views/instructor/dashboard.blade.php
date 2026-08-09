<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Instructor Dashboard') }}</h2>
                <p class="text-sm text-gray-500">Welcome back, {{ $instructor->first_name }} {{ $instructor->last_name }}</p>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 xl:grid-cols-3 gap-6 mb-6">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <p class="text-sm font-medium text-gray-500 uppercase tracking-wide">Subjects</p>
                    <p class="mt-4 text-3xl font-semibold text-gray-900">{{ $stats['totalSubjects'] }}</p>
                </div>
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <p class="text-sm font-medium text-gray-500 uppercase tracking-wide">Present Today</p>
                    <p class="mt-4 text-3xl font-semibold text-gray-900">{{ $stats['todayPresent'] }}</p>
                </div>
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <p class="text-sm font-medium text-gray-500 uppercase tracking-wide">Late Today</p>
                    <p class="mt-4 text-3xl font-semibold text-gray-900">{{ $stats['todayLate'] }}</p>
                </div>
            </div>

            <div class="grid gap-6 xl:grid-cols-2">
                <section class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <h3 class="text-lg font-semibold text-gray-900">My Weekly Schedule</h3>

                    @if ($schedules->isEmpty())
                        <p class="mt-6 text-sm text-gray-500">No schedule assigned yet.</p>
                    @else
                        <div class="mt-6 space-y-6">
                            @foreach ($dayOrder as $day)
                                @if ($scheduleByDay->has($day))
                                    <div>
                                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ ucfirst($day) }}</p>
                                        <div class="mt-3 space-y-3">
                                            @foreach ($scheduleByDay[$day] as $item)
                                                <div class="rounded-2xl border border-gray-200 p-4">
                                                    <div class="flex items-start justify-between gap-4">
                                                        <div>
                                                            <p class="font-semibold text-gray-900">{{ $item->subject->subject_name ?? '–' }}</p>
                                                            <p class="text-sm text-gray-500">Room {{ $item->room ?? 'TBD' }}</p>
                                                        </div>
                                                        <p class="text-sm text-gray-500">{{ \Illuminate\Support\Carbon::parse($item->start_time)->format('g:i A') }}</p>
                                                    </div>
                                                    <p class="mt-3 text-sm text-gray-500">Section: {{ $item->section->section_name ?? '–' }}</p>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    @endif
                </section>

                <section class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <h3 class="text-lg font-semibold text-gray-900">Today’s Attendance</h3>

                    @if ($todayAttendance->isEmpty())
                        <p class="mt-6 text-sm text-gray-500">No attendance records for today.</p>
                    @else
                        <div class="mt-6 space-y-4">
                            @foreach ($todayAttendance as $log)
                                <div class="rounded-2xl border border-gray-200 p-4">
                                    <div class="flex items-center justify-between gap-4">
                                        <div>
                                            <p class="font-medium text-gray-900">{{ $log->schedule->subject->subject_name ?? '–' }}</p>
                                            <p class="text-sm text-gray-500">{{ $log->user->name ?? 'Student' }}</p>
                                        </div>
                                        <span class="text-xs uppercase tracking-wide text-gray-500">{{ ucfirst($log->status) }}</span>
                                    </div>
                                    <p class="mt-3 text-sm text-gray-500">{{ \Illuminate\Support\Carbon::parse($log->scan_time)->format('M d, g:i A') }}</p>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </section>
            </div>
        </div>
    </div>
</x-app-layout>
