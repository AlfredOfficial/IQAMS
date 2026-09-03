<x-staff-layout title="Attendance History">
    <div class="space-y-5">
        <div><h2 class="text-xl font-semibold">Attendance History</h2><p class="text-sm text-gray-500">Your workday attendance records are read-only.</p></div>
        <form class="grid gap-3 rounded-xl bg-white p-4 shadow-sm md:grid-cols-5">
            <input class="rounded-md border-gray-300" type="date" name="from" value="{{ $from->toDateString() }}">
            <input class="rounded-md border-gray-300" type="date" name="to" value="{{ $to->toDateString() }}">
            <select class="rounded-md border-gray-300" name="status"><option value="">All statuses</option>@foreach(['Present','On Leave','In Progress','Incomplete','Absent'] as $value)<option @selected(request('status')===$value)>{{ $value }}</option>@endforeach</select>
            <select class="rounded-md border-gray-300" name="punctuality"><option value="">All punctuality</option>@foreach(['On Time','Late','Early Out','Incomplete'] as $value)<option @selected(request('punctuality')===$value)>{{ $value }}</option>@endforeach</select>
            <button class="rounded-md bg-indigo-600 px-4 py-2 text-white hover:bg-indigo-700">Filter</button>
        </form>
        <div class="rounded-xl bg-white shadow-sm">@include('personnel.partials.attendance-table')</div>
    </div>
</x-staff-layout>
