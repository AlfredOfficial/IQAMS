<div class="mx-auto max-w-7xl space-y-6">
    <div>
        <h2 class="text-xl font-bold text-slate-900">My Leave Requests</h2>
        <p class="mt-1 text-sm text-slate-500">Submit a request and monitor its approval status.</p>
    </div>

    <form method="POST" action="{{ route('leave-requests.store') }}" enctype="multipart/form-data" class="grid gap-5 rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200/70 sm:grid-cols-2 sm:p-6">
        @csrf
        <h3 class="text-lg font-bold text-slate-900 sm:col-span-2">Request leave</h3>
        <div>
            <x-input-label for="leave_type" value="Leave type"/>
            <select id="leave_type" name="leave_type" class="mt-1 w-full rounded-xl border-slate-300" required>@foreach(['vacation'=>'Vacation Leave','sick'=>'Sick Leave','emergency'=>'Emergency Leave','other'=>'Other Leave'] as $value=>$label)<option value="{{ $value }}" @selected(old('leave_type')===$value)>{{ $label }}</option>@endforeach</select>
            <x-input-error :messages="$errors->get('leave_type')" class="mt-2"/>
        </div>
        <div>
            <x-input-label for="attachment" value="Supporting document (optional)"/>
            <input id="attachment" name="attachment" type="file" accept=".pdf,.jpg,.jpeg,.png" class="mt-2 block w-full text-sm text-slate-600 file:mr-4 file:rounded-lg file:border-0 file:bg-blue-50 file:px-4 file:py-2 file:font-semibold file:text-blue-700 hover:file:bg-blue-100">
            <x-input-error :messages="$errors->get('attachment')" class="mt-2"/>
        </div>
        <div><x-input-label for="start_date" value="Start date"/><x-text-input id="start_date" name="start_date" type="date" :value="old('start_date')" class="mt-1 w-full rounded-xl" required/><x-input-error :messages="$errors->get('start_date')" class="mt-2"/></div>
        <div><x-input-label for="end_date" value="End date"/><x-text-input id="end_date" name="end_date" type="date" :value="old('end_date')" class="mt-1 w-full rounded-xl" required/><x-input-error :messages="$errors->get('end_date')" class="mt-2"/></div>
        <div class="sm:col-span-2"><x-input-label for="reason" value="Reason"/><textarea id="reason" name="reason" rows="4" class="mt-1 w-full rounded-xl border-slate-300" required>{{ old('reason') }}</textarea><x-input-error :messages="$errors->get('reason')" class="mt-2"/></div>
        <div class="sm:col-span-2"><button class="rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-blue-700">Submit request</button></div>
    </form>

    <div class="overflow-x-auto rounded-2xl bg-white shadow-sm ring-1 ring-slate-200/70">
        <table class="min-w-full text-sm"><thead class="bg-slate-50 text-left text-slate-500"><tr><th class="p-4">Dates</th><th class="p-4">Type</th><th class="p-4">Status</th><th class="p-4">Review notes</th><th class="p-4"></th></tr></thead><tbody>@forelse($requests as $item)<tr class="border-t border-slate-100"><td class="p-4">{{ $item->start_date->format('M d, Y') }} &ndash; {{ $item->end_date->format('M d, Y') }}</td><td class="p-4">{{ $item->type_label }}</td><td class="p-4"><span class="rounded-full bg-slate-100 px-2.5 py-1 font-semibold text-slate-700">{{ ucfirst($item->status) }}</span></td><td class="p-4">{{ $item->review_notes ?: '—' }}</td><td class="p-4">@if($item->status==='pending')<form method="POST" action="{{ route('leave-requests.cancel',$item) }}">@csrf @method('PATCH')<button class="font-semibold text-red-600 hover:underline">Cancel</button></form>@endif</td></tr>@empty<tr><td colspan="5" class="p-8 text-center text-slate-500">No leave requests yet.</td></tr>@endforelse</tbody></table>
    </div>
    {{ $requests->links() }}
</div>
