<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold text-gray-800">Offices and Administrative Units</h1>
    </x-slot>
    <div class="py-8" x-data="{ create: {{ $errors->any() ? 'true' : 'false' }}, edit: { show: false, id: null, code: '', name: '', is_active: true }, deactivateModal: { show: false, id: null, name: '' } }"
        @keydown.escape.window="create = false; edit.show = false; deactivateModal.show = false">
        <div class="mx-auto max-w-6xl sm:px-6 lg:px-8">
            @if ($errors->any())
                <div class="mb-4 rounded border border-red-200 bg-red-50 p-3 text-sm text-red-700">{{ $errors->first() }}
                </div>
            @endif
            <div data-repeatable-list class="overflow-hidden rounded-lg bg-white shadow-sm">
                <div class="flex items-center justify-between border-b px-6 py-4">
                    <p class="text-sm text-gray-500">Official non-teaching assignments</p><button @click="create=true"
                        class="rounded bg-indigo-600 px-4 py-2 text-sm font-medium text-white">+ Add
                        Office/Unit</button>
                </div>
                <table class="w-full text-left text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                        <tr>
                            <th class="px-6 py-3">Code</th>
                            <th class="px-6 py-3">Name</th>
                            <th class="px-6 py-3">Status</th>
                            <th class="px-6 py-3">Staff</th>
                            <th class="px-6 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @forelse($officeUnits as $unit)
                            <tr>
                                <td class="px-6 py-3 font-semibold">{{ $unit->code }}</td>
                                <td class="px-6 py-3">{{ $unit->name }}</td>
                                <td class="px-6 py-3">{{ $unit->is_active ? 'Active' : 'Inactive' }}</td>
                                <td class="px-6 py-3">{{ $unit->staff_count }}</td>
                                <td class="px-6 py-3 text-right"><x-record-action-menu><button type="button"
                                            @click="edit={show:true,id:{{ $unit->id }},code:@js($unit->code),name:@js($unit->name),is_active:{{ $unit->is_active ? 'true' : 'false' }}}"
                                            class="text-indigo-600 hover:text-indigo-800">Edit</button>@if ($unit->is_active)<button
                                            type="button"
                                            @click="deactivateModal={show:true,id:{{ $unit->id }},name:@js($unit->name)}"
                                            class="!text-red-600 hover:!text-red-700">Deactivate</button>@endif</x-record-action-menu>
                                </td>
                            </tr>
                        @empty<tr>
                                <td colspan="5" class="px-6 py-8 text-center text-gray-400">No offices or units
                                    found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
                <div class="border-t px-6 py-4">{{ $officeUnits->links() }}</div>
            </div>
        </div>

        <div x-show="create" x-cloak x-transition.opacity class="fixed inset-0 z-[90] grid place-items-center bg-slate-900/50 backdrop-blur-sm p-4">
            <div @click.outside="create=false" class="w-full max-w-md rounded-lg bg-white p-6 shadow-xl">
                <h2 class="mb-4 text-lg font-semibold text-gray-800">Add Office/Unit</h2>
                <form method="POST" action="{{ route('office-units.store') }}" class="space-y-4" data-repeatable-create>
                    @csrf
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Office Code</label>
                        <input name="code" value="{{ old('code') }}" placeholder="e.g. REG"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Office/Unit Name</label>
                        <input name="name" value="{{ old('name') }}" placeholder="Official name"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                    <input type="hidden" name="is_active" value="1">
                    <div class="flex justify-end gap-3 pt-2">
                        <button type="button" @click="create=false" class="rounded-md px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100">
                            Cancel
                        </button>
                        <button class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                            Save
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <div x-show="edit.show" x-cloak x-transition.opacity class="fixed inset-0 z-[90] grid place-items-center bg-slate-900/50 backdrop-blur-sm p-4">
            <div @click.outside="edit.show=false" class="w-full max-w-md rounded-lg bg-white p-6 shadow-xl">
                <h2 class="mb-4 text-lg font-semibold text-gray-800">Edit Office/Unit</h2>
                <form method="POST" :action="'{{ url('office-units') }}/' + edit.id" class="space-y-4"
                    data-password-confirmation-required>
                    @csrf
                    @method('PUT')
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Office Code</label>
                        <input name="code" x-model="edit.code" class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Office/Unit Name</label>
                        <input name="name" x-model="edit.name" class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                    <div class="pt-1">
                        <label class="inline-flex cursor-pointer items-center gap-2.5 text-sm font-medium text-gray-700">
                            <input type="hidden" name="is_active" value="0">
                            <input type="checkbox" name="is_active" value="1" x-model="edit.is_active"
                                class="h-4 w-4 rounded-md border-gray-300 text-indigo-600 shadow-sm focus:ring-2 focus:ring-indigo-500 focus:ring-offset-1">
                            <span>Active</span>
                        </label>
                    </div>
                    <div class="flex justify-end gap-3 pt-2">
                        <button type="button" @click="edit.show=false" class="rounded-md px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100">Cancel</button>
                        <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">Update</button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Deactivate Confirmation Modal --}}
        <div x-show="deactivateModal.show" x-cloak x-transition.opacity class="fixed inset-0 z-[90] flex items-center justify-center bg-slate-900/50 backdrop-blur-sm px-4">
            <div @click.outside="deactivateModal.show = false" class="w-full max-w-sm rounded-lg bg-white p-6 shadow-xl">
                <h3 class="mb-2 text-lg font-semibold text-gray-800">Deactivate Office/Unit</h3>
                <p class="mb-6 text-sm text-gray-500">Are you sure you want to deactivate <span
                        class="font-medium text-gray-700" x-text="deactivateModal.name"></span>? The office/unit will be marked as inactive and can be reactivated by editing it later.</p>
                <form method="POST" :action="'{{ url('office-units') }}/' + deactivateModal.id"
                    data-password-confirmation-required>
                    @csrf
                    @method('DELETE')
                    <div class="flex items-center gap-3">
                        <button type="submit"
                            class="rounded bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700">Deactivate</button>
                        <button type="button" @click="deactivateModal.show = false"
                            class="text-sm text-gray-500 hover:text-gray-700">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
