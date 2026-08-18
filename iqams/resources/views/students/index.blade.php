<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-gray-900">Manage Students</h1>
            <p class="mt-1 text-sm text-gray-500">Add, update, and manage student accounts.</p>
        </div>
    </x-slot>

    <div class="py-8" x-data="{
            showCreateModal: {{ $errors->any() ? 'true' : 'false' }},
            editModal: { show: false, id: null, course_id: '', section_id: '', first_name: '', last_name: '', middle_name: '', status: '', avatar_url: '' },
            deleteModal: { show: false, id: null, name: '' },
            statusModal: { show: false, userId: null, name: '', status: '' },
            qrModal: { show: false, value: '', label: '' }
        }">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            @if (session('generated_username'))
                <div class="mb-4 bg-indigo-50 border border-indigo-200 text-indigo-800 px-4 py-3 rounded">
                    <p class="font-medium">Login credentials for this student (shown once — share it with them now):</p>
                    <p class="text-sm mt-1">Username: <span class="font-mono font-semibold">{{ session('generated_username') }}</span></p>
                    <p class="text-sm">Password: <span class="font-mono font-semibold">{{ session('generated_password') }}</span></p>
                </div>
            @endif

            <div class="bg-white shadow-sm rounded-lg">
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
                    <span class="text-sm text-gray-500">{{ $students->total() }} total</span>
                    <button @click="showCreateModal = true"
                       class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2 rounded">
                        + Add Student
                    </button>
                </div>

                <div class="overflow-x-auto">
                <table class="min-w-[920px] w-full table-fixed text-left text-sm [&_th]:px-5 [&_th]:py-4 [&_th]:align-middle [&_th]:font-medium [&_th]:tracking-wide [&_td]:h-20 [&_td]:px-5 [&_td]:py-4 [&_td]:align-middle">
                    <colgroup><col class="w-40"><col class="w-20"><col class="w-48"><col class="w-28"><col class="w-32"><col class="w-36"><col class="w-20"></colgroup>
                    <thead class="bg-gray-50 text-gray-500 uppercase text-xs">
                        <tr>
                            <th class="px-6 py-3">Student No.</th>
                            <th class="px-6 py-3">Profile</th>
                            <th class="px-6 py-3">Name</th>
                            <th class="px-6 py-3">Course</th>
                            <th class="px-6 py-3">Section</th>
                            <th class="px-6 py-3">Account Status</th>
                            <th class="px-6 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($students as $student)
                            <tr class="transition-colors hover:bg-gray-50/80">
                                <td class="whitespace-nowrap px-6 py-3 font-medium text-gray-800">{{ $student->student_no }}</td>
                                <td class="px-6 py-3"><img src="{{ $student->user->avatar_url ?? asset('images/default-avatar.svg') }}" alt="Profile photo" class="h-10 w-10 rounded-full object-cover"></td>
                                <td class="whitespace-nowrap px-6 py-3 text-gray-600">{{ $student->first_name }} {{ $student->last_name }}</td>
                                <td class="px-6 py-3 text-gray-600">{{ $student->course->course_code ?? '—' }}</td>
                                <td class="whitespace-nowrap px-6 py-3 text-gray-600">{{ $student->section->section_name ?? '—' }}</td>
                                <td class="px-6 py-3">
                                    <span @class([
                                        'px-2 py-1 rounded text-xs font-medium',
                                        'bg-green-50 text-green-700' => $student->user->status === 'active',
                                        'bg-red-50 text-red-700' => $student->user->status === 'inactive',
                                    ])>
                                        {{ ucfirst($student->user->status) }}
                                    </span>
                                </td>
                                <td class="px-6 py-3 text-right">
                                    <x-action-menu
                                        :delete-action="route('students.destroy', $student)"
                                        :toggle-action="route('users.status.update', $student->user)"
                                        :next-status="$student->user->isAccountActive() ? 'inactive' : 'active'"
                                        :is-active="$student->user->isAccountActive()"
                                        :delete-name="$student->fullName()">
                                        <x-slot:edit><button type="button" @click="editModal = {{ Illuminate\Support\Js::from(['show' => true, 'id' => $student->id, 'course_id' => (string) $student->course_id, 'section_id' => (string) $student->section_id, 'first_name' => $student->first_name, 'last_name' => $student->last_name, 'middle_name' => $student->middle_name, 'status' => $student->status, 'avatar_url' => $student->user->avatar_url ?? asset('images/default-avatar.svg')]) }}">Edit</button></x-slot:edit>
                                        <x-slot:qr><button type="button" @click="qrModal = {{ Illuminate\Support\Js::from(['show' => true, 'value' => $student->student_no, 'label' => $student->fullName()]) }}">View QR</button></x-slot:qr>
                                    </x-action-menu>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-6 py-8 text-center text-gray-400">
                                    No students yet. Add your first one.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
                </div>

                <div class="px-6 py-4 border-t border-gray-200">
                    {{ $students->links() }}
                </div>
            </div>
        </div>

        {{-- Create Student Modal --}}
        <div x-show="showCreateModal" x-cloak
             class="fixed inset-0 z-50 flex items-center justify-center px-4"
             style="background: rgba(0,0,0,0.4);">
            <div @click.outside="showCreateModal = false"
                 class="bg-white rounded-lg shadow-xl w-full max-w-md p-6 max-h-[90vh] overflow-y-auto">

                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-800">Add Student</h3>
                    <button @click="showCreateModal = false" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <form method="POST" action="{{ route('students.store') }}" enctype="multipart/form-data">
                    @csrf
                    <div class="mb-4"><label class="mb-1 block text-sm font-medium text-gray-700">Profile Photo</label><input type="file" name="avatar" accept="image/jpeg,image/png" required class="block w-full text-sm text-gray-600">@error('avatar')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror</div>

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
                        <label class="block text-sm font-medium text-gray-700 mb-1">Section <span class="text-gray-400 font-normal">(optional)</span></label>
                        <select name="section_id" class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">-- No Section Yet --</option>
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
                        <label class="block text-sm font-medium text-gray-700 mb-1">Student No.</label>
                        <input type="text" name="student_no" value="{{ old('student_no') }}"
                               class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                               placeholder="e.g. 2026-00123">
                        <p class="mt-1 text-xs text-gray-400">This will also become the student's login username.</p>
                        @error('student_no')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="mb-4 grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">First Name</label>
                            <input type="text" name="first_name" value="{{ old('first_name') }}"
                                   class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @error('first_name')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Last Name</label>
                            <input type="text" name="last_name" value="{{ old('last_name') }}"
                                   class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @error('last_name')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Middle Name <span class="text-gray-400 font-normal">(optional)</span></label>
                        <input type="text" name="middle_name" value="{{ old('middle_name') }}"
                               class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @error('middle_name')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                        <input type="email" name="email" value="{{ old('email') }}"
                               class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                               placeholder="student@email.com">
                        @error('email')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex items-center gap-3">
                        <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2 rounded">
                            Save Student
                        </button>
                        <button type="button" @click="showCreateModal = false" class="text-sm text-gray-500 hover:text-gray-700">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Edit Student Modal --}}
        <div x-show="editModal.show" x-cloak
             class="fixed inset-0 z-50 flex items-center justify-center px-4"
             style="background: rgba(0,0,0,0.4);">
            <div @click.outside="editModal.show = false"
                 class="bg-white rounded-lg shadow-xl w-full max-w-md p-6 max-h-[90vh] overflow-y-auto">

                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-800">Edit Student</h3>
                    <button type="button" @click="editModal.show = false" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <form method="POST" :action="'{{ url('students') }}/' + editModal.id" enctype="multipart/form-data">
                    @csrf
                    @method('PUT')
                    <div class="mb-4 flex items-center gap-4"><img :src="editModal.avatar_url" alt="Current profile photo" class="h-14 w-14 rounded-full object-cover"><div><label class="mb-1 block text-sm font-medium text-gray-700">Replace Profile Photo</label><input type="file" name="avatar" accept="image/jpeg,image/png" class="block w-full text-sm text-gray-600"></div></div>

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
                        <label class="block text-sm font-medium text-gray-700 mb-1">Section</label>
                        <select name="section_id" x-model="editModal.section_id"
                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">-- No Section --</option>
                            @foreach ($sections as $section)
                                <option value="{{ $section->id }}">{{ $section->section_name }} ({{ $section->course->course_code ?? '—' }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-4 grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">First Name</label>
                            <input type="text" name="first_name" x-model="editModal.first_name"
                                   class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Last Name</label>
                            <input type="text" name="last_name" x-model="editModal.last_name"
                                   class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Middle Name</label>
                        <input type="text" name="middle_name" x-model="editModal.middle_name"
                               class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>

                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                        <select name="status" x-model="editModal.status"
                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                            <option value="graduated">Graduated</option>
                            <option value="dropped">Dropped</option>
                        </select>
                    </div>

                    <p class="text-xs text-gray-400 mb-4">Email and login credentials can't be changed here yet.</p>

                    <div class="flex items-center gap-3">
                        <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2 rounded">
                            Update Student
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

                <h3 class="text-lg font-semibold text-gray-800 mb-2">Delete Student</h3>
                <p class="text-sm text-gray-500 mb-6">
                    Are you sure you want to delete <span class="font-medium text-gray-700" x-text="deleteModal.name"></span>?
                    This also deletes their login account and attendance history. This can't be undone.
                </p>

                <form method="POST" :action="'{{ url('students') }}/' + deleteModal.id">
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

        <x-account-status-modal />
        <x-qr-modal />
    </div>
</x-app-layout>
