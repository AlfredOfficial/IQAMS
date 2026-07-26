<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Departments
        </h2>
    </x-slot>

    <div class="py-8" x-data="{
            showCreateModal: {{ $errors->any() ? 'true' : 'false' }},
            editModal: { show: false, id: null, code: '', name: '' },
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
                    <span class="text-sm text-gray-500">{{ $departments->total() }} total</span>
                    <button @click="showCreateModal = true"
                       class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2 rounded">
                        + Add Department
                    </button>
                </div>

                <table class="w-full text-sm text-left">
                    <thead class="bg-gray-50 text-gray-500 uppercase text-xs">
                        <tr>
                            <th class="px-6 py-3">Code</th>
                            <th class="px-6 py-3">Name</th>
                            <th class="px-6 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($departments as $department)
                            <tr>
                                <td class="px-6 py-3 text-gray-800 font-medium">{{ $department->department_code }}</td>
                                <td class="px-6 py-3 text-gray-600">{{ $department->department_name }}</td>
                                <td class="px-6 py-3 text-right space-x-3">
                                    <button type="button"
                                        @click="editModal = { show: true, id: {{ $department->id }}, code: '{{ $department->department_code }}', name: '{{ addslashes($department->department_name) }}' }"
                                        class="text-indigo-600 hover:text-indigo-800">Edit</button>

                                    <button type="button"
                                        @click="deleteModal = { show: true, id: {{ $department->id }}, name: '{{ addslashes($department->department_name) }}' }"
                                        class="text-red-600 hover:text-red-800">Delete</button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="px-6 py-8 text-center text-gray-400">
                                    No departments yet. Add your first one.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>

                <div class="px-6 py-4 border-t border-gray-200">
                    {{ $departments->links() }}
                </div>
            </div>
        </div>

        {{-- Create Department Modal --}}
        <div x-show="showCreateModal"
             x-cloak
             class="fixed inset-0 z-50 flex items-center justify-center px-4"
             style="background: rgba(0,0,0,0.4);">
            <div @click.outside="showCreateModal = false"
                 class="bg-white rounded-lg shadow-xl w-full max-w-md p-6">

                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-800">Add Department</h3>
                    <button @click="showCreateModal = false" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <form method="POST" action="{{ route('departments.store') }}">
                    @csrf

                    <div class="mb-4">
                        <label for="department_code" class="block text-sm font-medium text-gray-700 mb-1">
                            Department Code
                        </label>
                        <input type="text" name="department_code" id="department_code"
                               value="{{ old('department_code') }}"
                               class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                               placeholder="e.g. CCS">
                        @error('department_code')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="mb-6">
                        <label for="department_name" class="block text-sm font-medium text-gray-700 mb-1">
                            Department Name
                        </label>
                        <input type="text" name="department_name" id="department_name"
                               value="{{ old('department_name') }}"
                               class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                               placeholder="e.g. College of Computer Studies">
                        @error('department_name')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex items-center gap-3">
                        <button type="submit"
                                class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2 rounded">
                            Save Department
                        </button>
                        <button type="button" @click="showCreateModal = false" class="text-sm text-gray-500 hover:text-gray-700">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Edit Department Modal (shared, populated per row via editModal state) --}}
        <div x-show="editModal.show"
             x-cloak
             class="fixed inset-0 z-50 flex items-center justify-center px-4"
             style="background: rgba(0,0,0,0.4);">
            <div @click.outside="editModal.show = false"
                 class="bg-white rounded-lg shadow-xl w-full max-w-md p-6">

                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-800">Edit Department</h3>
                    <button type="button" @click="editModal.show = false" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <form method="POST" :action="'{{ url('departments') }}/' + editModal.id">
                    @csrf
                    @method('PUT')

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Department Code
                        </label>
                        <input type="text" name="department_code" x-model="editModal.code"
                               class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>

                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Department Name
                        </label>
                        <input type="text" name="department_name" x-model="editModal.name"
                               class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>

                    <div class="flex items-center gap-3">
                        <button type="submit"
                                class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2 rounded">
                            Update Department
                        </button>
                        <button type="button" @click="editModal.show = false" class="text-sm text-gray-500 hover:text-gray-700">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Delete Confirmation Modal --}}
        <div x-show="deleteModal.show"
             x-cloak
             class="fixed inset-0 z-50 flex items-center justify-center px-4"
             style="background: rgba(0,0,0,0.4);">
            <div @click.outside="deleteModal.show = false"
                 class="bg-white rounded-lg shadow-xl w-full max-w-sm p-6">

                <h3 class="text-lg font-semibold text-gray-800 mb-2">Delete Department</h3>
                <p class="text-sm text-gray-500 mb-6">
                    Are you sure you want to delete <span class="font-medium text-gray-700" x-text="deleteModal.name"></span>?
                    This can't be undone.
                </p>

                <form method="POST" :action="'{{ url('departments') }}/' + deleteModal.id">
                    @csrf
                    @method('DELETE')

                    <div class="flex items-center gap-3">
                        <button type="submit"
                                class="bg-red-600 hover:bg-red-700 text-white text-sm font-medium px-4 py-2 rounded">
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