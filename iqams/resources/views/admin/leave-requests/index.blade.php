<x-app-layout>
    <x-slot name="header"><h2 class="text-xl font-semibold text-gray-800">Leave Request Review</h2></x-slot>
    <div class="mx-auto max-w-7xl space-y-4 p-4 sm:p-6">
        <form class="flex gap-3 rounded-xl bg-white p-4 shadow-sm">
            <select name="status" class="rounded-md border-gray-300"><option value="">All statuses</option>@foreach(['pending','approved','rejected','cancelled'] as $status)<option value="{{ $status }}" @selected(request('status')===$status)>{{ ucfirst($status) }}</option>@endforeach</select>
            <x-primary-button>Filter</x-primary-button>
        </form>
        <div class="overflow-x-auto rounded-xl bg-white shadow-sm">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-left"><tr><th class="p-3">Requester</th><th class="p-3">Dates / Type</th><th class="p-3">Reason</th><th class="p-3">Status</th><th class="p-3">Review</th></tr></thead>
                <tbody>
                    @forelse($requests as $item)
                        <tr class="border-t align-top">
                            <td class="p-3 font-medium">{{ $item->user->name }}<br><span class="text-xs font-normal text-gray-500">{{ ucfirst($item->user->primaryRoleName() ?? '') }}</span></td>
                            <td class="p-3 whitespace-nowrap">{{ $item->start_date->format('M d, Y') }} – {{ $item->end_date->format('M d, Y') }}<br>{{ $item->type_label }}@if($item->attachment_path)<br><a class="text-indigo-600 hover:underline" target="_blank" href="{{ route('admin.leave-requests.attachment', $item) }}">View document</a>@endif</td>
                            <td class="max-w-sm p-3">{{ $item->reason }}</td><td class="p-3">{{ ucfirst($item->status) }}</td>
                            <td class="min-w-64 p-3">@if($item->status==='pending')<form method="POST" action="{{ route('admin.leave-requests.update',$item) }}" class="space-y-2">@csrf @method('PATCH')<textarea name="review_notes" rows="2" placeholder="Review notes (optional)" class="w-full rounded-md border-gray-300"></textarea><div class="flex gap-2"><button name="status" value="approved" class="rounded-md bg-green-600 px-3 py-2 text-white">Approve</button><button name="status" value="rejected" class="rounded-md bg-red-600 px-3 py-2 text-white">Reject</button></div></form>@else<span class="text-gray-500">{{ $item->review_notes ?: 'Reviewed' }}</span>@endif</td>
                        </tr>
                    @empty<tr><td colspan="5" class="p-8 text-center text-gray-500">No requests found.</td></tr>@endforelse
                </tbody>
            </table>
        </div>
        {{ $requests->links() }}
    </div>
</x-app-layout>
