<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Schedules
        </h2>
    </x-slot>

    <div class="py-8" x-data="{
            dayNames: { monday: 'Monday', tuesday: 'Tuesday', wednesday: 'Wednesday', thursday: 'Thursday', friday: 'Friday', saturday: 'Saturday', sunday: 'Sunday' },
            showCreateModal: {{ $errors->any() && old('_form') !== 'edit' ? 'true' : 'false' }},
            createDays: @js(old('_form') === 'create' ? old('days', []) : []),
            editModal: {
                show: {{ $errors->any() && old('_form') === 'edit' ? 'true' : 'false' }},
                id: @js(old('_form') === 'edit' ? old('_schedule_id') : null),
                subject_id: @js(old('_form') === 'edit' ? old('subject_id', '') : ''),
                instructor_id: @js(old('_form') === 'edit' ? old('instructor_id', '') : ''),
                section_id: @js(old('_form') === 'edit' ? old('section_id', '') : ''),
                days: @js(old('_form') === 'edit' ? old('days', []) : []),
                original_day: @js(old('_form') === 'edit' ? old('_original_day', '') : ''),
                recurring_days: @js(old('_form') === 'edit' ? old('_recurring_days', []) : []),
                apply_to_recurring: @js(old('_form') === 'edit' ? (bool) old('apply_to_recurring', false) : false),
                start_time: @js(old('_form') === 'edit' ? old('start_time', '') : ''),
                end_time: @js(old('_form') === 'edit' ? old('end_time', '') : ''),
                room: @js(old('_form') === 'edit' ? old('room', '') : '')
            },
            deleteModal: { show: false, id: null, name: '' },
            applyPreset(days, preset) {
                const presets = {
                    mwf: ['monday', 'wednesday', 'friday'],
                    tth: ['tuesday', 'thursday'],
                    weekdays: ['monday', 'tuesday', 'wednesday', 'thursday', 'friday']
                };
                if (presets[preset]) days.splice(0, days.length, ...presets[preset]);
            },
            selectedDayNames(days) { return days.map(day => this.dayNames[day]).join(', '); },
            dayPattern(days) {
                const labels = { monday: 'M', tuesday: 'T', wednesday: 'W', thursday: 'Th', friday: 'F', saturday: 'Sa', sunday: 'Su' };
                return days.map(day => labels[day]).join('');
            },
            toggleRecurringEdit() {
                this.editModal.days = this.editModal.apply_to_recurring
                    ? [...this.editModal.recurring_days]
                    : [this.editModal.original_day];
            }
        }">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

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
                                        @click="editModal = {{ Illuminate\Support\Js::from([
                                            'show' => true,
                                            'id' => $schedule->id,
                                            'subject_id' => (string) $schedule->subject_id,
                                            'instructor_id' => (string) $schedule->instructor_id,
                                            'section_id' => (string) $schedule->section_id,
                                            'days' => count($schedule->recurring_days) > 1 ? $schedule->recurring_days : [$schedule->day],
                                            'original_day' => $schedule->day,
                                            'recurring_days' => $schedule->recurring_days,
                                            'apply_to_recurring' => count($schedule->recurring_days) > 1,
                                            'start_time' => \Illuminate\Support\Carbon::parse($schedule->start_time)->format('H:i'),
                                            'end_time' => \Illuminate\Support\Carbon::parse($schedule->end_time)->format('H:i'),
                                            'room' => $schedule->room,
                                        ]) }}"
                                        class="text-indigo-600 hover:text-indigo-800">Edit</button>

                                    <button type="button"
                                        @click="deleteModal = {{ Illuminate\Support\Js::from([
                                            'show' => true,
                                            'id' => $schedule->id,
                                            'name' => ($schedule->subject->subject_code ?? 'Schedule').' - '.ucfirst($schedule->day),
                                        ]) }}"
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
             class="fixed inset-y-0 right-0 left-0 z-50 flex items-center justify-center p-4 lg:left-[260px]"
             :class="sidebarCollapsed ? 'lg:!left-[80px]' : 'lg:!left-[260px]'"
             style="background: rgba(0,0,0,0.4);">
            <div @click.outside="showCreateModal = false"
                 class="flex max-h-[calc(100vh-2rem)] w-full max-w-2xl flex-col overflow-hidden rounded-lg bg-white shadow-xl">

                <div class="flex shrink-0 items-center justify-between border-b border-gray-100 px-6 py-4">
                    <h3 class="text-lg font-semibold text-gray-800">Add Schedule</h3>
                    <button @click="showCreateModal = false" class="text-gray-400 hover:text-gray-600">
                        <x-heroicon-o-x-mark class="w-5 h-5" />
                    </button>
                </div>

                <form method="POST" action="{{ route('schedules.store') }}" class="grid min-h-0 gap-x-4 overflow-y-auto px-6 py-5 md:grid-cols-2">
                    @csrf
                    <input type="hidden" name="_form" value="create">

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

                    <div class="mb-4 md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Days</label>
                        <div class="mb-2 flex flex-wrap gap-2">
                            <button type="button" @click="applyPreset(createDays, 'mwf')" class="rounded-md border border-indigo-200 px-3 py-1.5 text-xs font-semibold text-indigo-700 hover:bg-indigo-50">MWF</button>
                            <button type="button" @click="applyPreset(createDays, 'tth')" class="rounded-md border border-indigo-200 px-3 py-1.5 text-xs font-semibold text-indigo-700 hover:bg-indigo-50">TTH</button>
                            <button type="button" @click="applyPreset(createDays, 'weekdays')" class="rounded-md border border-indigo-200 px-3 py-1.5 text-xs font-semibold text-indigo-700 hover:bg-indigo-50">MON-FRI</button>
                            <button type="button" @click="createDays.splice(0)" class="rounded-md border border-gray-200 px-3 py-1.5 text-xs font-semibold text-gray-600 hover:bg-gray-50">CUSTOM</button>
                        </div>
                        <div class="grid grid-cols-4 gap-2 sm:grid-cols-7">
                            @foreach (['monday' => 'MON','tuesday' => 'TUE','wednesday' => 'WED','thursday' => 'THU','friday' => 'FRI','saturday' => 'SAT','sunday' => 'SUN'] as $day => $short)
                                <label class="cursor-pointer rounded-md border px-2 py-2 text-center text-xs font-semibold transition"
                                       :class="createDays.includes('{{ $day }}') ? 'border-indigo-600 bg-indigo-600 text-white' : 'border-gray-300 bg-white text-gray-600 hover:border-indigo-300'">
                                    <input type="checkbox" name="days[]" value="{{ $day }}" x-model="createDays" class="sr-only">
                                    {{ $short }}
                                </label>
                            @endforeach
                        </div>
                        <p class="mt-2 text-xs text-gray-500">Selected days: <span x-text="selectedDayNames(createDays) || 'None'"></span></p>
                        @error('days')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                        @error('days.*')
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

                    <div class="sticky bottom-0 flex items-center justify-end gap-3 border-t border-gray-100 bg-white py-4 md:col-span-2">
                        <button type="button" @click="showCreateModal = false" class="text-sm text-gray-500 hover:text-gray-700">
                            Cancel
                        </button>
                        <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2 rounded">
                            Save Schedule
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Edit Schedule Modal --}}
        <div x-show="editModal.show" x-cloak
             class="fixed inset-y-0 right-0 left-0 z-50 overflow-y-auto px-4 py-4 sm:px-6 lg:left-[260px]"
             :class="sidebarCollapsed ? 'lg:!left-[80px]' : 'lg:!left-[260px]'"
             style="background: rgba(0,0,0,0.4);">
            <div class="flex min-h-full items-center justify-center">
            <div @click.outside="editModal.show = false"
                 class="w-full max-w-2xl rounded-lg bg-white p-4 shadow-xl sm:p-5">

                <div class="mb-3 flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-gray-800">Edit Schedule</h3>
                    <button type="button" @click="editModal.show = false" class="text-gray-400 hover:text-gray-600">
                        <x-heroicon-o-x-mark class="w-5 h-5" />
                    </button>
                </div>

                <form method="POST" :action="'{{ url('schedules') }}/' + editModal.id" class="grid gap-x-4 md:grid-cols-2">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="_form" value="edit">
                    <input type="hidden" name="_schedule_id" :value="editModal.id">
                    <input type="hidden" name="_original_day" :value="editModal.original_day">
                    <template x-for="day in editModal.recurring_days" :key="'recurring-' + day">
                        <input type="hidden" name="_recurring_days[]" :value="day">
                    </template>

                    @if ($errors->any() && old('_form') === 'edit')
                        <div class="mb-4 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700 md:col-span-2">
                            Please correct the highlighted schedule information.
                        </div>
                    @endif

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Subject</label>
                        <select name="subject_id" x-model="editModal.subject_id"
                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">-- Select Subject --</option>
                            @foreach ($subjects as $subject)
                                <option value="{{ $subject->id }}">{{ $subject->subject_code }} - {{ $subject->subject_name }}</option>
                            @endforeach
                        </select>
                        @if (old('_form') === 'edit') @error('subject_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror @endif
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
                        @if (old('_form') === 'edit') @error('instructor_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror @endif
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
                        @if (old('_form') === 'edit') @error('section_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror @endif
                    </div>

                    <div x-show="editModal.recurring_days.length > 1" class="mb-3 rounded-lg border border-indigo-200 bg-indigo-50 px-3 py-2.5 md:col-span-2">
                        <label class="flex cursor-pointer items-start gap-2.5">
                            <input type="hidden" name="apply_to_recurring" value="0">
                            <input type="checkbox" name="apply_to_recurring" value="1" x-model="editModal.apply_to_recurring" @change="toggleRecurringEdit()"
                                   class="mt-0.5 rounded border-indigo-300 text-indigo-600 focus:ring-indigo-500">
                            <span>
                                <span class="block text-sm font-semibold text-indigo-900">Apply changes to all recurring schedules</span>
                                <span class="mt-1 block text-xs leading-5 text-indigo-700">
                                    This schedule is part of <strong x-text="dayPattern(editModal.recurring_days)"></strong>.
                                    <span x-show="editModal.apply_to_recurring">Changes will apply to <span x-text="selectedDayNames(editModal.recurring_days)"></span>.</span>
                                    <span x-show="!editModal.apply_to_recurring">Only <span x-text="dayNames[editModal.original_day]"></span> will be changed.</span>
                                </span>
                            </span>
                        </label>
                    </div>

                    <div class="mb-4 md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Days</label>
                        <div x-show="!editModal.apply_to_recurring" class="mb-2 flex flex-wrap gap-2">
                            <button type="button" @click="applyPreset(editModal.days, 'mwf')" class="rounded-md border border-indigo-200 px-3 py-1.5 text-xs font-semibold text-indigo-700 hover:bg-indigo-50">MWF</button>
                            <button type="button" @click="applyPreset(editModal.days, 'tth')" class="rounded-md border border-indigo-200 px-3 py-1.5 text-xs font-semibold text-indigo-700 hover:bg-indigo-50">TTH</button>
                            <button type="button" @click="applyPreset(editModal.days, 'weekdays')" class="rounded-md border border-indigo-200 px-3 py-1.5 text-xs font-semibold text-indigo-700 hover:bg-indigo-50">MON-FRI</button>
                            <button type="button" @click="editModal.days.splice(0)" class="rounded-md border border-gray-200 px-3 py-1.5 text-xs font-semibold text-gray-600 hover:bg-gray-50">CUSTOM</button>
                        </div>
                        <div class="grid grid-cols-4 gap-2 sm:grid-cols-7">
                            @foreach (['monday' => 'MON','tuesday' => 'TUE','wednesday' => 'WED','thursday' => 'THU','friday' => 'FRI','saturday' => 'SAT','sunday' => 'SUN'] as $day => $short)
                                <label class="rounded-md border px-2 py-2 text-center text-xs font-semibold transition"
                                       :class="[
                                           editModal.days.includes('{{ $day }}') ? 'border-indigo-600 bg-indigo-600 text-white' : 'border-gray-300 bg-white text-gray-600 hover:border-indigo-300',
                                           editModal.apply_to_recurring ? 'cursor-default opacity-80' : 'cursor-pointer'
                                       ]">
                                    <input type="checkbox" name="days[]" value="{{ $day }}" x-model="editModal.days" :disabled="editModal.apply_to_recurring" class="sr-only">
                                    {{ $short }}
                                </label>
                            @endforeach
                        </div>
                        <template x-if="editModal.apply_to_recurring">
                            <div>
                                <template x-for="day in editModal.days" :key="'day-' + day"><input type="hidden" name="days[]" :value="day"></template>
                            </div>
                        </template>
                        <p class="mt-2 text-xs text-gray-500">Selected days: <span x-text="selectedDayNames(editModal.days) || 'None'"></span></p>
                        @if (old('_form') === 'edit')
                            @error('days')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                            @error('days.*')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        @endif
                    </div>

                    <div class="mb-4 grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Start Time</label>
                            <input type="time" name="start_time" x-model="editModal.start_time"
                                   class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @if (old('_form') === 'edit') @error('start_time')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror @endif
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">End Time</label>
                            <input type="time" name="end_time" x-model="editModal.end_time"
                                   class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @if (old('_form') === 'edit') @error('end_time')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror @endif
                        </div>
                    </div>

                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Room</label>
                        <input type="text" name="room" x-model="editModal.room"
                               class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @if (old('_form') === 'edit') @error('room')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror @endif
                    </div>

                    <div class="mt-2 flex items-center justify-end gap-3 pt-2 md:col-span-2">
                        <button type="button" @click="editModal.show = false" class="rounded px-4 py-2 text-sm font-medium text-gray-500 hover:bg-gray-50 hover:text-gray-700">
                            Cancel
                        </button>
                        <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2 rounded">
                            Update Schedule
                        </button>
                    </div>
                </form>
            </div>
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

                    <div class="flex items-center justify-end gap-3">
                        <button type="button" @click="deleteModal.show = false" class="text-sm text-gray-500 hover:text-gray-700">
                            Cancel
                        </button>
                        <button type="submit" class="bg-red-600 hover:bg-red-700 text-white text-sm font-medium px-4 py-2 rounded">
                            Delete
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
