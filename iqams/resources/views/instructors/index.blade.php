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
            editModal: { show: false, id: null, department_id: '', name_prefix: '', first_name: '', middle_name: '', last_name: '', professional_credentials: '', avatar_url: '' },
            deleteModal: { show: false, id: null, name: '' },
            statusModal: { show: false, userId: null, name: '', status: '' },
            qrModal: { show: false, value: '', label: '' },
            selectedIds: []
         }"
         @keydown.escape.window="showCreateModal = false; editModal.show = false; deleteModal.show = false; statusModal.show = false; qrModal.show = false">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <x-temporary-credentials-alert role="instructor" />
            <div class="overflow-hidden rounded-lg bg-white shadow-sm">
                <div class="flex flex-col gap-3 border-b border-gray-200 px-6 py-4 sm:flex-row sm:items-center sm:justify-between">
                    <span class="text-sm text-gray-500">{{ $instructors->total() }} total</span>
                    <div class="flex flex-wrap items-center gap-2"><button type="button" @click="window.ensureIqamsQrCode().then(() => window.printIqamsIdCards(selectedIds.map(id => '{{ url('admin/id-cards') }}/' + id))).catch(error => window.alert(error.message))" :disabled="selectedIds.length === 0" class="whitespace-nowrap rounded border border-indigo-200 bg-indigo-50 px-4 py-2 text-sm font-medium text-indigo-700 disabled:cursor-not-allowed disabled:opacity-50">Print selected ID cards</button><button type="button" @click="showCreateModal = true"
                            class="rounded bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                        + Add Instructor
                    </button></div>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-[1200px] w-full table-fixed text-left text-sm [&_th]:px-5 [&_th]:py-4 [&_th]:align-middle [&_th]:font-medium [&_th]:tracking-wide [&_td]:h-20 [&_td]:px-5 [&_td]:py-4 [&_td]:align-middle [&_td:nth-child(6)]:break-all [&_td:nth-child(7)]:whitespace-nowrap [&_td:nth-child(8)]:whitespace-nowrap">
                        <colgroup><col class="w-16"><col class="w-32"><col class="w-20"><col class="w-48"><col class="w-48"><col class="w-64"><col class="w-36"><col class="w-20"></colgroup>
                        <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                            <tr>
                                <th class="px-6 py-3">Select</th><th class="px-6 py-3">Instructor ID</th> {{-- keep the employee no in the data base nevermind the table --}}
                                <th class="px-6 py-3">Profile</th>
                                <th class="px-6 py-3">Name</th>
                                <th class="px-6 py-3">Department</th>
                                <th class="px-6 py-3">Email</th>
                                <th class="px-6 py-3">Status</th>
                                <th class="whitespace-nowrap px-6 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($instructors as $instructor)
                                <tr class="transition-colors hover:bg-gray-50/80">
                                    <td class="px-6 py-3"><input type="checkbox" value="{{ $instructor->user_id }}" @change="selectedIds = $event.target.checked ? [...selectedIds, {{ $instructor->user_id }}] : selectedIds.filter(id => id !== {{ $instructor->user_id }})" class="rounded border-gray-300 text-indigo-600"></td><td class="whitespace-nowrap px-6 py-3 font-medium text-gray-800">{{ $instructor->employee_no }}</td>
                                    <td class="px-6 py-3"><img loading="lazy" width="40" height="40" src="{{ $instructor->user->avatar_thumbnail_url ?? asset('images/default-avatar.svg') }}" alt="Profile photo" class="h-10 w-10 rounded-full object-cover"></td>
                                    <td class="break-words px-6 py-3 text-gray-600">{{ $instructor->fullName() }}</td>
                                    <td class="break-words px-6 py-3 text-gray-600">{{ $instructor->department->department_name ?? '—' }}</td>
                                    <td class="px-6 py-3 text-gray-600">{{ $instructor->user->email ?? '—' }}</td>
                                    <td class="whitespace-nowrap px-6 py-3">
                                        <x-student-status :status="$instructor->user->status" />
                                        @if ($instructor->user->must_change_password)
                                            <span class="mt-1 block text-xs font-medium text-amber-700">Initial password</span>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-3 text-right">
                                        <x-action-menu
                                            :delete-action="route('instructors.destroy', $instructor)"
                                            :toggle-action="route('users.status.update', $instructor->user)"
                                            :next-status="$instructor->user->isAccountActive() ? 'inactive' : 'active'"
                                            :is-active="$instructor->user->isAccountActive()"
                                            :requires-password-confirmation="true"
                                            :delete-name="$instructor->fullName()">
                                            <x-slot:reset>
                                                <form method="POST" action="{{ route('users.password.reset', $instructor->user) }}" onsubmit="return confirm('Reset this account to its temporary password?')" @submit.prevent="open = false; $dispatch('password-confirmation-required', { form: $el })">
                                                    @csrf
                                                    <button type="submit">Reset temporary password</button>
                                                </form>
                                            </x-slot:reset>
                                            <x-slot:qr><button type="button" @click="fetch('{{ url('admin/id-cards') }}/{{ $instructor->user_id }}', { headers: { Accept: 'application/json' }, credentials: 'same-origin' }).then(response => response.json().then(data => { if (!response.ok) throw new Error(data.message || 'QR unavailable.'); qrModal = { show: true, value: data.qr_code, label: data.name }; })).catch(error => window.alert(error.message))">View QR</button><button type="button" @click="window.ensureIqamsQrCode().then(() => window.printIqamsIdCard('{{ url('admin/id-cards') }}/{{ $instructor->user_id }}')).catch(error => window.alert(error.message))">Print ID Card</button></x-slot:qr>
                                            <x-slot:edit><button type="button" @click="editModal = {{ Illuminate\Support\Js::from(['show' => true, 'id' => $instructor->id, 'department_id' => (string) $instructor->department_id, 'name_prefix' => $instructor->name_prefix ?? '', 'first_name' => $instructor->first_name, 'middle_name' => $instructor->middle_name ?? '', 'last_name' => $instructor->last_name, 'professional_credentials' => $instructor->professional_credentials ?? '', 'avatar_url' => $instructor->user->avatar_thumbnail_url ?? asset('images/default-avatar.svg')]) }}">Edit</button></x-slot:edit>
                                        </x-action-menu>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="px-6 py-8 text-center text-gray-400">
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
             class="fixed inset-0 z-[70] overflow-y-auto px-4 py-4 sm:px-6"
             style="background: rgba(0, 0, 0, 0.4)">
            <div class="flex min-h-full items-center justify-center">
            <div @click.outside="showCreateModal = false"
                 class="w-full max-w-2xl rounded-lg bg-white p-4 shadow-xl sm:p-5">
                <div class="mb-3 flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-gray-800">Add Instructor</h3>
                    <button type="button" @click="showCreateModal = false" class="text-gray-400 hover:text-gray-600">
                        <x-heroicon-o-x-mark class="h-5 w-5" />
                    </button>
                </div>

                <form method="POST" action="{{ route('instructors.store') }}" enctype="multipart/form-data">
                    @csrf
                    <div class="grid grid-cols-1 gap-x-5 gap-y-3 md:grid-cols-2">
                        <div class="md:col-span-2">
                            <label class="mb-1 block text-sm font-medium text-gray-700">Profile Photo</label>
                            <input type="file" name="avatar" accept="image/jpeg,image/png" required
                                   class="block w-full text-sm text-gray-600">
                            @error('avatar')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="employee_no" class="mb-1 block text-sm font-medium text-gray-700">Instructor ID</label>
                            <input id="employee_no" type="text" name="employee_no" value="{{ old('employee_no') }}" placeholder="e.g. INS2026001"
                                   class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <p class="mt-1 text-xs leading-4 text-gray-400">This will also become the instructor's login username.</p>
                            @error('employee_no')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>

                        <div>
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
                        <div>
                            <label for="name_prefix" class="mb-1 block text-sm font-medium text-gray-700">Title / Prefix</label>
                            <input id="name_prefix" type="text" name="name_prefix" value="{{ old('name_prefix') }}" placeholder="e.g. Engr."
                                   class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @error('name_prefix')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="first_name" class="mb-1 block text-sm font-medium text-gray-700">First Name</label>
                            <input id="first_name" type="text" name="first_name" value="{{ old('first_name') }}"
                                   class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @error('first_name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                    </div>

                    <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div>
                            <label for="middle_name" class="mb-1 block text-sm font-medium text-gray-700">Middle Name <span class="font-normal text-gray-400">(optional)</span></label>
                            <input id="middle_name" type="text" name="middle_name" value="{{ old('middle_name') }}"
                                   class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @error('middle_name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="last_name" class="mb-1 block text-sm font-medium text-gray-700">Last Name</label>
                            <input id="last_name" type="text" name="last_name" value="{{ old('last_name') }}"
                                   class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @error('last_name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                    </div>

                    <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div>
                        <label for="professional_credentials" class="mb-1 block text-sm font-medium text-gray-700">Professional Credentials</label>
                        <input id="professional_credentials" type="text" name="professional_credentials" value="{{ old('professional_credentials') }}" placeholder="e.g. LPT, MATVE"
                               class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <p class="mt-1 text-xs leading-4 text-gray-400">Displayed after the name using conventional formatting.</p>
                        @error('professional_credentials')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="email" class="mb-1 block text-sm font-medium text-gray-700">Email</label>
                        <input id="email" type="email" name="email" value="{{ old('email') }}" placeholder="instructor@email.com"
                               class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @error('email')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    </div>

                    <div class="mt-4 flex items-center justify-end gap-3 border-t border-gray-100 pt-3">
                        <button type="button" @click="showCreateModal = false" class="rounded px-4 py-2 text-sm font-medium text-gray-500 hover:bg-gray-50 hover:text-gray-700">Cancel</button>
                        <button type="submit" class="rounded bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Save Instructor</button>
                    </div>
                </form>
            </div>
            </div>
        </div>

        {{-- Edit Instructor Modal --}}
        <div x-show="editModal.show" x-cloak
             class="fixed inset-0 z-50 flex items-center justify-center px-4"
             style="background: rgba(0, 0, 0, 0.4)">
            <div @click.outside="editModal.show = false" class="max-h-[90vh] w-full max-w-md overflow-y-auto rounded-lg bg-white p-6 shadow-xl">
                <div class="mb-4 flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-gray-800">Edit Instructor</h3>
                    <button type="button" @click="editModal.show = false" class="text-gray-400 hover:text-gray-600">
                        <x-heroicon-o-x-mark class="h-5 w-5" />
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

                    <div class="mb-4 grid grid-cols-1 gap-3 sm:grid-cols-[0.65fr_1.35fr]">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">Title / Prefix</label>
                            <input type="text" name="name_prefix" x-model="editModal.name_prefix" placeholder="e.g. Engr."
                                   class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">First Name</label>
                            <input type="text" name="first_name" x-model="editModal.first_name"
                                   class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        </div>
                    </div>

                    <div class="mb-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">Middle Name</label>
                            <input type="text" name="middle_name" x-model="editModal.middle_name" placeholder="Optional"
                                   class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">Last Name</label>
                            <input type="text" name="last_name" x-model="editModal.last_name"
                                   class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        </div>
                    </div>

                    <div class="mb-6">
                        <label class="mb-1 block text-sm font-medium text-gray-700">Professional Credentials</label>
                        <input type="text" name="professional_credentials" x-model="editModal.professional_credentials" placeholder="e.g. LPT, MATVE"
                               class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
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
                <h3 class="mb-2 text-lg font-semibold text-gray-800">Deactivate Instructor</h3>
                <p class="mb-6 text-sm text-gray-500">
                    Are you sure you want to deactivate <span class="font-medium text-gray-700" x-text="deleteModal.name"></span>?
                    Their login will be disabled and attendance history will be retained.
                </p>
                <form method="POST" :action="'{{ url('instructors') }}/' + deleteModal.id">
                    @csrf
                    @method('DELETE')
                    <div class="flex items-center gap-3">
                        <button type="submit" class="rounded bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700">Deactivate / Archive</button>
                        <button type="button" @click="deleteModal.show = false" class="text-sm text-gray-500 hover:text-gray-700">Cancel</button>
                    </div>
                </form>
            </div>
        </div>

        <x-account-status-modal />
        <x-password-confirmation-modal />

        <x-qr-modal />
    </div>
</x-app-layout>
