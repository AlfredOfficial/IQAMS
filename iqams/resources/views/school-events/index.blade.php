<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold text-gray-800">School Events</h2>
    </x-slot>
    <div class="py-8" x-data="schoolEventsModal(@js([
    'showModal' => $errors->any() && old('form_context') === 'school_event_modal',
    'form' =>
        $errors->any() && old('form_context') === 'school_event_modal'
            ? [
                'id' => old('event_id', ''),
                'title' => old('title', ''),
                'description' => old('description', ''),
                'location' => old('location', ''),
                'starts_at' => old('starts_at', ''),
                'ends_at' => old('ends_at', ''),
                'attendance_mode' => old('attendance_mode', 'cancelled'),
                'target_scope' => old('target_scope', 'school'),
                'section_ids' => array_map('strval', old('section_ids', [])),
                'schedule_ids' => array_map('strval', old('schedule_ids', [])),
            ]
            : null,
    'baseUrl' => url('school-events'),
    'storeUrl' => route('school-events.store'),
]))" @keydown.escape.window="closeModal()">
        <div class="mx-auto max-w-7xl space-y-5 px-4 sm:px-6 lg:px-8">
            @if ($errors->any() && old('form_context') !== 'school_event_modal')
                <div class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">{{ $errors->first() }}
                </div>
            @endif
            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                <div class="flex flex-col gap-3 border-b border-gray-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-sm leading-6 text-gray-500">Dated exceptions for holidays, meetings, and required events.</p>
                    <button type="button" @click="openCreate()"
                        class="inline-flex shrink-0 items-center justify-center rounded-md bg-indigo-600 px-3.5 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">New event</button>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-[760px] w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <tr>
                                <th class="w-[30%] px-5 py-3.5">Event</th>
                                <th class="w-[25%] px-5 py-3.5">When</th>
                                <th class="w-[20%] px-5 py-3.5">Mode / scope</th>
                                <th class="w-[12%] px-5 py-3.5">Status</th>
                                <th class="w-[13%] px-5 py-3.5 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse($events as $event)
                                @php
                                    $eventForm = [
                                        'id' => $event->id,
                                        'title' => $event->title,
                                        'description' => $event->description ?? '',
                                        'location' => $event->location ?? '',
                                        'starts_at' => $event->starts_at->format('Y-m-d\TH:i'),
                                        'ends_at' => $event->ends_at->format('Y-m-d\TH:i'),
                                        'attendance_mode' => $event->attendance_mode,
                                        'target_scope' => $event->target_scope,
                                        'section_ids' => $event->targets
                                            ->pluck('section_id')
                                            ->filter()
                                            ->map(fn($id) => (string) $id)
                                            ->values(),
                                        'schedule_ids' => $event->targets
                                            ->pluck('schedule_id')
                                            ->filter()
                                            ->map(fn($id) => (string) $id)
                                            ->values(),
                                ]; @endphp
                                <tr class="transition-colors hover:bg-gray-50">
                                    <td class="px-5 py-4 align-top">
                                        <p class="font-semibold text-gray-900">{{ $event->title }}</p>
                                        <p class="text-xs text-gray-500">{{ $event->location ?: 'No location' }}</p>
                                    </td>
                                    <td class="whitespace-nowrap px-5 py-4 align-top text-gray-600">
                                        {{ $event->starts_at->format('M d, Y g:i A') }}<br><span class="text-xs">to
                                            {{ $event->ends_at->format('M d, Y g:i A') }}</span></td>
                                    <td class="px-5 py-4 align-top"><span
                                            class="capitalize">{{ str_replace('_', ' ', $event->attendance_mode) }}</span><br><span
                                            class="text-xs capitalize text-gray-500">{{ $event->target_scope }}</span>
                                    </td>
                                    <td class="px-5 py-4 align-top"><span
                                            class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $event->status === 'published' ? 'bg-green-100 text-green-700' : ($event->status === 'cancelled' ? 'bg-red-100 text-red-700' : 'bg-gray-100 text-gray-700') }}">{{ ucfirst($event->status) }}</span>
                                    </td>
                                    <td class="px-5 py-4 align-top">
                                        <div class="flex justify-end">
                                            @if ($event->status === 'draft' || ($event->status === 'published' && now()->lt($event->starts_at)) || !$event->attendance_logs_count)
                                            <x-record-action-menu>
                                            @if ($event->status === 'draft' || ($event->status === 'published' && now()->lt($event->starts_at)))
                                                <button type="button" @click="openEdit(@js($eventForm))"
                                                    class="block w-full px-4 py-2.5 text-left text-indigo-600 hover:bg-indigo-50">Edit</button>
                                            @endif
                                            @if ($event->status === 'draft')
                                                <form method="POST"
                                                    action="{{ route('school-events.publish', $event) }}"
                                                    data-password-confirmation-required>@csrf @method('PATCH')<button
                                                        class="block w-full px-4 py-2.5 text-left text-green-700 hover:bg-green-50">Publish</button></form>
                                            @endif
                                            @if ($event->status === 'published' && now()->lt($event->starts_at))
                                                <form method="POST"
                                                    action="{{ route('school-events.cancel', $event) }}"
                                                    data-password-confirmation-required>@csrf @method('PATCH')<button
                                                        class="block w-full px-4 py-2.5 text-left text-amber-700 hover:bg-amber-50">Cancel</button></form>
                                            @endif
                                            @if (!$event->attendance_logs_count)
                                                <form method="POST"
                                                    action="{{ route('school-events.destroy', $event) }}"
                                                    onsubmit="return confirm('Delete this event?')"
                                                    data-password-confirmation-required>@csrf @method('DELETE')<button
                                                        class="block w-full px-4 py-2.5 text-left text-red-600 hover:bg-red-50">Delete</button></form>
                                            @endif
                                            </x-record-action-menu>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty<tr>
                                    <td colspan="5" class="px-5 py-12 text-center">
                                        <div class="flex min-h-36 flex-col items-center justify-center">
                                            <x-heroicon-o-calendar-days class="mb-3 h-7 w-7 text-gray-400" aria-hidden="true" />
                                            <p class="text-sm font-semibold text-gray-900">No school events yet</p>
                                            <p class="mt-1 max-w-md text-sm text-gray-500">Create a school event to manage holidays, meetings, and other dated exceptions.</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="border-t px-5 py-3">{{ $events->links() }}</div>
            </div>
        </div>

        <div x-show="showModal" x-cloak
            class="fixed inset-y-0 right-0 left-0 z-50 flex items-center justify-center bg-black/40 p-4 lg:left-[260px]"
            :class="sidebarCollapsed ? 'lg:!left-[80px]' : 'lg:!left-[260px]'" @click.self="closeModal()">
            <section role="dialog" aria-modal="true" aria-labelledby="school-event-modal-title"
                class="flex max-h-[calc(100vh-2rem)] w-full max-w-2xl flex-col overflow-visible rounded-xl bg-white shadow-2xl">
                <div class="flex shrink-0 items-center justify-between border-b border-gray-100 px-6 py-4">
                    <h3 id="school-event-modal-title" class="text-lg font-semibold text-gray-900"
                        x-text="editing ? 'Edit School Event' : 'New School Event'"></h3>
                    <button type="button" @click="closeModal()"
                        class="rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-700"
                        aria-label="Close modal"><x-heroicon-o-x-mark class="h-5 w-5" /></button>
                </div>
                @include('school-events.form')
            </section>
        </div>
    </div>

</x-app-layout>
