<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-gray-900">Manage Instructors</h1>
            <p class="mt-1 text-sm text-gray-500">Add, update, and manage instructor accounts.</p>
        </div>
    </x-slot>

    <div class="py-8"
         x-data="{
            showCreateModal: {{ $errors->any() ? 'true' : 'false' }},
            editModal: { show: false, id: null, department_id: '', first_name: '', last_name: '', avatar_url: '' },
            deleteModal: { show: false, id: null, name: '' },
            statusModal: { show: false, userId: null, name: '', status: '' },
            qrModal: { show: false, value: '', label: '' }
         }"
         @keydown.escape.window="showCreateModal = false; editModal.show = false; deleteModal.show = false; statusModal.show = false; qrModal.show = false">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if (session('generated_username'))
                <div class="mb-4 rounded border border-indigo-200 bg-indigo-50 px-4 py-3 text-indigo-800">
                    <p class="font-medium">Login credentials for this instructor (shown once — share them now):</p>
                    <p class="mt-1 text-sm">Username: <span class="font-mono font-semibold">{{ session('generated_username') }}</span></p>
                    <p class="text-sm">Password: <span class="font-mono font-semibold">{{ session('generated_password') }}</span></p>
                </div>
            @endif

            <div class="overflow-hidden rounded-lg bg-white shadow-sm">
                <div class="flex items-center justify-between border-b border-gray-200 px-6 py-4">
                    <span class="text-sm text-gray-500">{{ $instructors->total() }} total</span>
                    <button type="button" @click="showCreateModal = true"
                            class="rounded bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                        + Add Instructor
                    </button>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-[1040px] w-full table-fixed text-left text-sm [&_th]:px-5 [&_th]:py-4 [&_th]:align-middle [&_th]:font-medium [&_th]:tracking-wide [&_td]:h-20 [&_td]:px-5 [&_td]:py-4 [&_td]:align-middle">
                        <colgroup><col class="w-40"><col class="w-20"><col class="w-44"><col class="w-48"><col class="w-52"><col class="w-28"><col class="w-20"></colgroup>
                        <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                            <tr>
                                <th class="px-6 py-3">Employee No.</th>
                                <th class="px-6 py-3">Profile</th>
                                <th class="px-6 py-3">Name</th>
                                <th class="px-6 py-3">Department</th>
                                <th class="px-6 py-3">Email</th>
                                <th class="px-6 py-3">Status</th>
                                <th class="px-6 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($instructors as $instructor)
                                <tr class="transition-colors hover:bg-gray-50/80">
                                    <td class="whitespace-nowrap px-6 py-3 font-medium text-gray-800">{{ $instructor->employee_no }}</td>
                                    <td class="px-6 py-3"><img src="{{ $instructor->user->avatar_url ?? asset('images/default-avatar.svg') }}" alt="Profile photo" class="h-10 w-10 rounded-full object-cover"></td>
                                    <td class="whitespace-nowrap px-6 py-3 text-gray-600">{{ $instructor->first_name }} {{ $instructor->last_name }}</td>
                                    <td class="px-6 py-3 text-gray-600">{{ $instructor->department->department_name ?? '—' }}</td>
                                    <td class="px-6 py-3 text-gray-600">{{ $instructor->user->email ?? '—' }}</td>
                                    <td class="px-6 py-3"><span class="rounded px-2 py-1 text-xs font-medium {{ $instructor->user->isAccountActive() ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700' }}">{{ ucfirst($instructor->user->status) }}</span></td>
                                    <td class="px-6 py-3 text-right">
                                        <x-action-menu
                                            :delete-action="route('instructors.destroy', $instructor)"
                                            :toggle-action="route('users.status.update', $instructor->user)"
                                            :next-status="$instructor->user->isAccountActive() ? 'inactive' : 'active'"
                                            :is-active="$instructor->user->isAccountActive()"
                                            :delete-name="$instructor->fullName()">
                                            <x-slot:edit><button type="button" @click="editModal = {{ Illuminate\Support\Js::from(['show' => true, 'id' => $instructor->id, 'department_id' => (string) $instructor->department_id, 'first_name' => $instructor->first_name, 'last_name' => $instructor->last_name, 'avatar_url' => $instructor->user->avatar_url ?? asset('images/default-avatar.svg')]) }}">Edit</button></x-slot:edit>
                                            <x-slot:qr><button type="button" @click="qrModal = {{ Illuminate\Support\Js::from(['show' => true, 'value' => $instructor->qr_code, 'label' => $instructor->fullName()]) }}">View QR</button></x-slot:qr>
                                        </x-action-menu>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-6 py-8 text-center text-gray-400">
                                        No instructors yet. Add your first one.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="border-t border-gray-200 px-6 py-4">
                    {{ $instructors->links() }}
                </div>
            </div>
        </div>

        {{-- Create Instructor Modal --}}
        <div x-show="showCreateModal" x-cloak
             class="fixed inset-0 z-50 flex items-center justify-center px-4"
             style="background: rgba(0, 0, 0, 0.4)">
            <div @click.outside="showCreateModal = false"
                 class="max-h-[90vh] w-full max-w-md overflow-y-auto rounded-lg bg-white p-6 shadow-xl">
                <div class="mb-4 flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-gray-800">Add Instructor</h3>
                    <button type="button" @click="showCreateModal = false" class="text-gray-400 hover:text-gray-600">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <form method="POST" action="{{ route('instructors.store') }}" enctype="multipart/form-data">
                    @csrf
                    <div class="mb-4"><label class="mb-1 block text-sm font-medium text-gray-700">Profile Photo</label><input type="file" name="avatar" accept="image/jpeg,image/png" required class="block w-full text-sm text-gray-600">@error('avatar')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror</div>

                    <div class="mb-4">
                        <label for="department_id" class="mb-1 block text-sm font-medium text-gray-700">Department</label>
                        <select id="department_id" name="department_id"
                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">-- Select Department --</option>
                            @foreach ($departments as $department)
                                <option value="{{ $department->id }}" @selected(old('department_id') == $department->id)>
                                    {{ $department->department_code }} - {{ $department->department_name }}
                                </option>
                            @endforeach
                        </select>
                        @error('department_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div class="mb-4">
                        <label for="employee_no" class="mb-1 block text-sm font-medium text-gray-700">Employee No.</label>
                        <input id="employee_no" type="text" name="employee_no" value="{{ old('employee_no') }}"
                               placeholder="e.g. INS2026001"
                               class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <p class="mt-1 text-xs text-gray-400">This will also become the instructor's login username.</p>
                        @error('employee_no')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div class="mb-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div>
                            <label for="first_name" class="mb-1 block text-sm font-medium text-gray-700">First Name</label>
                            <input id="first_name" type="text" name="first_name" value="{{ old('first_name') }}"
                                   class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @error('first_name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="last_name" class="mb-1 block text-sm font-medium text-gray-700">Last Name</label>
                            <input id="last_name" type="text" name="last_name" value="{{ old('last_name') }}"
                                   class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @error('last_name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                    </div>

                    <div class="mb-6">
                        <label for="email" class="mb-1 block text-sm font-medium text-gray-700">Email</label>
                        <input id="email" type="email" name="email" value="{{ old('email') }}" placeholder="instructor@email.com"
                               class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @error('email')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div class="flex items-center gap-3">
                        <button type="submit" class="rounded bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Save Instructor</button>
                        <button type="button" @click="showCreateModal = false" class="text-sm text-gray-500 hover:text-gray-700">Cancel</button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Edit Instructor Modal --}}
        <div x-show="editModal.show" x-cloak
             class="fixed inset-0 z-50 flex items-center justify-center px-4"
             style="background: rgba(0, 0, 0, 0.4)">
            <div @click.outside="editModal.show = false" class="w-full max-w-md rounded-lg bg-white p-6 shadow-xl">
                <div class="mb-4 flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-gray-800">Edit Instructor</h3>
                    <button type="button" @click="editModal.show = false" class="text-gray-400 hover:text-gray-600">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <form method="POST" :action="'{{ url('instructors') }}/' + editModal.id" enctype="multipart/form-data">
                    @csrf
                    @method('PUT')
                    <div class="mb-4 flex items-center gap-4"><img :src="editModal.avatar_url" alt="Current profile photo" class="h-14 w-14 rounded-full object-cover"><div><label class="mb-1 block text-sm font-medium text-gray-700">Replace Profile Photo</label><input type="file" name="avatar" accept="image/jpeg,image/png" class="block w-full text-sm text-gray-600"></div></div>

                    <div class="mb-4">
                        <label class="mb-1 block text-sm font-medium text-gray-700">Department</label>
                        <select name="department_id" x-model="editModal.department_id"
                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">-- Select Department --</option>
                            @foreach ($departments as $department)
                                <option value="{{ $department->id }}">{{ $department->department_code }} - {{ $department->department_name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-6 grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">First Name</label>
                            <input type="text" name="first_name" x-model="editModal.first_name"
                                   class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">Last Name</label>
                            <input type="text" name="last_name" x-model="editModal.last_name"
                                   class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        </div>
                    </div>

                    <p class="mb-4 text-xs text-gray-400">Employee number, email, and login credentials can't be changed here yet.</p>
                    <div class="flex items-center gap-3">
                        <button type="submit" class="rounded bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Update Instructor</button>
                        <button type="button" @click="editModal.show = false" class="text-sm text-gray-500 hover:text-gray-700">Cancel</button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Delete Confirmation Modal --}}
        <div x-show="deleteModal.show" x-cloak
             class="fixed inset-0 z-50 flex items-center justify-center px-4"
             style="background: rgba(0, 0, 0, 0.4)">
            <div @click.outside="deleteModal.show = false" class="w-full max-w-sm rounded-lg bg-white p-6 shadow-xl">
                <h3 class="mb-2 text-lg font-semibold text-gray-800">Delete Instructor</h3>
                <p class="mb-6 text-sm text-gray-500">
                    Are you sure you want to delete <span class="font-medium text-gray-700" x-text="deleteModal.name"></span>?
                    This also deletes their login account. This can't be undone.
                </p>
                <form method="POST" :action="'{{ url('instructors') }}/' + deleteModal.id">
                    @csrf
                    @method('DELETE')
                    <div class="flex items-center gap-3">
                        <button type="submit" class="rounded bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700">Delete</button>
                        <button type="button" @click="deleteModal.show = false" class="text-sm text-gray-500 hover:text-gray-700">Cancel</button>
                    </div>
                </form>
            </div>
        </div>

        <x-account-status-modal />

        <x-qr-modal />
    </div>
</x-app-layout>
