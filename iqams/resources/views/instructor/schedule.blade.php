<x-instructor-layout title="My Teaching Schedule">
<div class="mb-6"><h2 class="text-xl font-semibold">My Teaching Schedule</h2><p class="text-sm text-slate-500">For reference only; this does not control work attendance.</p></div>
<div class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
@forelse(['monday','tuesday','wednesday','thursday','friday','saturday','sunday'] as $day)
    @if(isset($days[$day]))<section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200/70"><h3 class="text-lg font-semibold">{{ ucfirst($day) }}</h3><div class="mt-4 space-y-3">@foreach($days[$day] as $item)<div class="rounded-xl border border-slate-100 p-4"><p class="font-semibold">{{ $item->subject?->subject_code }} · {{ $item->subject?->subject_name }}</p><p class="text-sm text-slate-600">{{ \Carbon\Carbon::parse($item->start_time)->format('g:i A') }}–{{ \Carbon\Carbon::parse($item->end_time)->format('g:i A') }}</p><p class="text-sm text-slate-500">{{ $item->section?->section_name ?? 'No section' }} · Room {{ $item->room ?? 'TBD' }}</p></div>@endforeach</div></section>@endif
@empty<div class="rounded-2xl bg-white p-8">No schedules assigned.</div>@endforelse
</div>
</x-instructor-layout>
