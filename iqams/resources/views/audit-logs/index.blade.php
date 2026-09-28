<x-app-layout>
    <x-slot name="header">
        <div class="space-y-1 py-1">
            <h2 class="text-xl font-bold tracking-tight text-slate-900">Audit activity</h2>
            <p class="text-sm text-slate-500">Review who performed each action and what record was affected.</p>
        </div>
    </x-slot>

    <div class="space-y-6 p-4 sm:p-6">
        <form method="GET" action="{{ route('admin.audit-logs.index') }}"
            class="grid gap-x-5 gap-y-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:grid-cols-2 lg:grid-cols-3">
            <label class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Action
                <select name="action" class="mt-2 h-10 w-full rounded-lg border-slate-300 px-3 text-sm text-slate-700 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">All actions</option>
                    @foreach ($actions as $actionValue => $actionLabel)
                        <option value="{{ $actionValue }}" @selected(($filters['action'] ?? '') === $actionValue)>{{ $actionLabel }}</option>
                    @endforeach
                </select>
            </label>
            <label class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Performed by
                <select name="actor_id" class="mt-2 h-10 w-full rounded-lg border-slate-300 px-3 text-sm text-slate-700 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">All users and system actions</option>
                    @foreach ($actors as $actor)
                        <option value="{{ $actor->id }}" @selected((string) ($filters['actor_id'] ?? '') === (string) $actor->id)>{{ $actor->name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Affected record type
                <select name="subject_type" class="mt-2 h-10 w-full rounded-lg border-slate-300 px-3 text-sm text-slate-700 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">All record types</option>
                    @foreach ($subjectTypes as $typeValue => $typeLabel)
                        <option value="{{ $typeValue }}" @selected(($filters['subject_type'] ?? '') === $typeValue)>{{ $typeLabel }}</option>
                    @endforeach
                </select>
            </label>
            <label class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Record reference
                <input name="subject_id" value="{{ $filters['subject_id'] ?? '' }}" type="number" min="1"
                    placeholder="Optional reference" class="mt-2 h-10 w-full rounded-lg border-slate-300 px-3 text-sm text-slate-700 shadow-sm placeholder:text-slate-400 focus:border-indigo-500 focus:ring-indigo-500">
            </label>
            <label class="block text-xs font-semibold uppercase tracking-wide text-slate-500">From
                <input name="from" value="{{ $filters['from'] ?? '' }}" type="date"
                    class="mt-2 h-10 w-full rounded-lg border-slate-300 px-3 text-sm text-slate-700 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            </label>
            <label class="block text-xs font-semibold uppercase tracking-wide text-slate-500">To
                <input name="to" value="{{ $filters['to'] ?? '' }}" type="date"
                    class="mt-2 h-10 w-full rounded-lg border-slate-300 px-3 text-sm text-slate-700 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            </label>
            <div class="flex items-end justify-end gap-2 sm:col-span-2 lg:col-span-3">
                <a href="{{ route('admin.audit-logs.index') }}"
                    class="inline-flex h-10 items-center rounded-lg border border-slate-300 px-4 text-sm font-semibold text-slate-600 transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">Reset</a>
                <button class="h-10 rounded-lg bg-indigo-600 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">Filter</button>
            </div>
        </form>

        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
            <table class="min-w-[900px] w-full table-fixed text-left text-sm">
                <colgroup><col class="w-[145px]"><col class="w-[22%]"><col class="w-[18%]"><col class="w-[22%]"><col class="w-[220px]"></colgroup>
                <thead class="border-b border-slate-200 bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-5 py-3.5">Time</th>
                        <th class="px-5 py-3.5">Activity</th>
                        <th class="px-5 py-3.5">Performed by</th>
                        <th class="px-5 py-3.5">Affected record</th>
                        <th class="px-5 py-3.5">Details</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($logs as $log)
                        <tr class="align-middle transition hover:bg-slate-50">
                            <td class="whitespace-nowrap px-5 py-4">
                                <div class="font-semibold text-slate-700">{{ $log->created_at?->format('M j, Y') }}</div>
                                <div class="mt-0.5 text-xs text-slate-400">{{ $log->created_at?->format('g:i:s A') }}</div>
                            </td>
                            <td class="px-5 py-4 font-medium text-slate-800">{{ $log->action_label }}</td>
                            <td class="px-5 py-4 text-slate-700">{{ $log->actor_label }}</td>
                            <td class="px-5 py-4">
                                <div class="font-semibold text-slate-800">{{ $log->subject_label }}</div>
                                <div class="mt-0.5 text-xs text-slate-500">{{ $log->subject_type_label }}</div>
                            </td>
                            <td class="px-5 py-4">
                                <details class="group relative">
                                    <summary class="flex max-w-full cursor-pointer list-none items-center gap-1 overflow-hidden text-sm font-semibold text-indigo-600 hover:text-indigo-800">
                                        {{ count($log->metadata_items) ? count($log->metadata_items) . ' detail(s)' : 'View request details' }}
                                        <x-heroicon-o-chevron-right class="h-4 w-4 transition group-open:rotate-90" />
                                    </summary>
                                    <div class="absolute right-0 top-full z-20 mt-2 max-h-72 w-80 max-w-[calc(100vw-2rem)] overflow-y-auto rounded-lg border border-slate-200 bg-white p-3 text-xs text-slate-700 shadow-xl">
                                        @if (count($log->metadata_items))
                                            <dl class="space-y-2">
                                                @foreach ($log->metadata_items as $item)
                                                    <div class="grid gap-1 sm:grid-cols-3">
                                                        <dt class="font-semibold text-gray-500">{{ $item['label'] }}
                                                        </dt>
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
                        <tr>
                            <td colspan="5" class="px-5 py-14 text-center text-sm text-slate-500">No audit records match these
                                filters.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
            </div>
        </div>

        {{ $logs->links() }}
    </div>
</x-app-layout>
