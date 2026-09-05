<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-gray-900">Manage Staff</h1>
            <p class="mt-1 text-sm text-gray-500">Add, update, and manage non-teaching staff accounts.</p>
        </div>
    </x-slot>

    <div class="py-8" x-data="{
            showCreateModal: {{ $errors->any() ? 'true' : 'false' }},
            editModal: { show: false, id: null, office_unit_id: '', employee_no: '', name_prefix: '', first_name: '', middle_name: '', last_name: '', name_suffix: '', email: '', avatar_url: '' },
            deleteModal: { show: false, id: null, name: '' },
            statusModal: { show: false, userId: null, name: '', status: '' },
            qrModal: { show: false, value: '', label: '' },
            selectedIds: []
        }">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            <x-temporary-credentials-alert role="staff member" />

            <div class="bg-white shadow-sm rounded-lg">
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
                    <span class="text-sm text-gray-500">{{ $staffMembers->total() }} total</span>
                    <div class="flex items-center gap-2"><button type="button" @click="window.ensureIqamsQrCode().then(() => window.printIqamsIdCards(selectedIds.map(id => '{{ url('admin/id-cards') }}/' + id))).catch(error => window.alert(error.message))" :disabled="selectedIds.length === 0" class="rounded border border-indigo-200 bg-indigo-50 px-4 py-2 text-sm font-medium text-indigo-700 disabled:cursor-not-allowed disabled:opacity-50">Print selected ID cards</button><button @click="showCreateModal = true"
                       class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2 rounded">
                        + Add Staff
                    </button></div>
                </div>

                <div class="overflow-x-auto">
                <table class="min-w-[1200px] w-full table-fixed text-left text-sm [&_th]:px-5 [&_th]:py-4 [&_th]:align-middle [&_th]:font-medium [&_th]:tracking-wide [&_td]:h-20 [&_td]:px-5 [&_td]:py-4 [&_td]:align-middle">
                    <colgroup><col class="w-16"><col class="w-32"><col class="w-20"><col class="w-48"><col class="w-48"><col class="w-64"><col class="w-36"><col class="w-20"></colgroup>
                    <thead class="bg-gray-50 text-gray-500 uppercase text-xs">
                        <tr>
                            <th class="px-6 py-3">Select</th><th class="px-6 py-3">Staff ID</th> {{-- never mind the table keep the employee no in the data base --}}
                            <th class="px-6 py-3">Profile</th>
                            <th class="px-6 py-3">Name</th>
                            <th class="px-6 py-3">Office/Unit</th>
                            <th class="px-6 py-3">Email</th>
                            <th class="px-6 py-3">Status</th>
                            <th class="px-6 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($staffMembers as $staff)
                            <tr class="transition-colors hover:bg-gray-50/80">
                                <td class="px-6 py-3"><input type="checkbox" value="{{ $staff->user_id }}" @change="selectedIds = $event.target.checked ? [...selectedIds, {{ $staff->user_id }}] : selectedIds.filter(id => id !== {{ $staff->user_id }})" class="rounded border-gray-300 text-indigo-600"></td><td class="whitespace-nowrap px-6 py-3 font-medium text-gray-800">{{ $staff->employee_no }}</td>
                                    <td class="px-6 py-3"><img loading="lazy" width="40" height="40" src="{{ $staff->user->avatar_thumbnail_url ?? asset('images/default-avatar.svg') }}" alt="Profile photo" class="h-10 w-10 rounded-full object-cover"></td>
                                <td class="break-words px-6 py-3 text-gray-600">{{ $staff->fullName() }}</td>
                                <td class="px-6 py-3 text-gray-600">{{ $staff->officeUnit?->name ?? 'Not assigned' }}</td>
                                <td class="px-6 py-3 text-gray-600">{{ $staff->user->email ?? '—' }}</td>
                                <td class="whitespace-nowrap px-6 py-3">
                                    <x-student-status :status="$staff->user->status" />
                                    @if ($staff->user->must_change_password)
                                        <span class="mt-1 block text-xs font-medium text-amber-700">Initial password</span>
                                    @endif
                                </td>
                                <td class="px-6 py-3 text-right">
                                    <x-action-menu
                                        :delete-action="route('non-teaching-staff.destroy', $staff)"
                                        :toggle-action="route('users.status.update', $staff->user)"
                                        :next-status="$staff->user->isAccountActive() ? 'inactive' : 'active'"
                                        :is-active="$staff->user->isAccountActive()"
                                        :requires-password-confirmation="true"
                                        :delete-name="$staff->fullName()">
                                        <x-slot:reset>
                                            <form method="POST" action="{{ route('users.password.reset', $staff->user) }}" onsubmit="return confirm('Reset this account to its temporary password?')" @submit.prevent="open = false; $dispatch('password-confirmation-required', { form: $el })">
                                                @csrf
                                                <button type="submit">Reset temporary password</button>
                                            </form>
                                        </x-slot:reset>
                                        <x-slot:qr><button type="button" @click="fetch('{{ url('admin/id-cards') }}/{{ $staff->user_id }}', { headers: { Accept: 'application/json' }, credentials: 'same-origin' }).then(response => response.json().then(data => { if (!response.ok) throw new Error(data.message || 'QR unavailable.'); qrModal = { show: true, value: data.qr_code, label: data.name }; })).catch(error => window.alert(error.message))">View QR</button><button type="button" @click="window.ensureIqamsQrCode().then(() => window.printIqamsIdCard('{{ url('admin/id-cards') }}/{{ $staff->user_id }}')).catch(error => window.alert(error.message))">Print ID Card</button></x-slot:qr>
                                        <x-slot:edit><button type="button" @click="editModal = {{ Illuminate\Support\Js::from(['show' => true, 'id' => $staff->id, 'office_unit_id' => (string) $staff->office_unit_id, 'employee_no' => $staff->employee_no, 'name_prefix' => $staff->name_prefix ?? '', 'first_name' => $staff->first_name, 'middle_name' => $staff->middle_name ?? '', 'last_name' => $staff->last_name, 'name_suffix' => $staff->name_suffix ?? '', 'email' => $staff->user->email ?? '', 'avatar_url' => $staff->user->avatar_thumbnail_url ?? asset('images/default-avatar.svg')]) }}">Edit</button></x-slot:edit>
                                    </x-action-menu>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-6 py-8 text-center text-gray-400">
                                    No staff members yet. Add your first one.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
                </div>

                <div class="px-6 py-4 border-t border-gray-200">
                    {{ $staffMembers->links() }}
                </div>
            </div>
        </div>

        {{-- Create Staff Modal --}}
        <div x-show="showCreateModal" x-cloak
             class="fixed inset-0 z-[70] flex items-center justify-center overflow-y-auto px-4 py-6"
             style="background: rgba(0,0,0,0.4);">
            <div @click.outside="showCreateModal = false"
                 class="w-full max-w-2xl rounded-lg bg-white p-4 shadow-xl sm:p-5">

                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-800">Add Staff Member</h3>
                    <button @click="showCreateModal = false" class="text-gray-400 hover:text-gray-600">
                        <x-heroicon-o-x-mark class="w-5 h-5" />
                    </button>
                </div>

                <form method="POST" action="{{ route('non-teaching-staff.store') }}" enctype="multipart/form-data">
                    @csrf
                    <div class="mb-3"><label class="mb-1 block text-sm font-medium text-gray-700">Profile Photo</label><input type="file" name="avatar" accept="image/jpeg,image/png" required class="block w-full text-sm text-gray-600">@error('avatar')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror</div>

                    <div class="grid grid-cols-1 gap-x-5 gap-y-3 sm:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">Staff ID</label>
                            <input type="text" name="employee_no" value="{{ old('employee_no') }}" placeholder="e.g. STF2026001"
                                   class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <p class="mt-1 text-xs leading-4 text-gray-400">This will also become the staff's login username.</p>
                            @error('employee_no')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">Office/Unit</label>
                            <select name="office_unit_id" class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">-- Select Office/Unit --</option>
                                @foreach ($officeUnits as $officeUnit)
                                    <option value="{{ $officeUnit->id }}" @selected(old('office_unit_id') == $officeUnit->id)>{{ $officeUnit->code }} - {{ $officeUnit->name }}</option>
                                @endforeach
                            </select>
                            @error('office_unit_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">Prefix <span class="font-normal text-gray-400">(optional)</span></label>
                            <input type="text" name="name_prefix" value="{{ old('name_prefix') }}" placeholder="e.g. Engr."
                                   class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @error('name_prefix')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">First Name</label>
                            <input type="text" name="first_name" value="{{ old('first_name') }}"
                                   class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @error('first_name')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">Middle Name <span class="font-normal text-gray-400">(optional)</span></label>
                            <input type="text" name="middle_name" value="{{ old('middle_name') }}"
                                   class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @error('middle_name')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Last Name</label>
                        <input type="text" name="last_name" value="{{ old('last_name') }}"
                               class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @error('last_name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Suffix <span class="font-normal text-gray-400">(optional)</span></label>
                        <input type="text" name="name_suffix" value="{{ old('name_suffix') }}" placeholder="e.g. RN"
                               class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <p class="mt-1 text-xs leading-4 text-gray-400">Displayed after the name using conventional formatting.</p>
                        @error('name_suffix')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                        <input type="email" name="email" value="{{ old('email') }}"
                               class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                               placeholder="staff@email.com">
                        @error('email')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                        </div>
                    </div>

                    <div class="mt-4 flex items-center justify-end gap-3 border-t border-gray-100 pt-3">
                        <button type="button" @click="showCreateModal = false" class="rounded px-4 py-2 text-sm font-medium text-gray-500 hover:bg-gray-50 hover:text-gray-700">
                            Cancel
                        </button>
                        <button type="submit" class="rounded bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                            Save Staff Member
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Edit Staff Modal --}}
        <div x-show="editModal.show" x-cloak
             class="fixed inset-0 z-[70] flex items-center justify-center overflow-y-auto px-4 py-6"
             style="background: rgba(0,0,0,0.4);">
            <div @click.outside="editModal.show = false"
                 class="w-full max-w-2xl rounded-lg bg-white p-4 shadow-xl sm:p-5">

                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-800">Edit Staff Member</h3>
                    <button type="button" @click="editModal.show = false" class="text-gray-400 hover:text-gray-600">
                        <x-heroicon-o-x-mark class="w-5 h-5" />
                    </button>
                </div>

                <form method="POST" :action="'{{ url('non-teaching-staff') }}/' + editModal.id" enctype="multipart/form-data">
                    @csrf
                    @method('PUT')
                    <div class="mb-4 flex items-center gap-4"><img :src="editModal.avatar_url" alt="Current profile photo" class="h-14 w-14 rounded-full object-cover"><div><label class="mb-1 block text-sm font-medium text-gray-700">Replace Profile Photo</label><input type="file" name="avatar" accept="image/jpeg,image/png" class="block w-full text-sm text-gray-600"></div></div>

                    <div class="grid grid-cols-1 gap-x-5 gap-y-3 sm:grid-cols-2">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Staff ID</label>
                            <input type="text" x-model="editModal.employee_no" disabled
                                   class="w-full rounded-md border-gray-200 bg-gray-50 text-gray-500 shadow-sm">
                            <p class="mt-1 text-xs text-gray-400">Staff ID and login username cannot be changed here.</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Office/Unit</label>
                            <select name="office_unit_id" x-model="editModal.office_unit_id"
                                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">-- Select Office/Unit --</option>
                                @foreach ($officeUnits as $officeUnit)
                                    <option value="{{ $officeUnit->id }}">{{ $officeUnit->code }} - {{ $officeUnit->name }}</option>
                                @endforeach
                            </select>
                            @error('office_unit_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Prefix <span class="font-normal text-gray-400">(optional)</span></label>
                            <input type="text" name="name_prefix" x-model="editModal.name_prefix" placeholder="e.g. Engr."
                                   class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @error('name_prefix')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">First Name</label>
                            <input type="text" name="first_name" x-model="editModal.first_name"
                                   class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Middle Name <span class="font-normal text-gray-400">(optional)</span></label>
                            <input type="text" name="middle_name" x-model="editModal.middle_name"
                                   class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @error('middle_name')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Last Name</label>
                            <input type="text" name="last_name" x-model="editModal.last_name"
                                   class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Suffix <span class="font-normal text-gray-400">(optional)</span></label>
                            <input type="text" name="name_suffix" x-model="editModal.name_suffix" placeholder="e.g. RN"
                                   class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <p class="mt-1 text-xs leading-4 text-gray-400">Displayed after the name using conventional formatting.</p>
                            @error('name_suffix')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                            <input type="email" x-model="editModal.email" disabled
                                   class="w-full rounded-md border-gray-200 bg-gray-50 text-gray-500 shadow-sm">
                            <p class="mt-1 text-xs text-gray-400">Email cannot be changed here.</p>
                        </div>
                    </div>

                    <div class="mt-4 flex items-center justify-end gap-3 border-t border-gray-100 pt-3">
                        <button type="button" @click="editModal.show = false" class="rounded px-4 py-2 text-sm font-medium text-gray-500 hover:bg-gray-50 hover:text-gray-700">
                            Cancel
                        </button>
                        <button type="submit" class="rounded bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                            Update Staff Member
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Delete Confirmation Modal --}}
        <div x-show="deleteModal.show" x-cloak
             class="fixed inset-0 z-[70] flex items-center justify-center px-4"
             style="background: rgba(0,0,0,0.4);">
            <div @click.outside="deleteModal.show = false"
                 class="bg-white rounded-lg shadow-xl w-full max-w-sm p-6">

        <h3 class="text-lg font-semibold text-gray-800 mb-2">Deactivate Staff Member</h3>
                <p class="text-sm text-gray-500 mb-6">
                    Are you sure you want to deactivate <span class="font-medium text-gray-700" x-text="deleteModal.name"></span>?
                    Their login will be disabled and attendance history will be retained.
                </p>

                <form method="POST" :action="'{{ url('non-teaching-staff') }}/' + deleteModal.id">
                    @csrf
                    @method('DELETE')

                    <div class="flex items-center gap-3">
                        <button type="submit" class="bg-red-600 hover:bg-red-700 text-white text-sm font-medium px-4 py-2 rounded">
                            Deactivate / Archive
                        </button>
                        <button type="button" @click="deleteModal.show = false" class="text-sm text-gray-500 hover:text-gray-700">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <x-account-status-modal />
        <x-password-confirmation-modal />

        <x-qr-modal />
    </div>
</x-app-layout>
