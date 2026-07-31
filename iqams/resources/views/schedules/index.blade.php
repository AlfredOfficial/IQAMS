<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Schedules
        </h2>
    </x-slot>

    <div class="py-8" x-data="{
            showCreateModal: {{ $errors->any() ? 'true' : 'false' }},
            editModal: { show: false, id: null, subject_id: '', instructor_id: '', section_id: '', day: '', start_time: '', end_time: '', room: '' },
            deleteModal: { show: false, id: null, name: '' }
        }">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            @if (session('success'))
                <div class="mb-4 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded">
                    {{ session('success') }}
                </div>
            @endif

            <div class="bg-white shadow-sm rounded-lg">
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
                    <span class="text-sm text-gray-500">{{ $schedules->total() }} total</span>
                    <button @click="showCreateModal = true"
                       class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2 rounded">
                        + Add Schedule
                    </button>
                </div>

                <table class="w-full text-sm text-left">
                    <thead class="bg-gray-50 text-gray-500 uppercase text-xs">
                        <tr>
                            <th class="px-6 py-3">Subject</th>
                            <th class="px-6 py-3">Instructor</th>
                            <th class="px-6 py-3">Section</th>
                            <th class="px-6 py-3">Day</th>
                            <th class="px-6 py-3">Time</th>
                            <th class="px-6 py-3">Room</th>
                            <th class="px-6 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($schedules as $schedule)
                            <tr>
                                <td class="px-6 py-3 text-gray-800 font-medium">{{ $schedule->subject->subject_code ?? '—' }}</td>
                                <td class="px-6 py-3 text-gray-600">{{ $schedule->instructor->first_name ?? '' }} {{ $schedule->instructor->last_name ?? '—' }}</td>
                                <td class="px-6 py-3 text-gray-600">{{ $schedule->section->section_name ?? '—' }}</td>
                                <td class="px-6 py-3 text-gray-600">{{ ucfirst($schedule->day) }}</td>
                                <td class="px-6 py-3 text-gray-600">
                                    {{ \Illuminate\Support\Carbon::parse($schedule->start_time)->format('g:i A') }}
                                    -
                                    {{ \Illuminate\Support\Carbon::parse($schedule->end_time)->format('g:i A') }}
                                </td>
                                <td class="px-6 py-3 text-gray-600">{{ $schedule->room }}</td>
                                <td class="px-6 py-3 text-right space-x-3">
                                    <button type="button"
                                        @click="editModal = {
                                            show: true,
                                            id: {{ $schedule->id }},
                                            subject_id: '{{ $schedule->subject_id }}',
                                            instructor_id: '{{ $schedule->instructor_id }}',
                                            section_id: '{{ $schedule->section_id }}',
                                            day: '{{ $schedule->day }}',
                                            start_time: '{{ \Illuminate\Support\Carbon::parse($schedule->start_time)->format('H:i') }}',
                                            end_time: '{{ \Illuminate\Support\Carbon::parse($schedule->end_time)->format('H:i') }}',
                                            room: '{{ addslashes($schedule->room) }}'
                                        }"
                                        class="text-indigo-600 hover:text-indigo-800">Edit</button>

                                    <button type="button"
                                        @click="deleteModal = { show: true, id: {{ $schedule->id }}, name: '{{ addslashes(($schedule->subject->subject_code ?? 'Schedule') . ' - ' . ucfirst($schedule->day)) }}' }"
                                        class="text-red-600 hover:text-red-800">Delete</button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-6 py-8 text-center text-gray-400">
                                    No schedules yet. Add your first one.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>

                <div class="px-6 py-4 border-t border-gray-200">
                    {{ $schedules->links() }}
                </div>
            </div>
        </div>

        {{-- Create Schedule Modal --}}
        <div x-show="showCreateModal" x-cloak
             class="fixed inset-0 z-50 flex items-center justify-center px-4"
             style="background: rgba(0,0,0,0.4);">
            <div @click.outside="showCreateModal = false"
                 class="bg-white rounded-lg shadow-xl w-full max-w-md p-6 max-h-[90vh] overflow-y-auto">

                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-800">Add Schedule</h3>
                    <button @click="showCreateModal = false" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <form method="POST" action="{{ route('schedules.store') }}">
                    @csrf

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Subject</label>
                        <select name="subject_id" class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">-- Select Subject --</option>
                            @foreach ($subjects as $subject)
                                <option value="{{ $subject->id }}" @selected(old('subject_id') == $subject->id)>
                                    {{ $subject->subject_code }} - {{ $subject->subject_name }}
                                </option>
                            @endforeach
                        </select>
                        @error('subject_id')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Instructor</label>
                        <select name="instructor_id" class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">-- Select Instructor --</option>
                            @foreach ($instructors as $instructor)
                                <option value="{{ $instructor->id }}" @selected(old('instructor_id') == $instructor->id)>
                                    {{ $instructor->first_name }} {{ $instructor->last_name }}
                                </option>
                            @endforeach
                        </select>
                        @error('instructor_id')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Section</label>
                        <select name="section_id" class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">-- Select Section --</option>
                            @foreach ($sections as $section)
                                <option value="{{ $section->id }}" @selected(old('section_id') == $section->id)>
                                    {{ $section->section_name }} ({{ $section->course->course_code ?? '—' }})
                                </option>
                            @endforeach
                        </select>
                        @error('section_id')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Day</label>
                        <select name="day" class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">-- Select Day --</option>
                            @foreach (['monday','tuesday','wednesday','thursday','friday','saturday','sunday'] as $day)
                                <option value="{{ $day }}" @selected(old('day') == $day)>{{ ucfirst($day) }}</option>
                            @endforeach
                        </select>
                        @error('day')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="mb-4 grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Start Time</label>
                            <input type="time" name="start_time" value="{{ old('start_time') }}"
                                   class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @error('start_time')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">End Time</label>
                            <input type="time" name="end_time" value="{{ old('end_time') }}"
                                   class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @error('end_time')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Room</label>
                        <input type="text" name="room" value="{{ old('room') }}"
                               class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                               placeholder="e.g. Room 301">
                        @error('room')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex items-center gap-3">
                        <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2 rounded">
                            Save Schedule
                        </button>
                        <button type="button" @click="showCreateModal = false" class="text-sm text-gray-500 hover:text-gray-700">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Edit Schedule Modal --}}
        <div x-show="editModal.show" x-cloak
             class="fixed inset-0 z-50 flex items-center justify-center px-4"
             style="background: rgba(0,0,0,0.4);">
            <div @click.outside="editModal.show = false"
                 class="bg-white rounded-lg shadow-xl w-full max-w-md p-6 max-h-[90vh] overflow-y-auto">

                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-800">Edit Schedule</h3>
                    <button type="button" @click="editModal.show = false" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <form method="POST" :action="'{{ url('schedules') }}/' + editModal.id">
                    @csrf
                    @method('PUT')

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Subject</label>
                        <select name="subject_id" x-model="editModal.subject_id"
                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">-- Select Subject --</option>
                            @foreach ($subjects as $subject)
                                <option value="{{ $subject->id }}">{{ $subject->subject_code }} - {{ $subject->subject_name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Instructor</label>
                        <select name="instructor_id" x-model="editModal.instructor_id"
                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">-- Select Instructor --</option>
                            @foreach ($instructors as $instructor)
                                <option value="{{ $instructor->id }}">{{ $instructor->first_name }} {{ $instructor->last_name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Section</label>
                        <select name="section_id" x-model="editModal.section_id"
                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">-- Select Section --</option>
                            @foreach ($sections as $section)
                                <option value="{{ $section->id }}">{{ $section->section_name }} ({{ $section->course->course_code ?? '—' }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Day</label>
                        <select name="day" x-model="editModal.day"
                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @foreach (['monday','tuesday','wednesday','thursday','friday','saturday','sunday'] as $day)
                                <option value="{{ $day }}">{{ ucfirst($day) }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-4 grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Start Time</label>
                            <input type="time" name="start_time" x-model="editModal.start_time"
                                   class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">End Time</label>
                            <input type="time" name="end_time" x-model="editModal.end_time"
                                   class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        </div>
                    </div>

                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Room</label>
                        <input type="text" name="room" x-model="editModal.room"
                               class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>

                    <div class="flex items-center gap-3">
                        <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2 rounded">
                            Update Schedule
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

                <h3 class="text-lg font-semibold text-gray-800 mb-2">Delete Schedule</h3>
                <p class="text-sm text-gray-500 mb-6">
                    Are you sure you want to delete <span class="font-medium text-gray-700" x-text="deleteModal.name"></span>?
                    This also deletes related Attendance Logs. This can't be undone.
                </p>

                <form method="POST" :action="'{{ url('schedules') }}/' + deleteModal.id">
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