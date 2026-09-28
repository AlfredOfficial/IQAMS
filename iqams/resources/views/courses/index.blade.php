<x-app-layout>
    <x-slot name="header">
        <div><h2 class="text-xl font-semibold leading-tight text-gray-800">Courses</h2><p class="mt-1 text-sm text-gray-500">Manage academic courses and their departments.</p></div>
    </x-slot>

    <div class="py-8" x-data="{
        showCreateModal: {{ $errors->any() ? 'true' : 'false' }},
        editModal: { show: false, id: null, department_id: '', code: '', name: '' },
        deleteModal: { show: false, id: null, name: '' }
    }">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
                    <span class="text-sm font-medium text-gray-500">{{ $courses->total() }} total</span>
                    <button @click="showCreateModal = true"
                        class="inline-flex items-center rounded-md bg-indigo-600 px-3.5 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                        + Add Course
                    </button>
                </div>

                <div class="overflow-x-auto"><table class="w-full min-w-[720px] table-fixed text-left text-sm">
                    <colgroup><col class="w-[18%]"><col class="w-[42%]"><col class="w-[22%]"><col class="w-[18%]"></colgroup>
                    <thead class="bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="px-5 py-3.5">Code</th><th class="px-5 py-3.5">Name</th><th class="px-5 py-3.5">Department</th><th class="px-5 py-3.5 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($courses as $course)
                            <tr class="transition-colors hover:bg-gray-50/80">
                                <td class="whitespace-nowrap px-5 py-4 font-semibold text-gray-800">{{ $course->course_code }}</td>
                                <td class="px-5 py-4 text-gray-600">{{ $course->course_name }}</td>
                                <td class="px-5 py-4 text-gray-600">{{ $course->department->department_name ?? '—' }}
                                </td>
                                <td class="px-5 py-4 text-right">
                                    <x-record-action-menu>
                                        <button type="button"
                                            @click="editModal = {{ Illuminate\Support\Js::from(['show' => true, 'id' => $course->id, 'department_id' => (string) $course->department_id, 'code' => $course->course_code, 'name' => $course->course_name]) }}"
                                            class="text-indigo-600 hover:text-indigo-800">Edit</button>

                                        <button type="button"
                                            @click="deleteModal = {{ Illuminate\Support\Js::from(['show' => true, 'id' => $course->id, 'name' => $course->course_name]) }}"
                                            class="!text-red-600 hover:!text-red-700">Delete</button>
                                    </x-record-action-menu>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-6 py-8 text-center text-gray-400">
                                    <x-heroicon-o-academic-cap class="mx-auto h-8 w-8 text-gray-300" aria-hidden="true" /><p class="mt-3 text-sm font-semibold text-gray-700">No courses found</p><p class="mx-auto mt-1 max-w-md text-sm text-gray-500">Create a course to organize academic programs and departments.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table></div>

                <div class="px-6 py-4 border-t border-gray-200">
                    {{ $courses->links() }}
                </div>
            </div>
        </div>

        {{-- Create Course Modal --}}
        <div x-show="showCreateModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center px-4"
            style="background: rgba(0,0,0,0.4);">
            <div @click.outside="showCreateModal = false" class="bg-white rounded-lg shadow-xl w-full max-w-md p-6">

                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-800">Add Course</h3>
                    <button @click="showCreateModal = false" class="text-gray-400 hover:text-gray-600">
                        <x-heroicon-o-x-mark class="w-5 h-5" />
                    </button>
                </div>

                <form method="POST" action="{{ route('courses.store') }}">
                    @csrf

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Department</label>
                        <select name="department_id"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">-- Select Department --</option>
                            @foreach ($departments as $department)
                                <option value="{{ $department->id }}" @selected(old('department_id') == $department->id)>
                                    {{ $department->department_name }}
                                </option>
                            @endforeach
                        </select>
                        @error('department_id')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Course Code</label>
                        <input type="text" name="course_code" value="{{ old('course_code') }}"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            placeholder="e.g. BSIT">
                        @error('course_code')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Course Name</label>
                        <input type="text" name="course_name" value="{{ old('course_name') }}"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            placeholder="e.g. Bachelor of Science in Information Technology">
                        @error('course_name')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex items-center justify-end gap-3">
                        <button type="button" @click="showCreateModal = false"
                            class="text-sm text-gray-500 hover:text-gray-700">
                            Cancel
                        </button>
                        <button type="submit"
                            class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2 rounded">
                            Save Course
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Edit Course Modal --}}
        <div x-show="editModal.show" x-cloak class="fixed inset-0 z-50 flex items-center justify-center px-4"
            style="background: rgba(0,0,0,0.4);">
            <div @click.outside="editModal.show = false" class="bg-white rounded-lg shadow-xl w-full max-w-md p-6">

                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-800">Edit Course</h3>
                    <button type="button" @click="editModal.show = false" class="text-gray-400 hover:text-gray-600">
                        <x-heroicon-o-x-mark class="w-5 h-5" />
                    </button>
                </div>

                <form method="POST" :action="'{{ url('courses') }}/' + editModal.id"
                    data-password-confirmation-required>
                    @csrf
                    @method('PUT')

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Department</label>
                        <select name="department_id" x-model="editModal.department_id"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">-- Select Department --</option>
                            @foreach ($departments as $department)
                                <option value="{{ $department->id }}">{{ $department->department_name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Course Code</label>
                        <input type="text" name="course_code" x-model="editModal.code"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>

                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Course Name</label>
                        <input type="text" name="course_name" x-model="editModal.name"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>

                    <div class="flex items-center justify-end gap-3">
                        <button type="button" @click="editModal.show = false"
                            class="text-sm text-gray-500 hover:text-gray-700">
                            Cancel
                        </button>
                        <button type="submit"
                            class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2 rounded">
                            Update Course
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Delete Confirmation Modal --}}
        <div x-show="deleteModal.show" x-cloak class="fixed inset-0 z-50 flex items-center justify-center px-4"
            style="background: rgba(0,0,0,0.4);">
            <div @click.outside="deleteModal.show = false" class="bg-white rounded-lg shadow-xl w-full max-w-sm p-6">

                <h3 class="text-lg font-semibold text-gray-800 mb-2">Delete Course</h3>
                <p class="text-sm text-gray-500 mb-6">
                    Are you sure you want to delete <span class="font-medium text-gray-700"
                        x-text="deleteModal.name"></span>?
                    This will also delete all its Sections and enrolled Students. This can't be undone.
                </p>

                <form method="POST" :action="'{{ url('courses') }}/' + deleteModal.id"
                    data-password-confirmation-required>
                    @csrf
                    @method('DELETE')

                    <div class="flex items-center justify-end gap-3">
                        <button type="button" @click="deleteModal.show = false"
                            class="text-sm text-gray-500 hover:text-gray-700">
                            Cancel
                        </button>
                        <button type="submit"
                            class="bg-red-600 hover:bg-red-700 text-white text-sm font-medium px-4 py-2 rounded">
                            Delete
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
