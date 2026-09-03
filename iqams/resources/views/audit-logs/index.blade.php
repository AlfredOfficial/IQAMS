<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Audit activity</h2>
            <p class="mt-1 text-sm text-gray-500">Review who performed each action and what record was affected.</p>
        </div>
    </x-slot>

    <div class="space-y-6 p-6">
        <form method="GET" action="{{ route('admin.audit-logs.index') }}" class="grid gap-3 rounded-xl bg-white p-5 shadow sm:grid-cols-2 lg:grid-cols-6">
            <label class="text-xs font-medium uppercase tracking-wide text-gray-500">Action
                <select name="action" class="mt-1 w-full rounded-md border-gray-300 text-sm">
                    <option value="">All actions</option>
                    @foreach ($actions as $actionValue => $actionLabel)
                        <option value="{{ $actionValue }}" @selected(($filters['action'] ?? '') === $actionValue)>{{ $actionLabel }}</option>
                    @endforeach
                </select>
            </label>
            <label class="text-xs font-medium uppercase tracking-wide text-gray-500">Performed by
                <select name="actor_id" class="mt-1 w-full rounded-md border-gray-300 text-sm">
                    <option value="">All users and system actions</option>
                    @foreach ($actors as $actor)
                        <option value="{{ $actor->id }}" @selected((string)($filters['actor_id'] ?? '') === (string)$actor->id)>{{ $actor->name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="text-xs font-medium uppercase tracking-wide text-gray-500">Affected record type
                <select name="subject_type" class="mt-1 w-full rounded-md border-gray-300 text-sm">
                    <option value="">All record types</option>
                    @foreach ($subjectTypes as $typeValue => $typeLabel)
                        <option value="{{ $typeValue }}" @selected(($filters['subject_type'] ?? '') === $typeValue)>{{ $typeLabel }}</option>
                    @endforeach
                </select>
            </label>
            <label class="text-xs font-medium uppercase tracking-wide text-gray-500">Record reference
                <input name="subject_id" value="{{ $filters['subject_id'] ?? '' }}" type="number" min="1" placeholder="Optional reference" class="mt-1 w-full rounded-md border-gray-300 text-sm">
            </label>
            <label class="text-xs font-medium uppercase tracking-wide text-gray-500">From
                <input name="from" value="{{ $filters['from'] ?? '' }}" type="date" class="mt-1 w-full rounded-md border-gray-300 text-sm">
            </label>
            <label class="text-xs font-medium uppercase tracking-wide text-gray-500">To
                <input name="to" value="{{ $filters['to'] ?? '' }}" type="date" class="mt-1 w-full rounded-md border-gray-300 text-sm">
            </label>
            <div class="flex items-end gap-2 sm:col-span-2 lg:col-span-6">
                <button class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white">Filter</button>
                <a href="{{ route('admin.audit-logs.index') }}" class="rounded-md border px-4 py-2 text-sm text-gray-700">Reset</a>
            </div>
        </form>

        <div class="overflow-x-auto rounded-xl bg-white shadow">
            <table class="min-w-full divide-y divide-gray-200 text-left text-sm">
                <thead class="bg-gray-50 text-gray-600">
                    <tr>
                        <th class="px-4 py-3">Time</th>
                        <th class="px-4 py-3">Activity</th>
                        <th class="px-4 py-3">Performed by</th>
                        <th class="px-4 py-3">Affected record</th>
                        <th class="px-4 py-3">Details</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($logs as $log)
                        <tr class="align-top">
                            <td class="whitespace-nowrap px-4 py-3 text-gray-600">
                                <div class="font-medium text-gray-800">{{ $log->created_at?->format('M j, Y') }}</div>
                                <div class="text-xs">{{ $log->created_at?->format('g:i:s A') }}</div>
                            </td>
                            <td class="px-4 py-3 font-medium text-gray-800">{{ $log->action_label }}</td>
                            <td class="px-4 py-3 text-gray-700">{{ $log->actor_label }}</td>
                            <td class="px-4 py-3">
                                <div class="font-medium text-gray-800">{{ $log->subject_label }}</div>
                                <div class="text-xs text-gray-500">{{ $log->subject_type_label }}</div>
                            </td>
                            <td class="max-w-lg px-4 py-3">
                                <details>
                                    <summary class="cursor-pointer font-medium text-indigo-600 hover:text-indigo-800">
                                        {{ count($log->metadata_items) ? count($log->metadata_items).' detail(s)' : 'View request details' }}
                                    </summary>
                                    <div class="mt-3 space-y-3 rounded-lg bg-gray-50 p-3 text-xs text-gray-700">
                                        @if (count($log->metadata_items))
                                            <dl class="space-y-2">
                                                @foreach ($log->metadata_items as $item)
                                                    <div class="grid gap-1 sm:grid-cols-3">
                                                        <dt class="font-semibold text-gray-500">{{ $item['label'] }}</dt>
                                                        <dd class="break-words sm:col-span-2">{{ $item['value'] }}</dd>
                                                    </div>
                                                @endforeach
                                            </dl>
                                        @else
                                            <p class="text-gray-500">No additional metadata recorded.</p>
                                        @endif
                                        <dl class="border-t border-gray-200 pt-2">
                                            <div class="grid gap-1 sm:grid-cols-3">
                                                <dt class="font-semibold text-gray-500">Route</dt>
                                                <dd class="break-words sm:col-span-2">{{ $log->route_label }}</dd>
                                            </div>
                                            <div class="grid gap-1 sm:grid-cols-3">
                                                <dt class="font-semibold text-gray-500">IP address</dt>
                                                <dd class="sm:col-span-2">{{ $log->ip_address ?: 'Not recorded' }}</dd>
                                            </div>
                                            @if ($log->user_agent)
                                                <div class="grid gap-1 sm:grid-cols-3">
                                                    <dt class="font-semibold text-gray-500">Browser</dt>
                                                    <dd class="break-all sm:col-span-2">{{ $log->user_agent }}</dd>
                                                </div>
                                            @endif
                                        </dl>
                                    </div>
                                </details>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-8 text-center text-gray-500">No audit records match these filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $logs->links() }}
    </div>
</x-app-layout>
