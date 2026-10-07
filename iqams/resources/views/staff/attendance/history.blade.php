<x-staff-layout title="Attendance History">
    <div class="space-y-5">
        <div>
            <h2 class="text-xl font-semibold">Attendance History</h2>
            <p class="text-sm text-gray-500">Your workday attendance records are read-only.</p>
        </div>
        <form class="grid grid-cols-2 gap-3 rounded-xl bg-white p-4 shadow-sm sm:grid-cols-2 lg:grid-cols-5">
            <input class="min-w-0 w-full rounded-md border-gray-300" type="date" name="from" value="{{ $from->toDateString() }}" aria-label="From date">
            <input class="min-w-0 w-full rounded-md border-gray-300" type="date" name="to" value="{{ $to->toDateString() }}" aria-label="To date">
            <select class="min-w-0 w-full rounded-md border-gray-300" name="status" aria-label="Attendance status">
                <option value="">All statuses</option>
                @foreach (['Present', 'On Leave', 'In Progress', 'Incomplete', 'Absent'] as $value)
                    <option @selected(request('status') === $value)>{{ $value }}</option>
                @endforeach
            </select>
            <select class="min-w-0 w-full rounded-md border-gray-300" name="punctuality" aria-label="Punctuality">
                <option value="">All punctuality</option>
                @foreach (['On Time', 'Late', 'Early Out', 'Incomplete'] as $value)
                    <option @selected(request('punctuality') === $value)>{{ $value }}</option>
                @endforeach
            </select>
            <div class="col-span-2 grid grid-cols-2 gap-3 lg:col-span-1">
                <a href="{{ route('staff.attendance.history') }}" class="inline-flex min-w-0 items-center justify-center rounded-md bg-gray-100 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-200">Cancel</a>
                <button class="min-w-0 rounded-md bg-indigo-600 px-4 py-2 text-white hover:bg-indigo-700">Filter</button>
            </div>
        </form>
        <div class="rounded-xl bg-white shadow-sm">@include('personnel.partials.attendance-table')</div>
    </div>
</x-staff-layout>
