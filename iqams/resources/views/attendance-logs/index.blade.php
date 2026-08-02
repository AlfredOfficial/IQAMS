<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Attendance Logs
        </h2>
    </x-slot>

    <div class="py-8" x-data="{
            showCreateModal: {{ $errors->any() ? 'true' : 'false' }},
            editModal: { show: false, id: null, user_id: '', schedule_id: '', attendance_type: '', scan_time: '', status_override: '', scanner_location: '', remarks: '' },
            deleteModal: { show: false, id: null, name: '' }
        }">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            @if (session('success'))
                <div class="mb-4 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded">
                    {{ session('success') }}
                </div>
            @endif

            {{-- Filters --}}
            <form method="GET" action="{{ route('attendance-logs.index') }}"
                  class="mb-4 bg-white shadow-sm rounded-lg p-4 flex flex-wrap items-end gap-3">
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">Date</label>
                    <input type="date" name="date" value="{{ request('date') }}"
                           class="rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">Status</label>
                    <select name="status" class="rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">All</option>
                        @foreach (['present', 'late', 'absent', 'excused'] as $status)
                            <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">Person</label>
                    <select name="user_id" class="rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">All</option>
                        @foreach ($people as $person)
                            <option value="{{ $person['user_id'] }}" @selected((string) request('user_id') === (string) $person['user_id'])>
                                {{ $person['label'] }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="flex items-center gap-2">
                    <button type="submit" class="bg-gray-800 hover:bg-gray-900 text-white text-sm font-medium px-4 py-2 rounded">
                        Filter
                    </button>
                    <a href="{{ route('attendance-logs.index') }}" class="text-sm text-gray-500 hover:text-gray-700">
                        Clear
                    </a>
                </div>
            </form>

            <div class="bg-white shadow-sm rounded-lg">
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
                    <span class="text-sm text-gray-500">{{ $logs->total() }} total</span>
                    <button @click="showCreateModal = true"
                       class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2 rounded">
                        + Add Log
                    </button>
                </div>

                <table class="w-full text-sm text-left">
                    <thead class="bg-gray-50 text-gray-500 uppercase text-xs">
                        <tr>
                            <th class="px-6 py-3">Person</th>
                            <th class="px-6 py-3">Schedule</th>
                            <th class="px-6 py-3">Type</th>
                            <th class="px-6 py-3">Scan Time</th>
                            <th class="px-6 py-3">Status</th>
                            <th class="px-6 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($logs as $log)
                            <tr>
                                <td class="px-6 py-3 text-gray-800 font-medium">{{ $log->user->name ?? '—' }}</td>
                                <td class="px-6 py-3 text-gray-600">
                                    {{ $log->schedule->subject->subject_code ?? '—' }}
                                    ({{ $log->schedule->section->section_name ?? '—' }})
                                </td>
                                <td class="px-6 py-3 text-gray-600">{{ $log->attendance_type === 'time_in' ? 'Time In' : 'Time Out' }}</td>
                                <td class="px-6 py-3 text-gray-600">{{ \Illuminate\Support\Carbon::parse($log->scan_time)->format('M d, Y g:i A') }}</td>
                                <td class="px-6 py-3">
                                    <span @class([
                                        'px-2 py-1 rounded text-xs font-medium',
                                        'bg-green-50 text-green-700' => $log->status === 'present',
                                        'bg-yellow-50 text-yellow-700' => $log->status === 'late',
                                        'bg-red-50 text-red-700' => $log->status === 'absent',
                                        'bg-blue-50 text-blue-700' => $log->status === 'excused',
                                    ])>
                                        {{ ucfirst($log->status) }}
                                    </span>
                                </td>
                                <td class="px-6 py-3 text-right space-x-3">
                                    <button type="button"
                                        @click="editModal = {
                                            show: true,
                                            id: {{ $log->id }},
                                            user_id: '{{ $log->user_id }}',
                                            schedule_id: '{{ $log->schedule_id }}',
                                            attendance_type: '{{ $log->attendance_type }}',
                                            scan_time: '{{ \Illuminate\Support\Carbon::parse($log->scan_time)->format('Y-m-d\TH:i') }}',
                                            status_override: '{{ $log->status }}',
                                            scanner_location: '{{ addslashes($log->scanner_location) }}',
                                            remarks: '{{ addslashes($log->remarks) }}'
                                        }"
                                        class="text-indigo-600 hover:text-indigo-800">Edit</button>

                                    <button type="button"
                                        @click="deleteModal = { show: true, id: {{ $log->id }}, name: '{{ addslashes(($log->user->name ?? 'Log') . ' - ' . \Illuminate\Support\Carbon::parse($log->scan_time)->format('M d, g:i A')) }}' }"
                                        class="text-red-600 hover:text-red-800">Delete</button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-8 text-center text-gray-400">
                                    No attendance logs found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>

                <div class="px-6 py-4 border-t border-gray-200">
                    {{ $logs->links() }}
                </div>
            </div>
        </div>

        {{-- Create Log Modal --}}
        <div x-show="showCreateModal" x-cloak
             class="fixed inset-0 z-50 flex items-center justify-center px-4"
             style="background: rgba(0,0,0,0.4);">
            <div @click.outside="showCreateModal = false"
                 class="bg-white rounded-lg shadow-xl w-full max-w-md p-6 max-h-[90vh] overflow-y-auto scrollbar-autohide"
                 x-data="{ scrollTimer: null }"
                 @scroll="$el.classList.add('is-scrolling'); clearTimeout(scrollTimer); scrollTimer = setTimeout(() => $el.classList.remove('is-scrolling'), 800)">

                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-800">Add Attendance Log</h3>
                    <button @click="showCreateModal = false" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <form method="POST" action="{{ route('attendance-logs.store') }}">
                    @csrf

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Person</label>
                        <select name="user_id" class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">-- Select Person --</option>
                            @foreach ($people as $person)
                                <option value="{{ $person['user_id'] }}" @selected(old('user_id') == $person['user_id'])>
                                    {{ $person['label'] }}
                                </option>
                            @endforeach
                        </select>
                        @error('user_id')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Schedule</label>
                        <select name="schedule_id" class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">-- Select Schedule --</option>
                            @foreach ($schedules as $schedule)
                                <option value="{{ $schedule->id }}" @selected(old('schedule_id') == $schedule->id)>
                                    {{ $schedule->subject->subject_code ?? '—' }} - {{ ucfirst($schedule->day) }} ({{ $schedule->section->section_name ?? '—' }})
                                </option>
                            @endforeach
                        </select>
                        @error('schedule_id')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Type</label>
                        <select name="attendance_type" class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">-- Select Type --</option>
                            <option value="time_in" @selected(old('attendance_type') === 'time_in')>Time In</option>
                            <option value="time_out" @selected(old('attendance_type') === 'time_out')>Time Out</option>
                        </select>
                        @error('attendance_type')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Scan Time</label>
                        <input type="datetime-local" name="scan_time" value="{{ old('scan_time') }}"
                               class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @error('scan_time')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Status Override <span class="text-gray-400 font-normal">(optional)</span></label>
                        <select name="status_override" class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">Auto-detect (present/late)</option>
                            <option value="present" @selected(old('status_override') === 'present')>Present</option>
                            <option value="late" @selected(old('status_override') === 'late')>Late</option>
                            <option value="absent" @selected(old('status_override') === 'absent')>Absent</option>
                            <option value="excused" @selected(old('status_override') === 'excused')>Excused</option>
                        </select>
                        <p class="mt-1 text-xs text-gray-400">Leave as Auto-detect unless you're making a manual correction.</p>
                        @error('status_override')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Scanner Location <span class="text-gray-400 font-normal">(optional)</span></label>
                        <input type="text" name="scanner_location" value="{{ old('scanner_location') }}"
                               class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                               placeholder="e.g. Main Gate">
                        @error('scanner_location')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Remarks <span class="text-gray-400 font-normal">(optional)</span></label>
                        <input type="text" name="remarks" value="{{ old('remarks') }}"
                               class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                               placeholder="e.g. Manual correction, doctor's note on file">
                        @error('remarks')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex items-center gap-3">
                        <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2 rounded">
                            Save Log
                        </button>
                        <button type="button" @click="showCreateModal = false" class="text-sm text-gray-500 hover:text-gray-700">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Edit Log Modal --}}
        <div x-show="editModal.show" x-cloak
             class="fixed inset-0 z-50 flex items-center justify-center px-4"
             style="background: rgba(0,0,0,0.4);">
            <div @click.outside="editModal.show = false"
                 class="bg-white rounded-lg shadow-xl w-full max-w-md p-6 max-h-[90vh] overflow-y-auto scrollbar-autohide"
                 x-data="{ scrollTimer: null }"
                 @scroll="$el.classList.add('is-scrolling'); clearTimeout(scrollTimer); scrollTimer = setTimeout(() => $el.classList.remove('is-scrolling'), 800)">

                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-800">Edit Attendance Log</h3>
                    <button type="button" @click="editModal.show = false" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <form method="POST" :action="'{{ url('attendance-logs') }}/' + editModal.id">
                    @csrf
                    @method('PUT')

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Person</label>
                        <select name="user_id" x-model="editModal.user_id"
                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">-- Select Person --</option>
                            @foreach ($people as $person)
                                <option value="{{ $person['user_id'] }}">{{ $person['label'] }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Schedule</label>
                        <select name="schedule_id" x-model="editModal.schedule_id"
                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">-- Select Schedule --</option>
                            @foreach ($schedules as $schedule)
                                <option value="{{ $schedule->id }}">
                                    {{ $schedule->subject->subject_code ?? '—' }} - {{ ucfirst($schedule->day) }} ({{ $schedule->section->section_name ?? '—' }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Type</label>
                        <select name="attendance_type" x-model="editModal.attendance_type"
                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="time_in">Time In</option>
                            <option value="time_out">Time Out</option>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Scan Time</label>
                        <input type="datetime-local" name="scan_time" x-model="editModal.scan_time"
                               class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Status Override</label>
                        <select name="status_override" x-model="editModal.status_override"
                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="present">Present</option>
                            <option value="late">Late</option>
                            <option value="absent">Absent</option>
                            <option value="excused">Excused</option>
                        </select>
                        <p class="mt-1 text-xs text-gray-400">Editing always uses this value directly (no auto-detect on edit).</p>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Scanner Location</label>
                        <input type="text" name="scanner_location" x-model="editModal.scanner_location"
                               class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>

                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Remarks</label>
                        <input type="text" name="remarks" x-model="editModal.remarks"
                               class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>

                    <div class="flex items-center gap-3">
                        <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2 rounded">
                            Update Log
                        </button>
                        <button type="button" @click="editModal.show = false" class="text-sm text-gray-500 hover:text-gray-700">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Delete Confirmation Modal --}}
        <div x-show="deleteModal.show" x-cloak
             class="fixed inset-0 z-50 flex items-center justify-center px-4"
             style="background: rgba(0,0,0,0.4);">
            <div @click.outside="deleteModal.show = false"
                 class="bg-white rounded-lg shadow-xl w-full max-w-sm p-6">

                <h3 class="text-lg font-semibold text-gray-800 mb-2">Delete Log</h3>
                <p class="text-sm text-gray-500 mb-6">
                    Are you sure you want to delete <span class="font-medium text-gray-700" x-text="deleteModal.name"></span>?
                    This can't be undone.
                </p>

                <form method="POST" :action="'{{ url('attendance-logs') }}/' + deleteModal.id">
                    @csrf
                    @method('DELETE')

                    <div class="flex items-center gap-3">
                        <button type="submit" class="bg-red-600 hover:bg-red-700 text-white text-sm font-medium px-4 py-2 rounded">
                            Delete
                        </button>
                        <button type="button" @click="deleteModal.show = false" class="text-sm text-gray-500 hover:text-gray-700">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>