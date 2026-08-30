<x-app-layout>
    <x-slot name="header"><h1 class="text-xl font-semibold text-gray-800">Offices and Administrative Units</h1></x-slot>
    <div class="py-8"
         x-data="{ create: {{ $errors->any() ? 'true' : 'false' }}, edit: { show:false, id:null, code:'', name:'', is_active:true }, deleteModal: { show:false, id:null, name:'' } }"
         @keydown.escape.window="create = false; edit.show = false; deleteModal.show = false">
        <div class="mx-auto max-w-6xl sm:px-6 lg:px-8">
            @if($errors->any())<div class="mb-4 rounded border border-red-200 bg-red-50 p-3 text-sm text-red-700">{{ $errors->first() }}</div>@endif
            <div class="overflow-hidden rounded-lg bg-white shadow-sm">
                <div class="flex items-center justify-between border-b px-6 py-4"><p class="text-sm text-gray-500">Official non-teaching assignments</p><button @click="create=true" class="rounded bg-indigo-600 px-4 py-2 text-sm font-medium text-white">+ Add Office/Unit</button></div>
                <table class="w-full text-left text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500"><tr><th class="px-6 py-3">Code</th><th class="px-6 py-3">Name</th><th class="px-6 py-3">Status</th><th class="px-6 py-3">Staff</th><th class="px-6 py-3 text-right">Actions</th></tr></thead>
                    <tbody class="divide-y">
                    @forelse($officeUnits as $unit)
                        <tr><td class="px-6 py-3 font-semibold">{{ $unit->code }}</td><td class="px-6 py-3">{{ $unit->name }}</td><td class="px-6 py-3">{{ $unit->is_active ? 'Active' : 'Inactive' }}</td><td class="px-6 py-3">{{ $unit->staff_count }}</td><td class="px-6 py-3"><div class="flex items-center justify-end gap-3"><button type="button" @click="edit={show:true,id:{{ $unit->id }},code:@js($unit->code),name:@js($unit->name),is_active:{{ $unit->is_active ? 'true' : 'false' }}}" class="text-indigo-600 hover:text-indigo-800">Edit</button><button type="button" @click="deleteModal={show:true,id:{{ $unit->id }},name:@js($unit->name)}" class="text-red-600 hover:text-red-800">Delete</button></div></td></tr>
                    @empty<tr><td colspan="5" class="px-6 py-8 text-center text-gray-400">No offices or units found.</td></tr>@endforelse
                    </tbody>
                </table>
                <div class="border-t px-6 py-4">{{ $officeUnits->links() }}</div>
            </div>
        </div>

        <div x-show="create" x-cloak class="fixed inset-0 z-50 grid place-items-center bg-black/40 p-4"><div @click.outside="create=false" class="w-full max-w-md rounded-lg bg-white p-6"><h2 class="mb-4 text-lg font-semibold">Add Office/Unit</h2><form method="POST" action="{{ route('office-units.store') }}" class="space-y-4">@csrf<input name="code" value="{{ old('code') }}" placeholder="Code (e.g. REG)" class="w-full rounded border-gray-300"><input name="name" value="{{ old('name') }}" placeholder="Official name" class="w-full rounded border-gray-300"><input type="hidden" name="is_active" value="1"><div class="flex gap-3"><button class="rounded bg-indigo-600 px-4 py-2 text-white">Save</button><button type="button" @click="create=false" class="text-gray-600">Cancel</button></div></form></div></div>
        <div x-show="edit.show" x-cloak class="fixed inset-0 z-50 grid place-items-center bg-black/40 p-4"><div @click.outside="edit.show=false" class="w-full max-w-md rounded-lg bg-white p-6"><h2 class="mb-4 text-lg font-semibold">Edit Office/Unit</h2><form method="POST" :action="'{{ url('office-units') }}/'+edit.id" class="space-y-4">@csrf @method('PUT')<input name="code" x-model="edit.code" class="w-full rounded border-gray-300"><input name="name" x-model="edit.name" class="w-full rounded border-gray-300"><label class="flex items-center gap-2"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" x-model="edit.is_active"> Active</label><div class="flex justify-end gap-3"><button type="button" @click="edit.show=false" class="text-gray-600">Cancel</button><button type="submit" class="rounded bg-indigo-600 px-4 py-2 text-white">Update</button></div></form></div></div>

        {{-- Delete Confirmation Modal --}}
        <div x-show="deleteModal.show" x-cloak class="fixed inset-0 z-50 flex items-center justify-center px-4" style="background: rgba(0,0,0,0.4);">
            <div @click.outside="deleteModal.show = false" class="w-full max-w-sm rounded-lg bg-white p-6 shadow-xl">
                <h3 class="mb-2 text-lg font-semibold text-gray-800">Delete Office/Unit</h3>
                <p class="mb-6 text-sm text-gray-500">Are you sure you want to delete <span class="font-medium text-gray-700" x-text="deleteModal.name"></span>? This can't be undone.</p>
                <form method="POST" :action="'{{ url('office-units') }}/' + deleteModal.id">
                    @csrf
                    @method('DELETE')
                    <div class="flex items-center gap-3">
                        <button type="submit" class="rounded bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700">Delete</button>
                        <button type="button" @click="deleteModal.show = false" class="text-sm text-gray-500 hover:text-gray-700">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
