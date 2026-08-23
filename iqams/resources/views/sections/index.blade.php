<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Sections
        </h2>
    </x-slot>

    <div class="py-8" x-data="{
            showCreateModal: {{ $errors->any() ? 'true' : 'false' }},
            editModal: { show: false, id: null, course_id: '', section_name: '', school_year: '', semester: '' },
            deleteModal: { show: false, id: null, name: '' },
            subjectsModal: { show: false, sectionName: '', subjects: [] }
        }">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            <div class="bg-white shadow-sm rounded-lg">
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
                    <span class="text-sm text-gray-500">{{ $sections->total() }} total</span>
                    <button @click="showCreateModal = true"
                       class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2 rounded">
                        + Add Section
                    </button>
                </div>

                <table class="w-full text-sm text-left">
                    <thead class="bg-gray-50 text-gray-500 uppercase text-xs">
                        <tr>
                            <th class="px-6 py-3">Section</th>
                            <th class="px-6 py-3">Course</th>
                            <th class="px-6 py-3">School Year</th>
                            <th class="px-6 py-3">Semester</th>
                            <th class="px-6 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($sections as $section)
                            <tr>
                                <td class="px-6 py-3 text-gray-800 font-medium">{{ $section->section_name }}</td>
                                <td class="px-6 py-3 text-gray-600">{{ $section->course->course_code ?? '—' }}</td>
                                <td class="px-6 py-3 text-gray-600">{{ $section->school_year }}</td>
                                <td class="px-6 py-3 text-gray-600">{{ ucfirst($section->semester) }}</td>
                                <td class="px-6 py-3 text-right space-x-3">
                                    <button type="button"
                                        @click="subjectsModal = {
                                            show: true,
                                            sectionName: '{{ addslashes($section->section_name) }}',
                                            subjects: [
                                                @foreach ($section->schedules as $schedule)
                                                {
                                                    subject: '{{ addslashes($schedule->subject->subject_code ?? '—') }} - {{ addslashes($schedule->subject->subject_name ?? '') }}',
                                                    instructor: '{{ addslashes(($schedule->instructor->first_name ?? '') . ' ' . ($schedule->instructor->last_name ?? '—')) }}',
                                                    day: '{{ ucfirst($schedule->day) }}',
                                                    time: '{{ \Illuminate\Support\Carbon::parse($schedule->start_time)->format('g:i A') }} - {{ \Illuminate\Support\Carbon::parse($schedule->end_time)->format('g:i A') }}',
                                                    room: '{{ addslashes($schedule->room) }}'
                                                },
                                                @endforeach
                                            ]
                                        }"
                                        class="text-gray-600 hover:text-gray-800">Subjects</button>

                                    <button type="button"
                                        @click="editModal = {
                                            show: true,
                                            id: {{ $section->id }},
                                            course_id: '{{ $section->course_id }}',
                                            section_name: '{{ addslashes($section->section_name) }}',
                                            school_year: '{{ $section->school_year }}',
                                            semester: '{{ $section->semester }}'
                                        }"
                                        class="text-indigo-600 hover:text-indigo-800">Edit</button>

                                    <button type="button"
                                        @click="deleteModal = { show: true, id: {{ $section->id }}, name: '{{ addslashes($section->section_name) }}' }"
                                        class="text-red-600 hover:text-red-800">Delete</button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-8 text-center text-gray-400">
                                    No sections yet. Add your first one.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>

                <div class="px-6 py-4 border-t border-gray-200">
                    {{ $sections->links() }}
                </div>
            </div>
        </div>

        {{-- Create Section Modal --}}
        <div x-show="showCreateModal" x-cloak
             class="fixed inset-0 z-50 flex items-center justify-center px-4"
             style="background: rgba(0,0,0,0.4);">
            <div @click.outside="showCreateModal = false"
                 class="bg-white rounded-lg shadow-xl w-full max-w-md p-6">

                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-800">Add Section</h3>
                    <button @click="showCreateModal = false" class="text-gray-400 hover:text-gray-600">
                        <x-heroicon-o-x-mark class="w-5 h-5" />
                    </button>
                </div>

                <form method="POST" action="{{ route('sections.store') }}">
                    @csrf

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Course</label>
                        <select name="course_id" class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">-- Select Course --</option>
                            @foreach ($courses as $course)
                                <option value="{{ $course->id }}" @selected(old('course_id') == $course->id)>
                                    {{ $course->course_code }} - {{ $course->course_name }}
                                </option>
                            @endforeach
                        </select>
                        @error('course_id')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Section Name</label>
                        <input type="text" name="section_name" value="{{ old('section_name') }}"
                               class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                               placeholder="e.g. BSIT-1A">
                        @error('section_name')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">School Year</label>
                        <input type="text" name="school_year" value="{{ old('school_year') }}"
                               class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                               placeholder="e.g. 2026-2027">
                        @error('school_year')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Semester</label>
                        <select name="semester" class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">-- Select Semester --</option>
                            <option value="1st" @selected(old('semester') == '1st')>1st Semester</option>
                            <option value="2nd" @selected(old('semester') == '2nd')>2nd Semester</option>
                            <option value="summer" @selected(old('semester') == 'summer')>Summer</option>
                        </select>
                        @error('semester')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex items-center gap-3">
                        <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2 rounded">
                            Save Section
                        </button>
                        <button type="button" @click="showCreateModal = false" class="text-sm text-gray-500 hover:text-gray-700">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Edit Section Modal --}}
        <div x-show="editModal.show" x-cloak
             class="fixed inset-0 z-50 flex items-center justify-center px-4"
             style="background: rgba(0,0,0,0.4);">
            <div @click.outside="editModal.show = false"
                 class="bg-white rounded-lg shadow-xl w-full max-w-md p-6">

                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-800">Edit Section</h3>
                    <button type="button" @click="editModal.show = false" class="text-gray-400 hover:text-gray-600">
                        <x-heroicon-o-x-mark class="w-5 h-5" />
                    </button>
                </div>

                <form method="POST" :action="'{{ url('sections') }}/' + editModal.id">
                    @csrf
                    @method('PUT')

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Course</label>
                        <select name="course_id" x-model="editModal.course_id"
                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">-- Select Course --</option>
                            @foreach ($courses as $course)
                                <option value="{{ $course->id }}">{{ $course->course_code }} - {{ $course->course_name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Section Name</label>
                        <input type="text" name="section_name" x-model="editModal.section_name"
                               class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">School Year</label>
                        <input type="text" name="school_year" x-model="editModal.school_year"
                               class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>

                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Semester</label>
                        <select name="semester" x-model="editModal.semester"
                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="1st">1st Semester</option>
                            <option value="2nd">2nd Semester</option>
                            <option value="summer">Summer</option>
                        </select>
                    </div>

                    <div class="flex items-center gap-3">
                        <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2 rounded">
                            Update Section
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

                <h3 class="text-lg font-semibold text-gray-800 mb-2">Delete Section</h3>
                <p class="text-sm text-gray-500 mb-6">
                    Are you sure you want to delete <span class="font-medium text-gray-700" x-text="deleteModal.name"></span>?
                    This also affects any Students and Schedules assigned to it. This can't be undone.
                </p>

                <form method="POST" :action="'{{ url('sections') }}/' + deleteModal.id">
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

        {{-- Subjects Modal (via this section's schedules) --}}
        <div x-show="subjectsModal.show" x-cloak
             class="fixed inset-0 z-50 flex items-center justify-center px-4"
             style="background: rgba(0,0,0,0.4);">
            <div @click.outside="subjectsModal.show = false"
                 class="bg-white rounded-lg shadow-xl w-full max-w-lg p-6 max-h-[85vh] overflow-y-auto scrollbar-autohide"
                 x-data="{ scrollTimer: null }"
                 @scroll="$el.classList.add('is-scrolling'); clearTimeout(scrollTimer); scrollTimer = setTimeout(() => $el.classList.remove('is-scrolling'), 800)">

                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-800">
                        Subjects — <span x-text="subjectsModal.sectionName"></span>
                    </h3>
                    <button type="button" @click="subjectsModal.show = false" class="text-gray-400 hover:text-gray-600">
                        <x-heroicon-o-x-mark class="w-5 h-5" />
                    </button>
                </div>

                <template x-if="subjectsModal.subjects.length === 0">
                    <p class="text-sm text-gray-400 py-6 text-center">No subjects scheduled for this section yet.</p>
                </template>

                <div class="space-y-3">
                    <template x-for="(item, index) in subjectsModal.subjects" :key="index">
                        <div class="border border-gray-100 rounded-lg p-3">
                            <p class="text-sm font-medium text-gray-800" x-text="item.subject"></p>
                            <p class="text-xs text-gray-500 mt-1">
                                <span x-text="item.instructor"></span> ·
                                <span x-text="item.day"></span> ·
                                <span x-text="item.time"></span> ·
                                <span x-text="item.room"></span>
                            </p>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
